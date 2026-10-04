<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandCanonicalSchema();
        $this->assertLegacyDataCanBeMapped();

        DB::transaction(function (): void {
            $personIds = $this->mergePeople();
            $siteIds = $this->mergeSites();
            $shiftIds = $this->mergeShifts($siteIds);
            $availabilityCount = $this->mergeAvailabilities($personIds);
            $assignmentCount = $this->mergeAssignments($personIds, $shiftIds);

            $this->assertMergedRows($personIds, $siteIds, $shiftIds, $availabilityCount, $assignmentCount);
        });

        $this->dropLegacyTables();
    }

    public function down(): void
    {
        throw new RuntimeException('This data consolidation is irreversible; restore the pre-consolidation database backup instead.');
    }

    private function expandCanonicalSchema(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            if (! Schema::hasColumn('people', 'coordination_title')) {
                $table->string('coordination_title', 100)->nullable();
            }
            if (! Schema::hasColumn('people', 'minimum_rest_hours')) {
                $table->integer('minimum_rest_hours')->nullable();
            }
        });

        Schema::table('sites', function (Blueprint $table): void {
            if (! Schema::hasColumn('sites', 'code')) {
                $table->string('code', 20)->nullable();
            }
        });

        Schema::table('shifts', function (Blueprint $table): void {
            if (! Schema::hasColumn('shifts', 'date')) {
                $table->date('date')->nullable();
            }
            if (! Schema::hasColumn('shifts', 'team_description')) {
                $table->string('team_description', 150)->nullable();
            }
            if (! Schema::hasColumn('shifts', 'required_people')) {
                $table->integer('required_people')->nullable();
            }
        });

        Schema::table('availabilities', function (Blueprint $table): void {
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });

        if (! Schema::hasTable('shift_assignments')) {
            Schema::create('shift_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
                $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
                $table->timestamp('assigned_at');
                $table->index(['shift_id', 'person_id']);
            });
        }
    }

    private function assertLegacyDataCanBeMapped(): void
    {
        if (Schema::hasTable('personal') && DB::table('personal')->exists()) {
            if (! Schema::hasTable('roles')) {
                throw new RuntimeException('Cannot merge personal: the roles table is missing.');
            }

            $emails = DB::table('personal')->pluck('correo')->map(fn ($email) => mb_strtolower(trim((string) $email)));
            if ($emails->count() !== $emails->unique()->count()) {
                throw new RuntimeException('Cannot merge personal: duplicate email addresses need manual review.');
            }

            $currentEmails = DB::table('people')->pluck('email')->map(fn ($email) => mb_strtolower(trim((string) $email)))->all();
            if ($emails->intersect($currentEmails)->isNotEmpty()) {
                throw new RuntimeException('Cannot merge personal: an email already exists in people.');
            }

            if (DB::table('personal')->leftJoin('roles', 'personal.rol_id', '=', 'roles.id')->whereNull('roles.id')->exists()) {
                throw new RuntimeException('Cannot merge personal: at least one role reference is missing.');
            }
        }

        if (Schema::hasTable('sedes') && DB::table('sedes')->exists()) {
            if (! Schema::hasTable('municipios') || DB::table('sedes')->leftJoin('municipios', 'sedes.municipio_id', '=', 'municipios.id')->whereNull('municipios.id')->exists()) {
                throw new RuntimeException('Cannot merge sedes: at least one municipality reference is missing.');
            }

            if (Schema::hasTable('municipios') && DB::table('municipios')->leftJoin('sedes', 'municipios.id', '=', 'sedes.municipio_id')->whereNull('sedes.id')->exists()) {
                throw new RuntimeException('Cannot merge municipios: an unused municipality would be lost.');
            }
        }

        if (Schema::hasTable('turnos') && DB::table('turnos')->leftJoin('sedes', 'turnos.sede_id', '=', 'sedes.id')->whereNull('sedes.id')->exists()) {
            throw new RuntimeException('Cannot merge turnos: at least one site reference is missing.');
        }

        if (Schema::hasTable('disponibilidad_declarada') && DB::table('disponibilidad_declarada')->leftJoin('personal', 'disponibilidad_declarada.personal_id', '=', 'personal.id')->whereNull('personal.id')->exists()) {
            throw new RuntimeException('Cannot merge availability: at least one person reference is missing.');
        }

        if (Schema::hasTable('asignaciones_turno')) {
            if (DB::table('asignaciones_turno')->leftJoin('personal', 'asignaciones_turno.personal_id', '=', 'personal.id')->whereNull('personal.id')->exists()) {
                throw new RuntimeException('Cannot merge assignments: at least one person reference is missing.');
            }
            if (DB::table('asignaciones_turno')->leftJoin('turnos', 'asignaciones_turno.turno_id', '=', 'turnos.id')->whereNull('turnos.id')->exists()) {
                throw new RuntimeException('Cannot merge assignments: at least one shift reference is missing.');
            }
        }
    }

    private function mergePeople(): array
    {
        if (! Schema::hasTable('personal')) {
            return [];
        }

        $ids = [];
        foreach (DB::table('personal')->orderBy('id')->get() as $person) {
            $role = DB::table('roles')->where('id', $person->rol_id)->value('nombre');
            $ids[(int) $person->id] = DB::table('people')->insertGetId([
                'name' => $person->nombre_completo,
                'email' => $person->correo,
                'role' => trim((string) $role),
                'phone' => null,
                'status' => $person->estado ?? 'Disponible',
                'coordination_title' => $person->cargo_coordinacion,
                'minimum_rest_hours' => $person->descanso_minimo_horas,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function mergeSites(): array
    {
        if (! Schema::hasTable('sedes')) {
            return [];
        }

        $municipalities = DB::table('municipios')->pluck('nombre', 'id');
        $ids = [];
        foreach (DB::table('sedes')->orderBy('id')->get() as $site) {
            $ids[(int) $site->id] = DB::table('sites')->insertGetId([
                'name' => $site->nombre,
                'type' => $site->tipo,
                'municipality' => $municipalities[$site->municipio_id],
                'coverage' => 0,
                'status' => $site->estado ?? 'Operativa',
                'code' => $site->codigo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function mergeShifts(array $siteIds): array
    {
        if (! Schema::hasTable('turnos')) {
            return [];
        }

        $ids = [];
        foreach (DB::table('turnos')->orderBy('id')->get() as $shift) {
            $ids[(int) $shift->id] = DB::table('shifts')->insertGetId([
                'title' => $shift->titulo,
                'site_id' => $siteIds[(int) $shift->sede_id],
                'start_time' => $shift->hora_inicio,
                'end_time' => $shift->hora_fin,
                'coverage' => 0,
                'status' => $shift->estado ?? 'En curso',
                'date' => $shift->fecha,
                'team_description' => $shift->descripcion_equipo,
                'required_people' => $shift->cupo_requerido,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function mergeAvailabilities(array $personIds): int
    {
        if (! Schema::hasTable('disponibilidad_declarada')) {
            return 0;
        }

        $copied = 0;
        foreach (DB::table('disponibilidad_declarada')->orderBy('id')->get() as $availability) {
            DB::table('availabilities')->insert([
                'person_id' => $personIds[(int) $availability->personal_id],
                'shift_id' => null,
                'date' => $availability->fecha,
                'start_time' => $availability->hora_inicio,
                'end_time' => $availability->hora_fin,
                'status' => $availability->estado_disponibilidad,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $copied++;
        }

        return $copied;
    }

    private function mergeAssignments(array $personIds, array $shiftIds): int
    {
        if (! Schema::hasTable('asignaciones_turno')) {
            return 0;
        }

        $copied = 0;
        foreach (DB::table('asignaciones_turno')->orderBy('id')->get() as $assignment) {
            DB::table('shift_assignments')->insert([
                'shift_id' => $shiftIds[(int) $assignment->turno_id],
                'person_id' => $personIds[(int) $assignment->personal_id],
                'assigned_at' => $assignment->fecha_asiguracion,
            ]);
            $copied++;
        }

        return $copied;
    }

    private function assertMergedRows(array $personIds, array $siteIds, array $shiftIds, int $availabilityCount, int $assignmentCount): void
    {
        $copied = [
            'people' => count($personIds),
            'sites' => count($siteIds),
            'shifts' => count($shiftIds),
            'availabilities' => $availabilityCount,
            'shift_assignments' => $assignmentCount,
        ];
        $expected = [
            'people' => Schema::hasTable('personal') ? DB::table('personal')->count() : 0,
            'sites' => Schema::hasTable('sedes') ? DB::table('sedes')->count() : 0,
            'shifts' => Schema::hasTable('turnos') ? DB::table('turnos')->count() : 0,
            'availabilities' => Schema::hasTable('disponibilidad_declarada') ? DB::table('disponibilidad_declarada')->count() : 0,
            'shift_assignments' => Schema::hasTable('asignaciones_turno') ? DB::table('asignaciones_turno')->count() : 0,
        ];

        foreach ($expected as $table => $count) {
            if ($copied[$table] !== $count) {
                throw new RuntimeException("Consolidation check failed for {$table}: expected {$count} copied rows, found {$copied[$table]}.");
            }
        }
    }

    private function dropLegacyTables(): void
    {
        foreach (['asignaciones_turno', 'disponibilidad_declarada', 'turnos', 'sedes', 'personal', 'municipios'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
