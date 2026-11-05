<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Tests\TestCase;

class ResetLocalMaterialsTest extends TestCase
{
    public function test_reset_command_refuses_non_local_environments(): void
    {
        $this->artisan('materials:reset-local', ['--database' => 'any_database'])
            ->expectsOutput('Bloqueado: solo está permitido con APP_ENV=local.')
            ->assertExitCode(Command::FAILURE);
    }
}
