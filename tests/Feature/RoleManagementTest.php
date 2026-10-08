<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_start_empty_and_empty_role_selector_has_no_options(): void
    {
        $this->assertDatabaseCount('roles', 0);

        $this->get('/personal/nuevo')
            ->assertOk()
            ->assertSee('No hay roles registrados')
            ->assertSee('disabled', false)
            ->assertDontSee('Médico')
            ->assertDontSee('Enfermero');
    }

    public function test_only_roles_saved_in_database_are_available_to_people(): void
    {
        $this->post('/roles', ['nombre' => ' Enfermería '])
            ->assertRedirect('/roles');

        $this->assertDatabaseHas('roles', ['nombre' => 'Enfermería']);

        $this->get('/personal/nuevo')
            ->assertOk()
            ->assertSee('Enfermería')
            ->assertDontSee('Médico')
            ->assertDontSee('Enfermero');

        $this->post('/personal', [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('people', ['email' => 'ana@example.com']);
    }

    public function test_role_names_reject_numbers(): void
    {
        $this->get('/roles')
            ->assertOk()
            ->assertSee('pattern="[^0-9]+"', false)
            ->assertSee('data-name-only', false);

        $this->post('/roles', ['nombre' => 'Enfermero 2'])
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('roles', 0);
    }

    public function test_deleting_an_unused_role_removes_it_from_the_selector(): void
    {
        $roleId = DB::table('roles')->insertGetId(['nombre' => 'Enfermería']);

        $this->delete("/roles/{$roleId}")
            ->assertRedirect('/roles');

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);

        $this->get('/personal/nuevo')
            ->assertOk()
            ->assertDontSee('Enfermería')
            ->assertSee('No hay roles registrados');
    }

    public function test_role_assigned_to_a_person_cannot_be_deleted(): void
    {
        $roleId = DB::table('roles')->insertGetId(['nombre' => 'Enfermería']);

        Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Enfermería',
        ]);

        $this->delete("/roles/{$roleId}")
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $roleId, 'nombre' => 'Enfermería']);
    }
}
