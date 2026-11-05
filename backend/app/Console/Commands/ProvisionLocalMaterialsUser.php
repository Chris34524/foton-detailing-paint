<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionLocalMaterialsUser extends Command
{
    protected $signature = 'materials:provision-local-user {email} {--name= : Display name}';

    protected $description = 'Create a local-only account with Materials permissions; password entered privately';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Bloqueado: esta cuenta de pruebas solo puede darse de alta con APP_ENV=local.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('La contraseña debe ingresarse de forma interactiva, no en argumentos ni en el repositorio.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El correo electrónico no es válido.');

            return self::INVALID;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error('Ya existe una cuenta con ese correo. No se modificaron credenciales ni permisos.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: 'Usuario de Materiales'));
        $password = (string) $this->secret('Contraseña inicial (mínimo 8 caracteres)');
        if (mb_strlen($password) < 8) {
            $this->error('La contraseña debe contener al menos ocho caracteres.');

            return self::INVALID;
        }
        DB::transaction(function () use ($email, $name, $password): void {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->givePermissionTo([
                Permission::MaterialsView->value,
                Permission::MaterialsConsume->value,
                Permission::MaterialsManage->value,
            ]);
        });

        $this->info('Cuenta local creada con permisos de Inventario, Movimientos y administración de Materiales.');
        $this->warn('Cambia la contraseña temporal antes de cualquier despliegue real.');

        return self::SUCCESS;
    }
}
