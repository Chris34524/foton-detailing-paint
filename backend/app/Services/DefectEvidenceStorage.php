<?php

namespace App\Services;

use App\Models\Defect;
use App\Models\DefectEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DefectEvidenceStorage
{
    public function disk(?string $name = null): FilesystemAdapter
    {
        $name ??= config('diagnosis.evidence_disk');
        $config = config('filesystems.disks.'.$name);
        if (! is_array($config) || ($config['visibility'] ?? 'private') !== 'private'
            || ($config['serve'] ?? false) || ! empty($config['url'])) {
            throw new RuntimeException('Diagnosis evidence requires a private disk without public serving.');
        }
        $disk = Storage::disk($name);
        if (($config['driver'] ?? null) === 'local') {
            $root = str_replace('\\', '/', realpath($disk->path('')) ?: $disk->path(''));
            foreach ([public_path(), storage_path('app/public')] as $publicRoot) {
                $publicRoot = str_replace('\\', '/', realpath($publicRoot) ?: $publicRoot);
                if ($root === $publicRoot || str_starts_with($root, rtrim($publicRoot, '/').'/')) {
                    throw new RuntimeException('Diagnosis evidence cannot use a public filesystem directory.');
                }
            }
        }

        return $disk;
    }

    /** @param list<UploadedFile> $files */
    public function store(Defect $defect, User $user, array $files): Collection
    {
        $diskName = config('diagnosis.evidence_disk');
        $disk = $this->disk($diskName);
        $paths = [];
        try {
            return DB::transaction(function () use ($defect, $user, $files, $diskName, $disk, &$paths): Collection {
                $evidences = new Collection;
                foreach ($files as $file) {
                    $mime = $file->getMimeType();
                    $extension = match ($mime) {
                        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                        default => throw new RuntimeException('Invalid evidence MIME type.'),
                    };
                    $directory = 'diagnosis-'.$defect->diagnosis_id.'/defect-'.$defect->id;
                    $filename = Str::uuid().'.'.$extension;
                    $path = $directory.'/'.$filename;
                    $paths[] = $path;
                    if ($disk->putFileAs($directory, $file, $filename, ['visibility' => 'private']) === false) {
                        throw new RuntimeException('Evidence storage failed.');
                    }
                    $evidence = new DefectEvidence;
                    $evidence->disk = $diskName;
                    $evidence->path = $path;
                    $evidence->original_name = Str::limit(
                        preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'evidence.'.$extension,
                        255, '',
                    );
                    $evidence->mime_type = $mime;
                    $evidence->size_bytes = $file->getSize();
                    $evidence->uploadedBy()->associate($user);
                    $defect->evidences()->save($evidence);
                    $evidences->push($evidence);
                }

                return $evidences->load(['uploadedBy', 'defect']);
            });
        } catch (Throwable $error) {
            if ($paths !== [] && ! $disk->delete($paths)) {
                throw new RuntimeException('Failed to clean up an unsuccessful evidence upload.', previous: $error);
            }
            throw $error;
        }
    }
}
