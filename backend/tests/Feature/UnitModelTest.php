<?php

namespace Tests\Feature;

use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnitModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vin_is_canonicalized_and_status_is_cast_to_the_default_enum(): void
    {
        $unit = Unit::factory()->create(['vin' => ' lvbv3jbb7ra123456 ']);
        $this->assertSame('LVBV3JBB7RA123456', $unit->fresh()->vin);
        $this->assertSame(UnitStatus::Registered, $unit->fresh()->status);
    }

    public function test_vin_cannot_be_changed_through_the_model(): void
    {
        $unit = Unit::factory()->create();
        $vin = $unit->vin;
        try {
            $unit->update(['vin' => 'LVBV3JBB7RA123456']);
            $this->fail('VIN mutation should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vin', $exception->errors());
        }
        $this->assertSame($vin, $unit->fresh()->vin);
    }

    public function test_invalid_vin_is_rejected_on_direct_model_writes(): void
    {
        $this->expectException(ValidationException::class);
        Unit::factory()->create(['vin' => 'LVBV3JBB7RA12345Q']);
    }

    public function test_database_rejects_duplicate_vins_even_when_eloquent_is_bypassed(): void
    {
        $unit = Unit::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('units')->insert(['vin' => $unit->vin, 'status' => UnitStatus::Registered->value]);
    }

    public function test_removing_a_registering_user_preserves_the_physical_unit(): void
    {
        $creator = User::factory()->create();
        $unit = Unit::factory()->make();
        $unit->registeredBy()->associate($creator);
        $unit->save();
        $creator->delete();
        $this->assertModelExists($unit);
        $this->assertNull($unit->fresh()->created_by);
        $this->assertNull($unit->fresh()->registeredBy);
    }
}
