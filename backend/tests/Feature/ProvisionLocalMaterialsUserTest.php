<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Tests\TestCase;

class ProvisionLocalMaterialsUserTest extends TestCase
{
    public function test_user_provisioning_is_blocked_outside_local_environment(): void
    {
        $this->artisan('materials:provision-local-user', [
            'email' => 'jose.barajas@ldrsolutions.com.mx',
            '--name' => 'José Barajas',
        ])
            ->expectsOutput('Bloqueado: esta cuenta de pruebas solo puede darse de alta con APP_ENV=local.')
            ->assertExitCode(Command::FAILURE);
    }
}
