<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Material;
use App\Models\User;
use App\Services\InventoryMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    /** Separate committed connections make actual MySQL row locks observable. */
    public function test_mysql_serializes_competing_exits_retries_and_global_folios(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires MySQL row locks; exercised by the MySQL CI job.');
        }
        $permissionExisted = PermissionModel::where('name', Permission::MaterialsManage->value)->where('guard_name', 'web')->exists();
        $permission = PermissionModel::findOrCreate(Permission::MaterialsManage->value, 'web');
        $actor = User::factory()->create();
        $actor->givePermissionTo($permission);
        $materials = Material::factory()->count(2)->create(['unit' => 'L', 'created_by' => $actor->id]);
        $counter = DB::table('inventory_sequences')->where('name', 'movement_folio')->value('next_value');
        try {
            app(InventoryMovementService::class)->confirm($actor, [
                'material_id' => $materials[0]->id, 'movement_type' => 'opening_balance',
                'quantity_original' => '1', 'unit_original' => 'L', 'idempotency_key' => (string) Str::uuid(),
            ]);
            $base = ['material_id' => $materials[0]->id, 'movement_type' => 'exit', 'quantity_original' => '1', 'unit_original' => 'L'];
            $results = $this->race($actor, [
                $base + ['idempotency_key' => (string) Str::uuid()],
                $base + ['idempotency_key' => (string) Str::uuid()],
            ]);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['created', 'rejected'], $statuses);
            $this->assertSame('0.000000', $materials[0]->fresh()->inventoryBalance->quantity);
            $this->assertSame(1, $materials[0]->inventoryMovements()->where('movement_type', 'exit')->count());
            $this->assertSame(['quantity_original'], collect($results)->firstWhere('status', 'rejected')['fields']);

            $winner = collect($results)->firstWhere('status', 'created');
            $payload = $base + ['idempotency_key' => $winner['key']];
            $retries = $this->race($actor, [$payload, $payload]);
            $this->assertSame(['replayed', 'replayed'], array_column($retries, 'status'));
            $this->assertSame($winner['id'], $retries[0]['id']);
            $this->assertSame($winner['id'], $retries[1]['id']);

            $payload = ['material_id' => $materials[1]->id, 'movement_type' => 'entry',
                'quantity_original' => '2', 'unit_original' => 'L', 'idempotency_key' => (string) Str::uuid()];
            $duplicates = $this->race($actor, [$payload, $payload]);
            $statuses = array_column($duplicates, 'status');
            sort($statuses);
            $this->assertSame(['created', 'replayed'], $statuses);
            $this->assertSame($duplicates[0]['id'], $duplicates[1]['id']);
            $this->assertSame('2.000000', $materials[1]->fresh()->inventoryBalance->quantity);

            $entries = $this->race($actor, $materials->map(fn (Material $material): array => [
                'material_id' => $material->id, 'movement_type' => 'entry', 'quantity_original' => '1',
                'unit_original' => 'L', 'idempotency_key' => (string) Str::uuid(),
            ])->all());
            $this->assertSame(['created', 'created'], array_column($entries, 'status'));
            $this->assertNotSame($entries[0]['folio'], $entries[1]['folio']);
            $numbers = $materials->flatMap(fn (Material $material) => $material->inventoryMovements()->pluck('folio'))
                ->map(fn (string $folio): int => (int) substr($folio, 7))->sort()->values()->all();
            $this->assertSame(range((int) $counter, (int) $counter + 4), $numbers);
        } finally {
            DB::table('inventory_movements')->whereIn('material_id', $materials->modelKeys())->delete();
            DB::table('inventory_balances')->whereIn('material_id', $materials->modelKeys())->delete();
            Material::whereKey($materials->modelKeys())->delete();
            $actor->delete();
            if (! $permissionExisted) {
                $permission->delete();
            }
            DB::table('inventory_sequences')->where('name', 'movement_folio')->update(['next_value' => $counter]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /** @return list<array{status:string,id?:int,folio?:string,key?:string,fields?:array}> */
    private function race(User $actor, array $payloads): array
    {
        $directory = sys_get_temp_dir().'/foton-inventory-'.Str::uuid();
        mkdir($directory, 0700);
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = json_decode(getenv('INVENTORY_TEST_CONNECTION'), true, flags: JSON_THROW_ON_ERROR);
config(['database.default' => 'mysql', 'database.connections.mysql' => $connection, 'cache.default' => 'array']);
\Illuminate\Support\Facades\DB::statement("SET SESSION innodb_lock_wait_timeout = 3");
$actor = \App\Models\User::findOrFail((int) getenv('INVENTORY_TEST_ACTOR'));
$payload = json_decode(getenv('INVENTORY_TEST_PAYLOAD'), true, flags: JSON_THROW_ON_ERROR);
touch(getenv('INVENTORY_TEST_READY'));
$deadline = microtime(true) + 15;
while (!is_file(getenv('INVENTORY_TEST_BARRIER'))) {
    if (microtime(true) > $deadline) { throw new \RuntimeException('Barrier timeout'); }
    clearstatcache(); usleep(1000);
}
try {
    $result = app(\App\Services\InventoryMovementService::class)->confirm($actor, $payload);
    echo json_encode(['status' => $result['replayed'] ? 'replayed' : 'created', 'id' => $result['movement']->id, 'folio' => $result['movement']->folio, 'key' => $payload['idempotency_key']], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException $error) {
    echo json_encode(['status' => 'rejected', 'fields' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
}
PHP;
        $processes = [];
        try {
            foreach ($payloads as $index => $payload) {
                $process = new Process([PHP_BINARY, '-r', $code], base_path(), [
                    'APP_ENV' => 'testing',
                    'INVENTORY_TEST_CONNECTION' => json_encode(DB::connection()->getConfig(), JSON_THROW_ON_ERROR),
                    'INVENTORY_TEST_ACTOR' => (string) $actor->id,
                    'INVENTORY_TEST_PAYLOAD' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'INVENTORY_TEST_READY' => $directory.'/ready-'.$index,
                    'INVENTORY_TEST_BARRIER' => $directory.'/go',
                ]);
                $process->setTimeout(25)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (count(glob($directory.'/ready-*')) !== count($payloads)) {
                if (microtime(true) > $deadline || collect($processes)->contains(fn (Process $process): bool => ! $process->isRunning())) {
                    $this->fail('Inventory workers did not reach the synchronization barrier.');
                }
                clearstatcache();
                usleep(1000);
            }
            touch($directory.'/go');

            return array_map(function (Process $process): array {
                try {
                    $process->wait();
                } catch (\Throwable $error) {
                    $this->fail('Worker timeout: '.$process->getErrorOutput().$process->getOutput());
                }
                $this->assertTrue($process->isSuccessful(), 'Inventory worker failed: '.$process->getErrorOutput());

                return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }, $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
