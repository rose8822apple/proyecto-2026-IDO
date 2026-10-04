<?php

use App\Http\Controllers\OperationController;
use App\Models\Availability;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::post('/notificaciones/vistas', function (Request $request) {
    $request->session()->put('notifications.seen_through', [
        'shifts' => Shift::query()->max('id') ?? 0,
        'people' => Person::query()->max('id') ?? 0,
        'sites' => Site::query()->max('id') ?? 0,
        'availability' => Availability::query()->max('id') ?? 0,
    ]);

    return response()->noContent();
})->name('notifications.read');

Route::get('/auditoria', function (Request $request) {
    $entityClasses = [
        'people' => Person::class,
        'shifts' => Shift::class,
        'sites' => Site::class,
        'availabilities' => Availability::class,
    ];
    $events = [
        'created' => 'Alta',
        'updated' => 'Edición',
        'deleted' => 'Eliminación',
    ];
    $filters = $request->validate([
        'entity' => ['nullable', Rule::in(array_keys($entityClasses))],
        'event' => ['nullable', Rule::in(array_keys($events))],
        'date_from' => ['nullable', 'date'],
        'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        'q' => ['nullable', 'string', 'max:120'],
    ]);

    $query = AuditLog::query()->with('actor')->orderByDesc('created_at')->orderByDesc('id');

    if (! empty($filters['entity'])) {
        $query->where('auditable_type', $entityClasses[$filters['entity']]);
    }

    if (! empty($filters['event'])) {
        $query->where('event', $filters['event']);
    }

    if (! empty($filters['date_from'])) {
        $query->whereDate('created_at', '>=', $filters['date_from']);
    }

    if (! empty($filters['date_to'])) {
        $query->whereDate('created_at', '<=', $filters['date_to']);
    }

    if (! empty($filters['q'])) {
        $search = sprintf('%%%s%%', $filters['q']);
        $query->where(function ($query) use ($search) {
            $query->where('auditable_id', 'like', $search)
                ->orWhere('old_values', 'like', $search)
                ->orWhere('new_values', 'like', $search)
                ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', $search));
        });
    }

    return view('audit.index', [
        'auditLogs' => $query->paginate(20)->withQueryString(),
        'entityOptions' => [
            'people' => 'Personal',
            'shifts' => 'Turnos',
            'sites' => 'Sedes',
            'availabilities' => 'Disponibilidad',
        ],
        'events' => $events,
        'filters' => $filters,
    ]);
})->name('audit');

Route::get('/dashboard', function () {
    $people = Person::query()->with('availabilities')->get();
    $personStatuses = $people->map(function ($person) {
        return $person->availabilities()->latest('updated_at')->value('status') ?? $person->status ?? 'Sin disponibilidad';
    });

    $peopleCount = $people->count();
    $operationalPeople = $personStatuses->filter(fn ($status) => $status === 'Disponible')->count();
    $peopleOnShift = $personStatuses->filter(fn ($status) => in_array($status, ['Asignado', 'En turno'], true))->count();
    $unavailablePeople = $personStatuses->filter(fn ($status) => in_array($status, ['No disponible', 'Horario específico', 'Inactivo'], true))->count();
    $sitesCount = Site::count();
    $operationalSites = Site::where('status', 'Operativa')->count();
    $sitesInReview = Site::where('status', 'En revisión')->count();
    $totalShifts = Shift::count();
    $recentShifts = Shift::with('site')->orderByDesc('created_at')->limit(4)->get();
    $alerts = $recentShifts->filter(fn ($shift) => $shift->status !== 'Completo')->values();

    return view('dashboard', [
        'title' => 'Resumen',
        'peopleCount' => $peopleCount,
        'operationalPeople' => $operationalPeople,
        'peopleOnShift' => $peopleOnShift,
        'unavailablePeople' => $unavailablePeople,
        'sitesCount' => $sitesCount,
        'operationalSites' => $operationalSites,
        'sitesInReview' => $sitesInReview,
        'totalShifts' => $totalShifts,
        'alerts' => $alerts,
    ]);
})->name('dashboard');

Route::get('/turnos', function () {
    $rows = Shift::with('site')->orderBy('date')->orderBy('start_time')->get()->map(function ($shift) {
        return [
            '<strong>' . e($shift->title) . '</strong><small class="table-subtext">Turno programado</small>',
            $shift->site?->name ?? 'Sin sede',
            $shift->date ?? 'Sin fecha',
            $shift->start_time . ' - ' . $shift->end_time,
            '<span class="tag ' . ($shift->status === 'Completo' ? 'tag-green' : ($shift->status === 'En curso' ? 'tag-red' : 'tag-yellow')) . '">' . e($shift->status) . '</span>',
            '<div class="table-actions"><a class="btn btn-secondary btn-small" href="' . route('shifts.edit', $shift) . '">Editar</a><form action="' . route('shifts.destroy', $shift) . '" method="POST" onsubmit="return confirm(\'¿Eliminar este turno?\');" style="display:inline;">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-small">Eliminar</button></form></div>',
        ];
    })->toArray();

    return view('module', [
        'title' => 'Turnos',
        'eyebrow' => 'GESTIÓN DE TURNOS',
        'description' => 'Organiza los horarios de hospitales y refugios sin perder de vista los descansos.',
        'action' => 'Crear turno',
        'actionRoute' => 'shifts.create',
        'panelTitle' => 'Turnos programados',
        'columns' => ['Turno', 'Sede', 'Fecha', 'Horario', 'Estado', 'Acciones'],
        'rows' => $rows,
    ]);
})->name('shifts');
Route::get('/turnos/crear', [OperationController::class, 'createShift'])->name('shifts.create');
Route::get('/turnos/{shift}/editar', [OperationController::class, 'editShift'])->name('shifts.edit');
Route::post('/turnos', [OperationController::class, 'storeShift'])->name('shifts.store');
Route::put('/turnos/{shift}', [OperationController::class, 'updateShift'])->name('shifts.update');
Route::delete('/turnos/{shift}', [OperationController::class, 'destroyShift'])->name('shifts.destroy');

Route::get('/personal', function () {
    $rows = Person::with('availabilities')->orderBy('name')->get()->map(function ($person) {
        $roleClass = match (strtolower($person->role)) {
            'médica', 'medica', 'doctor', 'doctora' => 'doctor',
            'enfermero', 'enfermera', 'nurse' => 'nurse',
            default => 'volunteer',
        };

        $status = $person->availabilities()->latest('updated_at')->value('status') ?? $person->status ?? 'Sin disponibilidad';

        return [
            '<strong>' . e($person->name) . '</strong><small class="table-subtext">' . e($person->email) . '</small>',
            '<span class="role-chip ' . $roleClass . '">' . e($person->role) . '</span>',
            $status,
            'Próximo turno',
            '<span class="tag ' . ($status === 'Disponible' ? 'tag-green' : (in_array($status, ['Asignado', 'En turno'], true) ? 'tag-red' : 'tag-yellow')) . '">' . e($status) . '</span>',
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
        'columns' => ['Sede', 'Tipo', 'Municipio', 'Estado', 'Acciones'],
        'rows' => $rows,
    ]);
})->name('sites');
Route::get('/sedes/nueva', [OperationController::class, 'createSite'])->name('sites.create');
Route::get('/sedes/{site}/editar', [OperationController::class, 'editSite'])->name('sites.edit');
Route::post('/sedes', [OperationController::class, 'storeSite'])->name('sites.store');
Route::put('/sedes/{site}', [OperationController::class, 'updateSite'])->name('sites.update');
Route::delete('/sedes/{site}', [OperationController::class, 'destroySite'])->name('sites.destroy');

Route::get('/disponibilidad', function () {
    $rows = Availability::with(['person', 'shift'])->orderBy('created_at')->get()->map(function ($availability) {
        $shiftDate = $availability->shift?->date ?? $availability->date ?? 'Sin fecha';
        $shiftTime = $availability->shift
            ? $availability->shift->start_time.' - '.$availability->shift->end_time
            : ($availability->start_time.' - '.$availability->end_time);

        return [
            '<strong>' . e($availability->person?->name ?? 'Sin persona') . '</strong><small class="table-subtext">' . e($availability->status) . '</small>',
            $shiftDate,
            $shiftTime,
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
