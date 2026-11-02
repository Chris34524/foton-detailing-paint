<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Defect;
use App\Models\DefectEvidence;
use App\Models\Diagnosis;
use App\Models\User;
use App\Services\DefectEvidenceStorage;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class DefectEvidenceTest extends DiagnosisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('diagnosis_evidence');
    }

    private function url(Defect $defect): string
    {
        return '/api/diagnoses/'.$defect->diagnosis_id.'/defects/'.$defect->id.'/evidences';
    }

    public function test_guest_and_users_without_update_cannot_upload(): void
    {
        $defect = Defect::factory()->create();
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertUnauthorized();
        $this->withPermissions(Permission::DiagnosisView);
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertForbidden();
    }

    public static function formats(): array
    {
        return [['jpg', 'image/jpeg'], ['png', 'image/png'], ['webp', 'image/webp']];
    }

    #[DataProvider('formats')]
    public function test_real_images_are_private_with_safe_names_and_public_metadata(string $extension, string $mime): void
    {
        $actor = $this->withPermissions(Permission::DiagnosisUpdate, Permission::DiagnosisView);
        $defect = Defect::factory()->create();
        $file = UploadedFile::fake()->image('original.'.$extension, 20, 20);
        $response = $this->postJson($this->url($defect), ['files' => [$file]])->assertCreated()
            ->assertJsonPath('data.0.mime_type', $mime)->assertJsonPath('data.0.uploaded_by', ['id' => $actor->id, 'name' => $actor->name])
            ->assertJsonMissingPath('data.0.disk')->assertJsonMissingPath('data.0.path');
        $evidence = DefectEvidence::query()->sole();
        Storage::disk('diagnosis_evidence')->assertExists($evidence->path);
        $this->assertSame('diagnosis_evidence', $evidence->disk);
        $this->assertNotSame($file->getClientOriginalName(), basename($evidence->path));
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}\.(jpg|png|webp)$/', basename($evidence->path));
        $this->assertSame($file->getSize(), (int) $evidence->size_bytes);
        $this->assertSame('private', Storage::disk('diagnosis_evidence')->getVisibility($evidence->path));
        $image = $this->get($response->json('data.0.view_url'))->assertOk()->assertHeader('Content-Type', $mime);
        $this->assertStringContainsString('inline;', $image->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $image->headers->get('Cache-Control'));
        $image->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotEmpty($image->streamedContent());
        $this->assertStringNotContainsString($evidence->path, $response->getContent());
    }

    public function test_multiple_files_are_atomic_and_maximum_five_is_enforced(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $files = array_map(fn ($index) => UploadedFile::fake()->image('file-'.$index.'.png'), range(1, 5));
        $this->postJson($this->url($defect), ['files' => $files])->assertCreated()->assertJsonCount(5, 'data');
        $this->postJson($this->url($defect), ['files' => [...$files, UploadedFile::fake()->image('sixth.jpg')]])
            ->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->assertDatabaseCount('defect_evidences', 5);
    }

    public static function badFiles(): array
    {
        return [
            ['bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            ['bad.jpg', '<?php echo "not an image";'],
            ['bad.html', '<html>bad</html>'], ['bad.js', 'alert(1)'],
            ['bad.exe', 'arbitrary binary'], ['bad.pdf', '%PDF-1.7'],
        ];
    }

    #[DataProvider('badFiles')]
    public function test_non_image_content_is_rejected(string $name, string $content): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->createWithContent($name, $content)]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->assertSame([], Storage::disk('diagnosis_evidence')->allFiles());
    }

    public function test_size_missing_files_spoofed_mime_and_server_fields_are_rejected(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('large.jpg')->size(10241)]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->create('fake.jpg', 1, 'image/jpeg')]])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->postJson($this->url($defect), [])->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('image.jpg')], 'path' => 'forged', 'uploaded_by' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['path', 'uploaded_by']);
    }

    public function test_nested_ownership_is_scoped_for_upload_and_retrieval(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $otherDiagnosis = Diagnosis::factory()->create();
        $otherDefect = Defect::factory()->for($defect->diagnosis)->create();
        $evidence = DefectEvidence::factory()->for($otherDefect)->create();
        $this->postJson('/api/diagnoses/'.$otherDiagnosis->id.'/defects/'.$defect->id.'/evidences',
            ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertNotFound();
        $this->getJson($this->url($defect).'/'.$evidence->id)->assertNotFound();
        $this->getJson('/api/diagnoses/'.$otherDiagnosis->id.'/defects/'.$otherDefect->id.'/evidences/'.$evidence->id)->assertNotFound();
    }

    public function test_retrieval_requires_view_and_no_public_url_bypasses_authentication(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $response = $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertCreated();
        $viewUrl = $response->json('data.0.view_url');
        $this->withPermissions(Permission::DiagnosisUpdate);
        $this->getJson($viewUrl)->assertForbidden();
        $this->withPermissions(Permission::DiagnosisView);
        $this->get($viewUrl)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson($viewUrl)->assertUnauthorized();
        $evidence = DefectEvidence::query()->sole();
        foreach (['/storage/'.$evidence->path, '/storage/diagnosis-evidence/'.$evidence->path, '/diagnosis-evidence/'.$evidence->path] as $publicUrl) {
            $this->assertContains($this->get($publicUrl)->status(), [403, 404]);
        }
    }

    public function test_database_failure_rolls_back_all_records_and_cleans_all_images(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $count = 0;
        Event::listen('eloquent.creating: '.DefectEvidence::class, function () use (&$count): void {
            if (++$count === 2) {
                throw new RuntimeException('Synthetic DB failure with private/path');
            }
        });
        try {
            $response = $this->postJson($this->url($defect), ['files' => [
                UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png'),
            ]])->assertStatus(503);
            $response->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
            $this->assertStringNotContainsString('private/path', $response->getContent());
            $this->assertDatabaseCount('defect_evidences', 0);
            $this->assertSame([], Storage::disk('diagnosis_evidence')->allFiles());
        } finally {
            Event::forget('eloquent.creating: '.DefectEvidence::class);
        }
    }

    public function test_partial_storage_failure_cleans_prior_files_and_rolls_back_metadata(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $realDisk = Storage::disk('diagnosis_evidence');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $attempt = 0;
        $disk->shouldReceive('putFileAs')->twice()->andReturnUsing(function ($directory, $file, $name, $options) use ($realDisk, &$attempt) {
            return ++$attempt === 2 ? false : $realDisk->putFileAs($directory, $file, $name, $options);
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(fn ($paths) => $realDisk->delete($paths));
        $this->partialMock(DefectEvidenceStorage::class, fn ($mock) => $mock->shouldReceive('disk')->andReturn($disk));
        $this->postJson($this->url($defect), ['files' => [
            UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png'),
        ]])->assertStatus(503);
        $this->assertDatabaseCount('defect_evidences', 0);
        $this->assertSame([], $realDisk->allFiles());
    }

    public function test_public_or_web_served_disk_configuration_fails_closed(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        foreach (['public', 'local'] as $name) {
            config(['diagnosis.evidence_disk' => $name]);
            $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertStatus(503);
        }
        config(['filesystems.disks.unsafe' => [
            'driver' => 'local', 'root' => public_path(), 'visibility' => 'private', 'serve' => false,
        ], 'diagnosis.evidence_disk' => 'unsafe']);
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertStatus(503);
    }

    public function test_missing_files_are_404_and_no_delete_workflow_is_exposed(): void
    {
        $this->administrator();
        $evidence = DefectEvidence::factory()->create();
        $path = $this->url($evidence->defect).'/'.$evidence->id;
        $this->getJson($path)->assertNotFound();
        $this->deleteJson($path)->assertMethodNotAllowed();
    }

    public function test_uploader_removal_preserves_metadata_and_core_fk_restricts_deletion(): void
    {
        $actor = User::factory()->create();
        $evidence = DefectEvidence::factory()->create(['uploaded_by' => $actor->id]);
        $actor->delete();
        $this->assertNull($evidence->fresh()->uploaded_by);
        $this->expectException(QueryException::class);
        $evidence->defect->delete();
    }

    public function test_upload_keeps_csrf_enabled(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->postJson($this->url($defect), ['files' => [UploadedFile::fake()->image('test.jpg')]])->assertStatus(419);
    }
}
