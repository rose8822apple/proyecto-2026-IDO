<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class OperationFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
        });
    }

    public function test_form_roles_are_loaded_from_database_and_coverage_blocks_negative_values(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'Médico'],
            ['nombre' => 'Enfermero'],
            ['nombre' => 'Voluntario'],
            ['nombre' => 'Coordinador'],
            ['nombre' => 'Paramédico'],
        ]);

        \App\Models\Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone' => '777123456',
            'status' => 'Disponible',
        ]);

        \App\Models\Person::query()->create([
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'role' => 'Enfermero',
            'phone' => '555987654',
            'status' => 'Disponible',
        ]);

        $this->get('/personal/nuevo')
            ->assertOk()
            ->assertSee('Médico')
            ->assertSee('Enfermero');

        $this->post('/turnos', [
            'title' => 'Triaje general',
            'site_id' => 1,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'coverage' => -10,
            'status' => 'En curso',
        ])->assertSessionHasErrors('coverage');

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'coverage' => -25,
            'status' => 'Operativa',
        ])->assertSessionHasErrors('coverage');
    }

    public function test_operations_can_store_records_in_database(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'Médico'],
            ['nombre' => 'Enfermero'],
            ['nombre' => 'Voluntario'],
            ['nombre' => 'Coordinador'],
            ['nombre' => 'Paramédico'],
        ]);

        $this->post('/personal', [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone' => '777123456',
            'status' => 'Disponible',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('people', ['email' => 'ana@example.com']);

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'coverage' => 95,
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', ['name' => 'Hospital Central']);

        $this->post('/turnos', [
            'title' => 'Triaje general',
            'site_id' => 1,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'coverage' => 100,
            'status' => 'En curso',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('shifts', ['title' => 'Triaje general']);

        $this->post('/disponibilidad', [
            'person_id' => 1,
            'date' => '2026-09-30',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Disponible',
        ])->assertRedirect('/disponibilidad');

        $this->assertDatabaseHas('availabilities', ['person_id' => 1]);
    }

    public function test_operations_can_be_updated_and_deleted(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'Médico'],
            ['nombre' => 'Enfermero'],
            ['nombre' => 'Voluntario'],
            ['nombre' => 'Coordinador'],
            ['nombre' => 'Paramédico'],
        ]);

        $person = \App\Models\Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone' => '777123456',
            'status' => 'Disponible',
        ]);

        $site = \App\Models\Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'coverage' => 95,
            'status' => 'Operativa',
        ]);

        $shift = \App\Models\Shift::query()->create([
            'title' => 'Triaje general',
            'site_id' => $site->id,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'coverage' => 100,
            'status' => 'En curso',
        ]);

        $availability = \App\Models\Availability::query()->create([
            'person_id' => $person->id,
            'date' => '2026-09-30',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Disponible',
        ]);

        $this->put('/personal/' . $person->id, [
            'name' => 'Ana García Ruiz',
            'email' => 'ana.nueva@example.com',
            'role' => 'Médico',
            'phone' => '777765432',
            'status' => 'En turno',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('people', ['id' => $person->id, 'name' => 'Ana García Ruiz']);

        $this->put('/sedes/' . $site->id, [
            'name' => 'Hospital Central Nuevo',
            'type' => 'Hospital',
            'municipality' => 'San Miguel del Valle',
            'coverage' => 90,
            'status' => 'En revisión',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Hospital Central Nuevo']);

        $this->put('/turnos/' . $shift->id, [
            'title' => 'Turno actualizado',
            'site_id' => $site->id,
            'start_time' => '09:00',
            'end_time' => '15:00',
            'coverage' => 80,
            'status' => 'Completo',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'title' => 'Turno actualizado']);

        $this->put('/disponibilidad/' . $availability->id, [
            'person_id' => $person->id,
            'date' => '2026-10-01',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => 'Asignado',
        ])->assertRedirect('/disponibilidad');

        $this->assertDatabaseHas('availabilities', ['id' => $availability->id, 'status' => 'Asignado']);

        $this->delete('/disponibilidad/' . $availability->id)->assertRedirect('/disponibilidad');
        $this->delete('/turnos/' . $shift->id)->assertRedirect('/turnos');
        $this->delete('/sedes/' . $site->id)->assertRedirect('/sedes');
        $this->delete('/personal/' . $person->id)->assertRedirect('/personal');

        $this->assertDatabaseMissing('availabilities', ['id' => $availability->id]);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('people', ['id' => $person->id]);
    }
}
