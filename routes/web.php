<?php

use App\Http\Controllers\OperationController;
use App\Models\Availability;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('/dashboard', function () {
    $peopleCount = Person::count();
    $sitesCount = Site::count();
    $operationalSites = Site::where('status', 'Operativa')->count();
    $totalShifts = Shift::count();
    $averageCoverage = (float) (Site::avg('coverage') ?? Shift::avg('coverage') ?? 0);
    $recentShifts = Shift::with('site')->orderByDesc('created_at')->limit(4)->get();
    $alerts = $recentShifts->filter(fn ($shift) => $shift->status !== 'Completo')->values();

    return view('dashboard', [
        'title' => 'Resumen',
        'peopleCount' => $peopleCount,
        'sitesCount' => $sitesCount,
        'operationalSites' => $operationalSites,
        'totalShifts' => $totalShifts,
        'averageCoverage' => round($averageCoverage, 0),
        'recentShifts' => $recentShifts,
        'alerts' => $alerts,
    ]);
})->name('dashboard');

Route::get('/turnos', function () {
    $rows = Shift::with('site')->orderBy('start_time')->get()->map(function ($shift) {
        return [
            '<strong>' . e($shift->title) . '</strong><small class="table-subtext">Equipo de cobertura</small>',
            $shift->site?->name ?? 'Sin sede',
            $shift->start_time . ' - ' . $shift->end_time,
            '<span class="coverage-bar"><i style="width:' . $shift->coverage . '%"></i></span> ' . $shift->coverage . '%',
            '<span class="tag ' . ($shift->status === 'Completo' ? 'tag-green' : ($shift->status === 'En curso' ? 'tag-red' : 'tag-yellow')) . '">' . e($shift->status) . '</span>',
            '<div class="table-actions"><a class="btn btn-secondary btn-small" href="' . route('shifts.edit', $shift) . '">Editar</a><form action="' . route('shifts.destroy', $shift) . '" method="POST" onsubmit="return confirm(\'¿Eliminar este turno?\');" style="display:inline;">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-small">Eliminar</button></form></div>',
        ];
    })->toArray();

    return view('module', [
        'title' => 'Turnos',
        'eyebrow' => 'GESTIÓN DE TURNOS',
        'description' => 'Organiza la cobertura de hospitales y refugios sin perder de vista los descansos.',
        'action' => 'Crear turno',
        'actionRoute' => 'shifts.create',
        'panelTitle' => 'Turnos programados',
        'columns' => ['Turno', 'Sede', 'Horario', 'Cobertura', 'Estado', 'Acciones'],
        'rows' => $rows,
    ]);
})->name('shifts');
Route::get('/turnos/crear', [OperationController::class, 'createShift'])->name('shifts.create');
Route::get('/turnos/{shift}/editar', [OperationController::class, 'editShift'])->name('shifts.edit');
Route::post('/turnos', [OperationController::class, 'storeShift'])->name('shifts.store');
Route::put('/turnos/{shift}', [OperationController::class, 'updateShift'])->name('shifts.update');
Route::delete('/turnos/{shift}', [OperationController::class, 'destroyShift'])->name('shifts.destroy');

Route::get('/personal', function () {
    $rows = Person::orderBy('name')->get()->map(function ($person) {
        $roleClass = match (strtolower($person->role)) {
            'médica', 'medica', 'doctor', 'doctora' => 'doctor',
            'enfermero', 'enfermera', 'nurse' => 'nurse',
            default => 'volunteer',
        };

        return [
            '<strong>' . e($person->name) . '</strong><small class="table-subtext">' . e($person->email) . '</small>',
            '<span class="role-chip ' . $roleClass . '">' . e($person->role) . '</span>',
            $person->status,
            'Próximo turno',
            '<span class="tag ' . ($person->status === 'Disponible' ? 'tag-green' : ($person->status === 'En turno' ? 'tag-red' : 'tag-yellow')) . '">' . e($person->status) . '</span>',
            '<div class="table-actions"><a class="btn btn-secondary btn-small" href="' . route('people.edit', $person) . '">Editar</a><form action="' . route('people.destroy', $person) . '" method="POST" onsubmit="return confirm(\'¿Eliminar esta persona?\');" style="display:inline;">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-small">Eliminar</button></form></div>',
        ];
    })->toArray();

    return view('module', [
        'title' => 'Personal',
        'eyebrow' => 'EQUIPO HUMANO',
        'description' => 'Consulta perfiles, roles, disponibilidad declarada y horas de descanso.',
        'action' => 'Añadir persona',
        'actionRoute' => 'people.create',
        'panelTitle' => 'Personal registrado',
        'columns' => ['Persona', 'Rol', 'Disponibilidad', 'Próximo turno', 'Estado', 'Acciones'],
        'rows' => $rows,
    ]);
})->name('people');
Route::get('/personal/nuevo', [OperationController::class, 'createPerson'])->name('people.create');
Route::get('/personal/{person}/editar', [OperationController::class, 'editPerson'])->name('people.edit');
Route::post('/personal', [OperationController::class, 'storePerson'])->name('people.store');
Route::put('/personal/{person}', [OperationController::class, 'updatePerson'])->name('people.update');
Route::delete('/personal/{person}', [OperationController::class, 'destroyPerson'])->name('people.destroy');

Route::get('/sedes', function () {
    $rows = Site::orderBy('name')->get()->map(function ($site) {
        return [
            '<strong>' . e($site->name) . '</strong><small class="table-subtext">' . e($site->type) . '</small>',
            '<span class="role-chip ' . ($site->type === 'Hospital' ? 'hospital' : 'shelter') . '">' . e($site->type) . '</span>',
            $site->municipality,
            '<span class="coverage-bar"><i style="width:' . $site->coverage . '%"></i></span> ' . $site->coverage . '%',
            '<span class="tag ' . ($site->status === 'Operativa' ? 'tag-green' : ($site->status === 'En revisión' ? 'tag-yellow' : 'tag-red')) . '">' . e($site->status) . '</span>',
            '<div class="table-actions"><a class="btn btn-secondary btn-small" href="' . route('sites.edit', $site) . '">Editar</a><form action="' . route('sites.destroy', $site) . '" method="POST" onsubmit="return confirm(\'¿Eliminar esta sede?\');" style="display:inline;">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-small">Eliminar</button></form></div>',
        ];
    })->toArray();

    return view('module', [
        'title' => 'Sedes activas',
        'eyebrow' => 'RED OPERATIVA',
        'description' => 'Hospitales y refugios disponibles para recibir equipos de atención.',
        'action' => 'Registrar sede',
        'actionRoute' => 'sites.create',
        'panelTitle' => 'Sedes de la red',
        'columns' => ['Sede', 'Tipo', 'Municipio', 'Cobertura', 'Estado', 'Acciones'],
        'rows' => $rows,
    ]);
})->name('sites');
Route::get('/sedes/nueva', [OperationController::class, 'createSite'])->name('sites.create');
Route::get('/sedes/{site}/editar', [OperationController::class, 'editSite'])->name('sites.edit');
Route::post('/sedes', [OperationController::class, 'storeSite'])->name('sites.store');
Route::put('/sedes/{site}', [OperationController::class, 'updateSite'])->name('sites.update');
Route::delete('/sedes/{site}', [OperationController::class, 'destroySite'])->name('sites.destroy');

Route::get('/disponibilidad', function () {
    $rows = Availability::with('person')->orderBy('date')->get()->map(function ($availability) {
        return [
            '<strong>' . e($availability->person?->name ?? 'Sin persona') . '</strong><small class="table-subtext">' . e($availability->status) . '</small>',
            $availability->date,
            $availability->start_time . ' - ' . $availability->end_time,
            '<span class="availability ' . ($availability->status === 'Disponible' ? 'available' : ($availability->status === 'Asignado' ? 'assigned' : 'unavailable')) . '">' . e($availability->status) . '</span>',
            'Descanso mínimo',
            '<div class="table-actions"><a class="btn btn-secondary btn-small" href="' . route('availability.edit', $availability) . '">Editar</a><form action="' . route('availability.destroy', $availability) . '" method="POST" onsubmit="return confirm(\'¿Eliminar esta disponibilidad?\');" style="display:inline;">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-small">Eliminar</button></form></div>',
        ];
    })->toArray();

    return view('module', [
        'title' => 'Disponibilidad',
        'eyebrow' => 'PLANIFICACIÓN',
        'description' => 'Revisa las ventanas disponibles y protege los tiempos de descanso del equipo.',
        'action' => 'Registrar disponibilidad',
        'actionRoute' => 'availability.create',
        'panelTitle' => 'Disponibilidad declarada',
        'columns' => ['Persona', 'Fecha', 'Horario', 'Estado', 'Descanso mínimo', 'Acciones'],
        'rows' => $rows,
    ]);
})->name('availability');
Route::get('/disponibilidad/nueva', [OperationController::class, 'createAvailability'])->name('availability.create');
Route::get('/disponibilidad/{availability}/editar', [OperationController::class, 'editAvailability'])->name('availability.edit');
Route::post('/disponibilidad', [OperationController::class, 'storeAvailability'])->name('availability.store');
Route::put('/disponibilidad/{availability}', [OperationController::class, 'updateAvailability'])->name('availability.update');
Route::delete('/disponibilidad/{availability}', [OperationController::class, 'destroyAvailability'])->name('availability.destroy');

Route::get('/configuracion', fn () => view('module', [
    'title' => 'Configuración',
    'eyebrow' => 'AJUSTES DEL SISTEMA',
    'description' => 'Parámetros generales para la coordinación de la red.',
    'action' => 'Nuevo parámetro',
    'actionRoute' => 'settings',
    'panelTitle' => 'Reglas operativas',
    'columns' => ['Regla', 'Descripción', 'Valor', 'Estado'],
    'rows' => [
        ['<strong>Descanso mínimo</strong>', 'Horas entre turnos consecutivos', '12 horas', '<span class="tag tag-green">Activo</span>'],
        ['<strong>Cobertura mínima</strong>', 'Personal requerido por turno', '80%', '<span class="tag tag-green">Activo</span>'],
    ],
]))->name('settings');
