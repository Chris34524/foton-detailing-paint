<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetLocalMaterials extends Command
{
    protected $signature = 'materials:reset-local {--database= : Exact database name to reset}';

    protected $description = 'Delete only Materials and Inventory data from a confirmed local development database';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Bloqueado: solo está permitido con APP_ENV=local.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();
        $expected = (string) $this->option('database');
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            $this->error('Motor de base de datos no admitido para esta limpieza.');

            return self::FAILURE;
        }

        if ($driver === 'mysql' && ! in_array((string) $connection->getConfig('host'), ['mysql', '127.0.0.1', 'localhost'], true)) {
            $this->error('Bloqueado: el host de MySQL no coincide con el entorno local esperado.');

            return self::FAILURE;
        }

        if ($expected === '' || $expected !== $database) {
            $this->error('Base actual: '.$database.'. Ejecuta de nuevo con --database='.$database.' solo si es tu base LOCAL de pruebas.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Esta limpieza exige una confirmación interactiva en la consola.');

            return self::FAILURE;
        }

        $tables = ['material_consumptions', 'inventory_movements', 'inventory_balances', 'materials'];
        try {
            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = DB::table($table)->count();
            }

            $this->warn('ATENCIÓN: borrarás los siguientes registros de la base local '.$database.':');
            foreach ($counts as $table => $count) {
                $this->line('  '.$table.': '.$count);
            }
            $this->warn('Se conservarán unidades, diagnósticos, procesos, órdenes, inspecciones, usuarios y permisos.');
            $this->warn('Realiza primero un respaldo si necesitas conservar estos datos.');
            if (trim((string) $this->ask('Para confirmar escribe exactamente REINICIAR MATERIALES')) !== 'REINICIAR MATERIALES') {
                $this->info('Cancelado. No se modificó ningún dato.');

                return self::FAILURE;
            }

            DB::transaction(function () use ($tables): void {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
                DB::table('inventory_sequences')->whereIn('name', ['movement_folio', 'movement_folio_v2', 'product_code'])->update(['next_value' => 1]);
            }, 3);
        } catch (Throwable $error) {
            $this->error('No se completó la limpieza; se preservó la integridad transaccional.');
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Materiales, consumos, movimientos y saldos reiniciados correctamente en la base LOCAL.');

        return self::SUCCESS;
    }
}
