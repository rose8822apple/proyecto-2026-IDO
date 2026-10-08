<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Availability;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use App\Services\AuditLogger;
use App\Support\VenezuelaTerritory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 50);
            });
        }

        DB::table('roles')->delete();
    }

    public function test_forms_hide_coverage_and_operations_can_be_saved_without_it(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'Médico'],
            ['nombre' => 'Enfermero'],
            ['nombre' => 'Voluntario'],
            ['nombre' => 'Coordinador'],
            ['nombre' => 'Paramédico'],
        ]);

        Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone' => '04261234567',
            'status' => 'Disponible',
        ]);

        Person::query()->create([
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'role' => 'Enfermero',
            'phone' => '04161234567',
            'status' => 'Disponible',
        ]);

        $this->get('/personal/nuevo')
            ->assertOk()
            ->assertSee('Médico')
            ->assertSee('Enfermero')
            ->assertSee('class="input-group phone-input"', false)
            ->assertSee('<label for="phone_number">Teléfono</label>', false)
            ->assertSee('name="phone_prefix"', false)
            ->assertSee('name="phone_number"', false)
            ->assertSee('maxlength="7"', false)
            ->assertSee('data-numeric-only', false)
            ->assertSee('data-name-only', false)
            ->assertSee('<label for="cedula_number">Cédula</label>', false)
            ->assertSee('name="cedula_prefix"', false)
            ->assertSee('name="cedula_number"', false)
            ->assertSee('value="V-"', false)
            ->assertSee('value="E-"', false)
            ->assertSee('maxlength="10"', false);

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'Alto Orinoco',
            'parish' => 'Alto Orinoco',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $site = Site::query()->firstOrFail();

        $this->post('/turnos', [
            'title' => 'Triaje general',
            'site_id' => $site->id,
            'date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'coverage' => 0]);
        $this->assertDatabaseHas('shifts', ['site_id' => $site->id, 'coverage' => 0]);

        $this->get('/turnos/crear')->assertOk()->assertDontSee('Cobertura');
        $this->get('/turnos/crear')
            ->assertSee('<label for="date">Fecha de inicio</label>', false)
            ->assertSee('<label for="end_date">Fecha de fin</label>', false);
        $this->get('/sedes/nueva')->assertOk()->assertDontSee('Cobertura');
        $this->get('/turnos')->assertOk()->assertDontSee('Cobertura');
        $this->get('/sedes')->assertOk()->assertDontSee('Cobertura')->assertSee('data-status-column', false);
        $this->get('/dashboard')->assertOk()->assertDontSee('Turnos creados');
    }

    public function test_people_list_includes_cedula_search_and_status_filter_without_export_control(): void
    {
        Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'V-0123456789',
            'role' => 'Médico',
            'phone' => '04261234567',
            'status' => 'Disponible',
        ]);

        $this->get('/personal')
            ->assertOk()
            ->assertSee('data-search-cedula="0123456789"', false)
            ->assertSee('Filtrar')
            ->assertDontSee('Exportar');
    }

    public function test_module_lists_include_pagination_with_ten_rows_per_page(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            Person::query()->create([
                'name' => 'Persona '.$index,
                'email' => 'persona'.$index.'@example.com',
                'role' => 'Voluntario',
            ]);
        }

        $this->get('/personal')
            ->assertOk()
            ->assertSee('data-page-size="10"', false)
            ->assertSee('data-page-previous', false)
            ->assertSee('data-page-next', false)
            ->assertSee('data-range-start', false)
            ->assertSee('data-range-end', false);
    }

    public function test_audit_list_shows_ten_rows_per_page(): void
    {
        for ($index = 1; $index <= 11; $index++) {
            AuditLog::query()->create([
                'event' => 'created',
                'auditable_type' => Person::class,
                'auditable_id' => $index,
                'new_values' => ['name' => 'Persona '.$index],
            ]);
        }

        $this->get('/auditoria')
            ->assertOk()
            ->assertViewHas('auditLogs', fn ($auditLogs) => $auditLogs->perPage() === 10
                && $auditLogs->count() === 10
                && $auditLogs->hasMorePages());
    }

    public function test_availability_status_selector_does_not_offer_assigned(): void
    {
        $this->get('/disponibilidad/nueva')
            ->assertOk()
            ->assertDontSee('name="status"', false)
            ->assertDontSee('Descanso mínimo')
            ->assertDontSee('Asignado');
    }

    public function test_availability_person_preview_supports_people_table_without_status_column(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
        ]);

        $this->get('/disponibilidad/nueva')
            ->assertOk()
            ->assertSee('data-person-status="Sin estado"', false);
    }

    public function test_person_cannot_be_assigned_overlapping_shifts_but_can_take_a_non_overlapping_shift(): void
    {
        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
        ]);
        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);
        $firstShift = Shift::query()->create([
            'title' => 'Turno matutino',
            'site_id' => $site->id,
            'date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ]);
        $overlappingShift = Shift::query()->create([
            'title' => 'Turno intermedio',
            'site_id' => $site->id,
            'date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'start_time' => '13:00',
            'end_time' => '17:00',
            'status' => 'En curso',
        ]);
        $nonOverlappingShift = Shift::query()->create([
            'title' => 'Turno nocturno',
            'site_id' => $site->id,
            'date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'start_time' => '14:00',
            'end_time' => '20:00',
            'status' => 'En curso',
        ]);

        $this->post('/disponibilidad', [
            'person_id' => $person->id,
            'shift_id' => $firstShift->id,
        ])->assertRedirect('/disponibilidad');

        $this->from('/disponibilidad/nueva')
            ->post('/disponibilidad', [
                'person_id' => $person->id,
                'shift_id' => $overlappingShift->id,
            ])
            ->assertRedirect('/disponibilidad/nueva')
            ->assertSessionHasErrors('person_id');

        $this->post('/disponibilidad', [
            'person_id' => $person->id,
            'shift_id' => $nonOverlappingShift->id,
        ])->assertRedirect('/disponibilidad');

        $this->assertDatabaseCount('availabilities', 2);
        $this->assertDatabaseHas('availabilities', [
            'person_id' => $person->id,
            'shift_id' => $nonOverlappingShift->id,
            'status' => 'No disponible',
        ]);
    }

    public function test_shift_can_be_created_with_a_date_range_and_end_must_not_precede_start(): void
    {
        $startDate = now()->addDay()->toDateString();
        $endDate = now()->addDays(4)->toDateString();
        $earlierDate = now()->addDays(2)->toDateString();
        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        $this->post('/turnos', [
            'title' => 'Jornada extendida',
            'site_id' => $site->id,
            'date' => $startDate,
            'end_date' => $endDate,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'En curso',
        ])->assertRedirect('/turnos');

        $shift = Shift::query()->where('title', 'Jornada extendida')->firstOrFail();
        $this->assertSame($startDate, $shift->date);
        $this->assertSame($endDate, $shift->end_date);
        $this->get('/turnos/'.$shift->id.'/editar')
            ->assertOk()
            ->assertSee('id="start_time"', false)
            ->assertSee('id="end_time"', false)
            ->assertSee('type="time"', false)
            ->assertSee('value="08:00"', false)
            ->assertSee('value="16:00"', false);

        $this->get('/turnos')
            ->assertOk()
            ->assertSee($startDate.' al '.$endDate);
        $this->get('/disponibilidad/nueva')
            ->assertOk()
            ->assertSee($startDate.' al '.$endDate.' | Jornada extendida');

        $this->from('/turnos/crear')->post('/turnos', [
            'title' => 'Fechas incorrectas',
            'site_id' => $site->id,
            'date' => $earlierDate,
            'end_date' => $startDate,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseMissing('shifts', ['title' => 'Fechas incorrectas']);
    }

    public function test_shift_accepts_browser_time_format_and_optional_seconds(): void
    {
        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        $this->post('/turnos', [
            'title' => 'Turno navegador',
            'site_id' => $site->id,
            'date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('shifts', [
            'title' => 'Turno navegador',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);
    }

    public function test_shift_form_rejects_numeric_names_and_past_dates_on_create_and_update(): void
    {
        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->get('/turnos/crear')
            ->assertOk()
            ->assertSee('min="'.$today.'"', false)
            ->assertSee('pattern="[^0-9]+" data-name-only', false);

        $this->from('/turnos/crear')->post('/turnos', [
            'title' => 'Turno 5',
            'site_id' => $site->id,
            'date' => $tomorrow,
            'end_date' => $tomorrow,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertSessionHasErrors('title');

        $this->from('/turnos/crear')->post('/turnos', [
            'title' => 'Turno matutino',
            'site_id' => $site->id,
            'date' => now()->subDay()->toDateString(),
            'end_date' => $tomorrow,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertSessionHasErrors('date');

        $shift = Shift::query()->create([
            'title' => 'Turno matutino',
            'site_id' => $site->id,
            'date' => $tomorrow,
            'end_date' => $tomorrow,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ]);

        $this->put('/turnos/'.$shift->id, [
            'title' => 'Turno 6',
            'site_id' => $site->id,
            'date' => $tomorrow,
            'end_date' => $tomorrow,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertSessionHasErrors('title');

        $this->put('/turnos/'.$shift->id, [
            'title' => 'Turno matutino',
            'site_id' => $site->id,
            'date' => now()->subDay()->toDateString(),
            'end_date' => $tomorrow,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertSessionHasErrors('date');
    }

    public function test_site_name_and_municipality_reject_numbers(): void
    {
        $this->post('/sedes', [
            'name' => 'Hospital 2',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ])->assertSessionHasErrors('name');

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'San Miguel 2',
            'status' => 'Operativa',
        ])->assertSessionHasErrors('municipality');
    }

    public function test_site_form_renders_dependent_territory_selectors_and_catalog(): void
    {
        $this->get('/sedes/nueva')
            ->assertOk()
            ->assertSee('data-territory-catalog', false)
            ->assertSee('data-territory-state', false)
            ->assertSee('data-territory-municipality', false)
            ->assertSee('data-territory-parish', false)
            ->assertSee('disabled', false)
            ->assertSee('Amazonas');

        $catalog = VenezuelaTerritory::catalog();
        $this->assertCount(25, $catalog);
        $this->assertSame(['Alto Orinoco', 'Huachamacare Acanaña', 'Marawaka Toky Shamanaña', 'Mavaka Mavaka', 'Sierra Parima Parimabé'], $catalog['Amazonas']['Alto Orinoco']);
        $this->assertSame([], $catalog['Portuguesa']['Agua Blanca']);
    }

    public function test_site_location_must_match_state_municipality_and_parish(): void
    {
        $this->from('/sedes/nueva')->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'Anaco',
            'parish' => 'Anaco',
            'status' => 'Operativa',
        ])->assertSessionHasErrors('municipality');

        $this->from('/sedes/nueva')->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'Alto Orinoco',
            'parish' => 'Anaco',
            'status' => 'Operativa',
        ])->assertSessionHasErrors('parish');

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'Alto Orinoco',
            'parish' => 'Huachamacare Acanaña',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', [
            'name' => 'Hospital Central',
            'state' => 'Amazonas',
            'municipality' => 'Alto Orinoco',
            'parish' => 'Huachamacare Acanaña',
        ]);

        $this->post('/sedes', [
            'name' => 'Centro comunitario',
            'type' => 'Otro',
            'state' => 'Distrito Capital',
            'municipality' => 'Libertador',
            'parish' => '23 de enero',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', [
            'name' => 'Centro comunitario',
            'state' => 'Distrito Capital',
            'municipality' => 'Libertador',
            'parish' => '23 de enero',
        ]);
    }

    public function test_site_can_be_saved_in_a_municipality_without_catalogued_parishes(): void
    {
        $this->post('/sedes', [
            'name' => 'Centro de atención',
            'type' => 'Otro',
            'state' => 'Portuguesa',
            'municipality' => 'Agua Blanca',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', [
            'name' => 'Centro de atención',
            'state' => 'Portuguesa',
            'municipality' => 'Agua Blanca',
            'parish' => null,
        ]);
    }

    public function test_legacy_site_can_be_updated_without_changing_its_unknown_location(): void
    {
        $site = Site::query()->create([
            'name' => 'Hospital anterior',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        $this->get('/sedes/'.$site->id.'/editar')
            ->assertOk()
            ->assertSee('value="San Miguel"', false)
            ->assertSee('>San Miguel</option>', false);

        $this->put('/sedes/'.$site->id, [
            'name' => 'Hospital anterior actualizado',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'name' => 'Hospital anterior actualizado',
            'municipality' => 'San Miguel',
            'state' => null,
            'parish' => null,
        ]);
    }

    public function test_audit_view_has_an_explicit_filter_button(): void
    {
        $this->get('/auditoria')
            ->assertOk()
            ->assertSee('data-audit-filters', false)
            ->assertSee('Filtrar</button>', false);
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
            'cedula_prefix' => 'V-',
            'cedula_number' => '0123456789',
            'role' => 'Médico',
            'phone_prefix' => '0426',
            'phone_number' => '7654321',
            'status' => 'Disponible',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('people', ['email' => 'ana@example.com', 'cedula' => 'V-0123456789', 'phone' => '04267654321']);

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'Alto Orinoco',
            'parish' => 'Alto Orinoco',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', ['name' => 'Hospital Central', 'coverage' => 0]);

        $this->post('/turnos', [
            'title' => 'Triaje general',
            'site_id' => 1,
            'date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('shifts', ['title' => 'Triaje general', 'coverage' => 0]);

        $this->post('/disponibilidad', [
            'person_id' => 1,
            'shift_id' => 1,
            'date' => '2026-09-30',
            'status' => 'Disponible',
        ])->assertRedirect('/disponibilidad');

        $this->assertDatabaseHas('availabilities', ['person_id' => 1, 'shift_id' => 1]);
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Person::class]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Site::class]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Shift::class]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Availability::class]);
    }

    public function test_availability_uses_only_a_created_shifts_schedule(): void
    {
        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'E-1234567890',
            'role' => 'Médico',
            'status' => 'Disponible',
        ]);

        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        $shift = Shift::query()->create([
            'title' => 'Turno matutino',
            'site_id' => $site->id,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ]);

        $this->get('/disponibilidad/nueva')
            ->assertOk()
            ->assertSee('data-person-select', false)
            ->assertSee('data-person-name="Ana García"', false)
            ->assertSee('data-person-cedula="E-1234567890"', false)
            ->assertSee('data-person-phone="No registrado"', false)
            ->assertSee('data-person-preview', false)
            ->assertSee('Verifica la persona seleccionada')
            ->assertSee('name="shift_id"', false)
            ->assertSee('Turno matutino | Hospital Central | 08:00 - 14:00')
            ->assertDontSee('name="start_time"', false)
            ->assertDontSee('name="end_time"', false);

        $this->post('/disponibilidad', [
            'person_id' => $person->id,
            'shift_id' => $shift->id,
            'date' => '2026-10-03',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => 'Disponible',
        ])->assertRedirect('/disponibilidad');

        $availability = Availability::query()->firstOrFail();
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'shift_id' => $shift->id,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'status' => 'No disponible',
        ]);

        $this->get('/disponibilidad/'.$availability->id.'/editar')
            ->assertOk()
            ->assertSee('value="'.$person->id.'"', false)
            ->assertSee('selected', false)
            ->assertSee('data-person-email="ana@example.com"', false)
            ->assertSee('value="'.$shift->id.'"', false)
            ->assertDontSee('name="start_time"', false)
            ->assertDontSee('name="end_time"', false);
    }

    public function test_legacy_operational_tables_merge_without_losing_fields_or_relations(): void
    {
        DB::table('roles')->insert(['id' => 1, 'nombre' => 'Médico']);

        Schema::create('municipios', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('nombre', 100);
        });
        Schema::create('personal', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('nombre_completo', 150);
            $table->string('correo', 150);
            $table->integer('rol_id');
            $table->string('cargo_coordinacion', 100)->nullable();
            $table->integer('descanso_minimo_horas')->nullable();
            $table->string('estado', 30)->nullable();
        });
        Schema::create('sedes', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('codigo', 20)->nullable();
            $table->string('nombre', 150);
            $table->string('tipo', 50);
            $table->integer('municipio_id');
            $table->string('estado', 30)->nullable();
        });
        Schema::create('turnos', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('titulo', 100);
            $table->string('descripcion_equipo', 150)->nullable();
            $table->integer('sede_id');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('cupo_requerido');
            $table->string('estado', 30)->nullable();
        });
        Schema::create('disponibilidad_declarada', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('personal_id');
            $table->date('fecha');
            $table->string('estado_disponibilidad', 40);
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
        });
        Schema::create('asignaciones_turno', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('turno_id');
            $table->integer('personal_id');
            $table->timestamp('fecha_asiguracion');
        });

        DB::table('municipios')->insert(['id' => 7, 'nombre' => 'San Miguel']);
        DB::table('personal')->insert([
            'id' => 15,
            'nombre_completo' => 'Ana López',
            'correo' => 'ana.legacy@example.com',
            'rol_id' => 1,
            'cargo_coordinacion' => 'Coordinación general',
            'descanso_minimo_horas' => 10,
            'estado' => 'Inactivo',
        ]);
        DB::table('sedes')->insert([
            'id' => 25,
            'codigo' => 'HSG-001',
            'nombre' => 'Hospital San Gabriel',
            'tipo' => 'Otro',
            'municipio_id' => 7,
            'estado' => 'En revisión',
        ]);
        DB::table('turnos')->insert([
            'id' => 35,
            'titulo' => 'Triaje general',
            'descripcion_equipo' => 'Médicos y enfermería',
            'sede_id' => 25,
            'fecha' => '2026-10-01',
            'hora_inicio' => '08:00',
            'hora_fin' => '16:00',
            'cupo_requerido' => 5,
            'estado' => 'Vacantes',
        ]);
        DB::table('disponibilidad_declarada')->insert([
            ['id' => 45, 'personal_id' => 15, 'fecha' => '2026-10-01', 'estado_disponibilidad' => 'Disponible', 'hora_inicio' => null, 'hora_fin' => null],
            ['id' => 46, 'personal_id' => 15, 'fecha' => '2026-10-02', 'estado_disponibilidad' => 'Horario específico', 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
        ]);
        DB::table('asignaciones_turno')->insert([
            'id' => 55,
            'turno_id' => 35,
            'personal_id' => 15,
            'fecha_asiguracion' => '2026-09-29 20:14:47',
        ]);

        $migration = require database_path('migrations/2026_10_03_000006_consolidate_legacy_operational_tables.php');
        $migration->up();

        $person = Person::query()->where('email', 'ana.legacy@example.com')->firstOrFail();
        $site = Site::query()->where('code', 'HSG-001')->firstOrFail();
        $shift = Shift::query()->where('title', 'Triaje general')->firstOrFail();

        $this->assertSame('Coordinación general', $person->coordination_title);
        $this->assertSame(10, $person->minimum_rest_hours);
        $this->assertSame('Inactivo', $person->status);
        $this->assertSame('San Miguel', $site->municipality);
        $this->assertSame('Otro', $site->type);
        $this->assertSame('2026-10-01', $shift->date);
        $this->assertSame('Médicos y enfermería', $shift->team_description);
        $this->assertSame(5, $shift->required_people);
        $this->assertSame('Vacantes', $shift->status);

        $this->assertDatabaseCount('availabilities', 2);
        $this->assertDatabaseHas('availabilities', [
            'person_id' => $person->id,
            'date' => '2026-10-01',
            'status' => 'Disponible',
            'start_time' => null,
            'end_time' => null,
        ]);
        $this->assertDatabaseHas('availabilities', [
            'person_id' => $person->id,
            'date' => '2026-10-02',
            'status' => 'Horario específico',
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
        $this->assertDatabaseHas('shift_assignments', [
            'shift_id' => $shift->id,
            'person_id' => $person->id,
            'assigned_at' => '2026-09-29 20:14:47',
        ]);

        foreach (['personal', 'sedes', 'turnos', 'disponibilidad_declarada', 'asignaciones_turno', 'municipios'] as $legacyTable) {
            $this->assertFalse(Schema::hasTable($legacyTable), "Legacy table {$legacyTable} should be retired after merge.");
        }
    }

    public function test_creating_a_person_logs_a_structured_event_to_stderr(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);

        $logger = \Mockery::mock();
        $logger->shouldReceive('info')
            ->once()
            ->with('Evento de aplicación: person.created', \Mockery::on(fn (array $context) => $context['entity'] === 'Person'
                && $context['id'] === 1
                && $context['fields'] === ['name', 'email', 'role', 'status', 'cedula', 'phone']
            ));

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        $this->post('/personal', [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula_prefix' => 'V-',
            'cedula_number' => '1234567890',
            'role' => 'Médico',
            'phone_prefix' => '0414',
            'phone_number' => '1234567',
            'status' => 'Disponible',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('people', ['email' => 'ana@example.com', 'cedula' => 'V-1234567890', 'phone' => '04141234567']);
    }

    public function test_cedula_rejects_prefixes_other_than_v_or_e(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);

        $this->post('/personal', [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula_prefix' => 'X-',
            'cedula_number' => '1234567890',
            'role' => 'Médico',
            'status' => 'Disponible',
        ])->assertSessionHasErrors('cedula_prefix');

        $this->assertDatabaseMissing('people', ['email' => 'ana@example.com']);

        $this->post('/personal', [
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'cedula_prefix' => 'E-',
            'cedula_number' => '12345678901',
            'role' => 'Médico',
            'status' => 'Disponible',
        ])->assertSessionHasErrors('cedula_number');

        $this->assertDatabaseMissing('people', ['email' => 'luis@example.com']);
    }

    public function test_duplicate_cedula_shows_user_exists_alert_and_does_not_create_person(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);
        Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'V-1234567890',
            'role' => 'Médico',
        ]);

        $this->from('/personal/nuevo')
            ->followingRedirects()
            ->post('/personal', [
                'name' => 'Luis Pérez',
                'email' => 'luis@example.com',
                'cedula_prefix' => 'V-',
                'cedula_number' => '1234567890',
                'role' => 'Médico',
            ])
            ->assertOk()
            ->assertSee('Usuario existente: la cédula ya está registrada.');

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseMissing('people', ['email' => 'luis@example.com']);
    }

    public function test_person_can_be_updated_without_changing_to_a_duplicate_cedula(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);
        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'V-1234567890',
            'role' => 'Médico',
        ]);

        $this->put('/personal/'.$person->id, [
            'name' => 'Ana García Ruiz',
            'email' => 'ana@example.com',
            'cedula_prefix' => 'V-',
            'cedula_number' => '1234567890',
            'role' => 'Médico',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'Ana García Ruiz',
            'cedula' => 'V-1234567890',
        ]);
    }

    public function test_audit_logger_stores_snapshots_without_sensitive_person_fields(): void
    {
        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'V-1234567890',
            'role' => 'Médico',
            'phone' => '04261234567',
            'status' => 'Disponible',
        ]);

        $entry = app(AuditLogger::class)->record('person.created', $person);

        $this->assertSame('created', $entry->event);
        $this->assertSame(Person::class, $entry->auditable_type);
        $this->assertSame([
            'name' => 'Ana García',
            'role' => 'Médico',
            'status' => 'Disponible',
        ], $entry->new_values);
        $this->assertArrayNotHasKey('cedula', $entry->new_values);
        $this->assertNull($entry->old_values);
        $this->assertNull($entry->actor_id);
        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id, 'event' => 'created']);
    }

    public function test_audit_history_records_changes_filters_results_and_is_read_only(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);

        $this->post('/personal', [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone_prefix' => '0426',
            'phone_number' => '1234567',
            'status' => 'Disponible',
        ])->assertRedirect('/personal');

        $person = Person::query()->firstOrFail();

        $this->put('/personal/'.$person->id, [
            'name' => 'Ana García Ruiz',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone_prefix' => '0426',
            'phone_number' => '1234567',
            'status' => 'En turno',
        ])->assertRedirect('/personal');

        $update = AuditLog::query()->where('event', 'updated')->firstOrFail();
        $this->assertSame(['name', 'status'], array_keys($update->old_values));
        $this->assertSame('Ana García', $update->old_values['name']);
        $this->assertSame('Ana García Ruiz', $update->new_values['name']);
        $this->assertSame('Disponible', $update->old_values['status']);
        $this->assertSame('En turno', $update->new_values['status']);

        $today = now()->toDateString();
        $this->get('/auditoria?entity=people&event=updated&q=En%20turno&date_from='.$today.'&date_to='.$today)
            ->assertOk()
            ->assertSee('Historial de cambios')
            ->assertSee('Edición')
            ->assertSee('Ana García Ruiz')
            ->assertSee('En turno')
            ->assertSee('Sin identificar');

        $this->delete('/personal/'.$person->id)->assertRedirect('/personal');
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'deleted',
            'auditable_type' => Person::class,
            'auditable_id' => $person->id,
        ]);

        $this->post('/auditoria')->assertStatus(405);
        $this->put('/auditoria')->assertStatus(405);
        $this->delete('/auditoria')->assertStatus(405);
    }

    public function test_audit_history_includes_records_deleted_in_cascades(): void
    {
        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'E-1234567890',
            'role' => 'Médico',
            'status' => 'Disponible',
        ]);

        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        $shift = Shift::query()->create([
            'title' => 'Turno matutino',
            'site_id' => $site->id,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ]);

        Availability::query()->create([
            'person_id' => $person->id,
            'shift_id' => $shift->id,
            'date' => '2026-10-03',
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'status' => 'Disponible',
        ]);

        $this->delete('/sedes/'.$site->id)->assertRedirect('/sedes');
        $this->delete('/personal/'.$person->id)->assertRedirect('/personal');

        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => Site::class]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => Shift::class]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => Person::class]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => Availability::class]);
    }

    public function test_notification_menu_shows_recent_additions_from_each_module(): void
    {
        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'status' => 'Disponible',
        ]);

        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        Shift::query()->create([
            'title' => 'Turno de triaje',
            'site_id' => $site->id,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ]);

        Availability::query()->create([
            'person_id' => $person->id,
            'date' => '2026-10-03',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Disponible',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-notification-toggle', false)
            ->assertSee('Nuevo turno')
            ->assertSee('Turno de triaje')
            ->assertSee('Personal añadido')
            ->assertSee('Ana García')
            ->assertSee('Sede añadida')
            ->assertSee('Hospital Central')
            ->assertSee('Nueva disponibilidad');
    }

    public function test_notification_count_decreases_when_viewed_and_increases_for_new_additions(): void
    {
        $this->withSession(['notifications.seen_through' => [
            'shifts' => 0,
            'people' => 0,
            'sites' => 0,
            'availability' => 0,
        ]]);

        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'status' => 'Disponible',
        ]);

        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        Shift::query()->create([
            'title' => 'Turno de triaje',
            'site_id' => $site->id,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'status' => 'En curso',
        ]);

        Availability::query()->create([
            'person_id' => $person->id,
            'date' => '2026-10-03',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Disponible',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-notification-count', false)
            ->assertSee('data-notification-total>4', false);

        $this->post('/notificaciones/vistas')->assertNoContent();

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-notification-count', false)
            ->assertSee('data-notification-total>0', false);

        Shift::query()->create([
            'title' => 'Turno vespertino',
            'site_id' => $site->id,
            'start_time' => '14:00',
            'end_time' => '20:00',
            'status' => 'En curso',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-notification-count', false)
            ->assertSee('data-notification-total>1', false);
    }

    public function test_dashboard_shows_site_status_counts_without_staff_on_shift_sections(): void
    {
        Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'status' => 'En turno',
        ]);

        Person::query()->create([
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
            'role' => 'Enfermero',
            'status' => 'Disponible',
        ]);

        Person::query()->create([
            'name' => 'Marta Ruiz',
            'email' => 'marta@example.com',
            'role' => 'Voluntario',
            'status' => 'No disponible',
        ]);

        Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        Site::query()->create([
            'name' => 'Refugio Norte',
            'type' => 'Refugio',
            'municipality' => 'San Miguel',
            'status' => 'En revisión',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Personal en turno')
            ->assertDontSee('>En turno</span>', false)
            ->assertDontSee('stats-status-grid-personnel', false)
            ->assertSee('Estadísticas operativas')
            ->assertSee('data-stat="operational-sites">1</strong>', false)
            ->assertSee('data-stat="sites-in-review">1</strong>', false)
            ->assertSee('data-live-clock', false)
            ->assertSee('bi-clock', false)
            ->assertSee('Instituto universitario jesus obrero IUJO CARACAS, Grupo 2 Investigacion de operaciones AC')
            ->assertSee('aria-label="Instagram"', false)
            ->assertSee('aria-label="Facebook"', false)
            ->assertSee('aria-label="X"', false)
            ->assertSee('href="https://github.com/rose8822apple/proyecto-2026-IDO"', false)
            ->assertDontSee('Cobertura promedio');
    }

    public function test_person_phone_requires_an_allowed_prefix_and_exactly_seven_digits(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);

        $this->post('/personal', [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone_prefix' => '0413',
            'phone_number' => '12345678',
            'status' => 'Disponible',
        ])->assertSessionHasErrors(['phone_prefix', 'phone_number']);

        $this->assertDatabaseMissing('people', ['email' => 'ana@example.com']);
    }

    public function test_person_name_and_phone_reject_invalid_characters_on_create_and_update(): void
    {
        DB::table('roles')->insert(['nombre' => 'Médico']);

        $this->post('/personal', [
            'name' => 'Ana2 García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone_prefix' => '0414',
            'phone_number' => '123a567',
            'status' => 'Disponible',
        ])->assertSessionHasErrors(['name', 'phone_number']);

        $this->assertDatabaseMissing('people', ['email' => 'ana@example.com']);

        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone' => '04141234567',
            'status' => 'Disponible',
        ]);

        $this->put('/personal/'.$person->id, [
            'name' => 'Ana2 García',
            'email' => 'ana@example.com',
            'role' => 'Médico',
            'phone_prefix' => '0414',
            'phone_number' => '123a567',
            'status' => 'Disponible',
        ])->assertSessionHasErrors(['name', 'phone_number']);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'Ana García',
            'phone' => '04141234567',
        ]);
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

        $person = Person::query()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'cedula' => 'E-1234567890',
            'role' => 'Médico',
            'phone' => '04261234567',
            'status' => 'Disponible',
        ]);

        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'coverage' => 95,
            'status' => 'Operativa',
        ]);

        $shift = Shift::query()->create([
            'title' => 'Triaje general',
            'site_id' => $site->id,
            'start_time' => '08:00',
            'end_time' => '14:00',
            'coverage' => 100,
            'status' => 'En curso',
        ]);

        $availability = Availability::query()->create([
            'person_id' => $person->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-30',
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'status' => 'Disponible',
        ]);

        $this->get('/personal/'.$person->id.'/editar')
            ->assertOk()
            ->assertSee('value="E-" selected', false)
            ->assertSee('value="1234567890"', false)
            ->assertSee('value="0426" selected', false)
            ->assertSee('name="phone_number"', false)
            ->assertSee('value="1234567"', false);

        $this->put('/personal/'.$person->id, [
            'name' => 'Ana García Ruiz',
            'email' => 'ana.nueva@example.com',
            'cedula_prefix' => 'V-',
            'cedula_number' => '9876543210',
            'role' => 'Médico',
            'phone_prefix' => '0424',
            'phone_number' => '7654321',
            'status' => 'En turno',
        ])->assertRedirect('/personal');

        $this->assertDatabaseHas('people', ['id' => $person->id, 'name' => 'Ana García Ruiz', 'cedula' => 'V-9876543210', 'phone' => '04247654321']);

        $this->put('/sedes/'.$site->id, [
            'name' => 'Hospital Central Nuevo',
            'type' => 'Hospital',
            'state' => 'Amazonas',
            'municipality' => 'Alto Orinoco',
            'parish' => 'Alto Orinoco',
            'status' => 'En revisión',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Hospital Central Nuevo', 'coverage' => 95]);

        $this->put('/turnos/'.$shift->id, [
            'title' => 'Turno actualizado',
            'site_id' => $site->id,
            'date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '15:00:00',
            'status' => 'Completo',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'title' => 'Turno actualizado', 'coverage' => 100, 'date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]);

        $this->put('/disponibilidad/'.$availability->id, [
            'person_id' => $person->id,
            'shift_id' => $shift->id,
            'date' => '2026-10-01',
            'status' => 'Asignado',
        ])->assertRedirect('/disponibilidad');

        $this->assertDatabaseHas('availabilities', ['id' => $availability->id, 'shift_id' => $shift->id, 'start_time' => '09:00:00', 'end_time' => '15:00:00', 'status' => 'No disponible']);

        $this->delete('/disponibilidad/'.$availability->id)->assertRedirect('/disponibilidad');
        $this->delete('/turnos/'.$shift->id)->assertRedirect('/turnos');
        $this->delete('/sedes/'.$site->id)->assertRedirect('/sedes');
        $this->delete('/personal/'.$person->id)->assertRedirect('/personal');

        $this->assertDatabaseMissing('availabilities', ['id' => $availability->id]);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('people', ['id' => $person->id]);
    }
}
