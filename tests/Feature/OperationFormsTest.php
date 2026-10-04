<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Availability;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use App\Services\AuditLogger;
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

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
        });
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
            ->assertSee('<label for="cedula_number">Cédula</label>', false)
            ->assertSee('name="cedula_prefix"', false)
            ->assertSee('name="cedula_number"', false)
            ->assertSee('value="V-"', false)
            ->assertSee('value="E-"', false)
            ->assertSee('maxlength="10"', false);

        $this->post('/sedes', [
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $site = Site::query()->firstOrFail();

        $this->post('/turnos', [
            'title' => 'Triaje general',
            'site_id' => $site->id,
            'date' => '2026-10-01',
            'end_date' => '2026-10-01',
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

    public function test_availability_status_selector_does_not_offer_assigned(): void
    {
        $this->get('/disponibilidad/nueva')
            ->assertOk()
            ->assertSee('Disponible')
            ->assertSee('No disponible')
            ->assertSee('Horario específico')
            ->assertDontSee('Asignado');
    }

    public function test_shift_can_be_created_with_a_date_range_and_end_must_not_precede_start(): void
    {
        $site = Site::query()->create([
            'name' => 'Hospital Central',
            'type' => 'Hospital',
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ]);

        $this->post('/turnos', [
            'title' => 'Jornada extendida',
            'site_id' => $site->id,
            'date' => '2026-10-05',
            'end_date' => '2026-10-08',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertRedirect('/turnos');

        $shift = Shift::query()->where('title', 'Jornada extendida')->firstOrFail();
        $this->assertSame('2026-10-05', $shift->date);
        $this->assertSame('2026-10-08', $shift->end_date);

        $this->get('/turnos')
            ->assertOk()
            ->assertSee('2026-10-05 al 2026-10-08');
        $this->get('/disponibilidad/nueva')
            ->assertOk()
            ->assertSee('2026-10-05 al 2026-10-08 | Jornada extendida');

        $this->from('/turnos/crear')->post('/turnos', [
            'title' => 'Fechas incorrectas',
            'site_id' => $site->id,
            'date' => '2026-10-08',
            'end_date' => '2026-10-05',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'En curso',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseMissing('shifts', ['title' => 'Fechas incorrectas']);
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
            'municipality' => 'San Miguel',
            'status' => 'Operativa',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', ['name' => 'Hospital Central', 'coverage' => 0]);

        $this->post('/turnos', [
            'title' => 'Triaje general',
            'site_id' => 1,
            'date' => '2026-10-01',
            'end_date' => '2026-10-01',
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
        ]);

        $this->get('/disponibilidad/'.$availability->id.'/editar')
            ->assertOk()
            ->assertSee('value="'.$shift->id.'" selected', false)
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

    public function test_dashboard_shows_staff_on_shift_and_site_status_counts(): void
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
            ->assertSee('Personal en turno')
            ->assertSee('data-stat="people-on-shift">1</strong>', false)
            ->assertSee('Estadísticas operativas')
            ->assertSee('data-stat="operational-people">1</strong>', false)
            ->assertSee('data-stat="unavailable-people">1</strong>', false)
            ->assertSee('data-stat="operational-sites">1</strong>', false)
            ->assertSee('data-stat="sites-in-review">1</strong>', false)
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
            'municipality' => 'San Miguel del Valle',
            'status' => 'En revisión',
        ])->assertRedirect('/sedes');

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Hospital Central Nuevo', 'coverage' => 95]);

        $this->put('/turnos/'.$shift->id, [
            'title' => 'Turno actualizado',
            'site_id' => $site->id,
            'date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'start_time' => '09:00',
            'end_time' => '15:00',
            'status' => 'Completo',
        ])->assertRedirect('/turnos');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'title' => 'Turno actualizado', 'coverage' => 100, 'date' => '2026-10-01', 'end_date' => '2026-10-02']);

        $this->put('/disponibilidad/'.$availability->id, [
            'person_id' => $person->id,
            'shift_id' => $shift->id,
            'date' => '2026-10-01',
            'status' => 'Asignado',
        ])->assertRedirect('/disponibilidad');

        $this->assertDatabaseHas('availabilities', ['id' => $availability->id, 'shift_id' => $shift->id, 'start_time' => '09:00', 'end_time' => '15:00', 'status' => 'Asignado']);

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
