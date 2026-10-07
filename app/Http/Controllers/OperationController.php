<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class OperationController extends Controller
{
    private const PHONE_PREFIXES = ['0426', '0416', '0414', '0424', '0412'];

    public function __construct(private AuditLogger $auditLogger) {}

    protected function logOperation(string $event, Model $model, array $context = [], array $before = []): void
    {
        Log::channel('stderr')->info('Evento de aplicación: '.$event, [
            'entity' => class_basename($model),
            'id' => $model->getKey(),
            ...$context,
        ]);

        $this->auditLogger->record($event, $model, $before);
    }

    protected function buildFields(array $fields, $model = null): array
    {
        foreach ($fields as $index => $field) {
            $name = $field['name'];
            $value = $model && isset($model->{$name})
                ? $model->{$name}
                : old($name, $field['value'] ?? null);

            if (($field['type'] ?? null) === 'time' && is_string($value)) {
                $value = substr($value, 0, 5);
            }

            $fields[$index]['value'] = $value;
        }

        return $fields;
    }

    protected function personFields(array $roles, ?Person $person = null): array
    {
        $phone = preg_replace('/\D/', '', (string) ($person?->phone ?? ''));
        $prefix = collect(self::PHONE_PREFIXES)->first(fn ($candidate) => str_starts_with($phone, $candidate));
        preg_match('/^(V-|E-)(\d{1,10})$/', strtoupper((string) ($person?->cedula ?? '')), $cedulaMatch);

        return $this->buildFields([
            ['name' => 'name', 'label' => 'Nombre completo', 'type' => 'text', 'placeholder' => 'Ana García', 'required' => true],
            ['name' => 'email', 'label' => 'Correo electrónico', 'type' => 'email', 'placeholder' => 'ana@redsalud.org', 'required' => true],
            ['name' => 'cedula_number', 'label' => 'Cédula', 'type' => 'cedula', 'options' => ['V-' => 'V-', 'E-' => 'E-'], 'prefix_value' => $cedulaMatch[1] ?? null, 'value' => $cedulaMatch[2] ?? '', 'placeholder' => '1234567890', 'maxlength' => 10, 'pattern' => '[0-9]{1,10}', 'inputmode' => 'numeric'],
            ['name' => 'role', 'label' => 'Rol', 'type' => 'select', 'options' => array_combine($roles, $roles), 'required' => true],
            ['name' => 'phone_number', 'label' => 'Teléfono', 'type' => 'phone', 'options' => array_combine(self::PHONE_PREFIXES, self::PHONE_PREFIXES), 'prefix_value' => $prefix, 'value' => $prefix ? substr($phone, strlen($prefix)) : '', 'placeholder' => '1234567', 'maxlength' => 7, 'pattern' => '[0-9]{7}', 'inputmode' => 'numeric'],
        ], $person);
    }

    protected function normalizePersonPhone(array $validated): array
    {
        $validated['cedula'] = ! empty($validated['cedula_number'])
            ? $validated['cedula_prefix'].$validated['cedula_number']
            : null;
        $validated['phone'] = ! empty($validated['phone_number'])
            ? $validated['phone_prefix'].$validated['phone_number']
            : null;
        unset($validated['cedula_prefix'], $validated['cedula_number'], $validated['phone_prefix'], $validated['phone_number']);

        return $validated;
    }

    protected function cedulaRules(Request $request, ?Person $person = null): array
    {
        return [
            'cedula_prefix' => ['nullable', 'required_with:cedula_number', Rule::in(['V-', 'E-'])],
            'cedula_number' => [
                'nullable',
                'required_with:cedula_prefix',
                'digits_between:1,10',
                function ($attribute, $value, $fail) use ($request, $person): void {
                    $prefix = $request->input('cedula_prefix');

                    if (! in_array($prefix, ['V-', 'E-'], true) || ! is_string($value) || ! preg_match('/^\d{1,10}$/', $value)) {
                        return;
                    }

                    $query = Person::query()->where('cedula', $prefix.$value);
                    if ($person) {
                        $query->where('id', '!=', $person->id);
                    }

                    if ($query->exists()) {
                        $fail('Usuario existente: la cédula ya está registrada.');
                    }
                },
            ],
        ];
    }

    protected function availableRoles(): array
    {
        return DB::table('roles')
            ->pluck('nombre')
            ->filter()
            ->map(fn ($role) => trim((string) $role))
            ->values()
            ->all();
    }

    protected function availableShifts(): array
    {
        return Shift::query()
            ->with('site')
            ->orderBy('date')
            ->orderBy('end_date')
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(fn (Shift $shift) => [
                $shift->id => ($shift->date ?? 'Sin fecha')
                    .($shift->end_date && $shift->end_date !== $shift->date ? ' al '.$shift->end_date : '')
                    .' | '.$shift->title.' | '.($shift->site?->name ?? 'Sin sede').' | '.substr($shift->start_time, 0, 5).' - '.substr($shift->end_time, 0, 5),
            ])
            ->all();
    }

    protected function availabilityPersonField(): array
    {
        $columns = ['id', 'name', 'email', 'role', 'cedula', 'phone'];
        if (Schema::hasColumn('people', 'status')) {
            $columns[] = 'status';
        }

        $people = Person::query()
            ->select($columns)
            ->orderBy('name')
            ->get();

        return [
            'name' => 'person_id',
            'label' => 'Persona',
            'type' => 'select',
            'options' => $people->mapWithKeys(fn (Person $person) => [$person->id => $person->name])->all(),
            'option_details' => $people->mapWithKeys(fn (Person $person) => [
                $person->id => [
                    'name' => $person->name,
                    'email' => $person->email,
                    'role' => $person->role,
                    'cedula' => $person->cedula ?: 'No registrada',
                    'phone' => $person->phone ?: 'No registrado',
                    'status' => $person->status ?: 'Sin estado',
                ],
            ])->all(),
            'required' => true,
        ];
    }

    protected function hasAvailabilityConflict(
        int $personId,
        Shift $shift,
        ?int $exceptAvailabilityId = null,
        ?string $candidateDate = null,
    ): bool
    {
        $candidateStartDate = (string) ($shift->date ?? $candidateDate ?? now()->toDateString());
        $candidateEndDate = (string) ($shift->end_date ?? $candidateStartDate);
        $candidateTimes = $this->splitShiftTimes((string) $shift->start_time, (string) $shift->end_time);

        $availabilities = Availability::query()
            ->with('shift')
            ->where('person_id', $personId)
            ->when($exceptAvailabilityId, fn ($query) => $query->whereKeyNot($exceptAvailabilityId))
            ->get();

        foreach ($availabilities as $availability) {
            $existingStartDate = (string) ($availability->shift?->date ?? $availability->date);
            $existingEndDate = (string) ($availability->shift?->end_date ?? $existingStartDate);
            $existingTimes = $this->splitShiftTimes(
                (string) ($availability->shift?->start_time ?? $availability->start_time),
                (string) ($availability->shift?->end_time ?? $availability->end_time),
            );

            foreach ($candidateTimes as [$candidateStart, $candidateEnd, $candidateOffset]) {
                $candidateChunkStart = $this->shiftDateByDays($candidateStartDate, $candidateOffset);
                $candidateChunkEnd = $this->shiftDateByDays($candidateEndDate, $candidateOffset);

                foreach ($existingTimes as [$existingStart, $existingEnd, $existingOffset]) {
                    $existingChunkStart = $this->shiftDateByDays($existingStartDate, $existingOffset);
                    $existingChunkEnd = $this->shiftDateByDays($existingEndDate, $existingOffset);

                    $datesOverlap = $candidateChunkStart <= $existingChunkEnd
                        && $existingChunkStart <= $candidateChunkEnd;
                    $timesOverlap = $candidateStart < $existingEnd && $existingStart < $candidateEnd;

                    if ($datesOverlap && $timesOverlap) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function splitShiftTimes(string $startTime, string $endTime): array
    {
        $start = ((int) substr($startTime, 0, 2) * 60) + (int) substr($startTime, 3, 2);
        $end = ((int) substr($endTime, 0, 2) * 60) + (int) substr($endTime, 3, 2);

        return $end > $start
            ? [[$start, $end, 0]]
            : [[$start, 1440, 0], [0, $end, 1]];
    }

    protected function shiftDateByDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify("+{$days} days")->format('Y-m-d');
    }

    protected function ensureNoAvailabilityConflict(
        int $personId,
        Shift $shift,
        ?int $exceptAvailabilityId = null,
        ?string $candidateDate = null,
    ): void
    {
        if ($this->hasAvailabilityConflict($personId, $shift, $exceptAvailabilityId, $candidateDate)) {
            throw ValidationException::withMessages([
                'person_id' => 'La persona ya tiene asignado un turno que se cruza en fecha y horario.',
            ]);
        }
    }

    public function createPerson()
    {
        $roles = $this->availableRoles();

        return view('operations.create', [
            'title' => 'Añadir personal',
            'description' => 'Registra a un profesional, voluntario o colaborador en la red operativa.',
            'action' => route('people.store'),
            'submitText' => 'Guardar personal',
            'fields' => $this->personFields($roles),
        ]);
    }

    public function editPerson(Person $person)
    {
        $roles = $this->availableRoles();

        return view('operations.create', [
            'title' => 'Editar personal',
            'description' => 'Actualiza la información del profesional, voluntario o colaborador.',
            'action' => route('people.update', $person),
            'submitText' => 'Actualizar personal',
            'method' => 'PUT',
            'fields' => $this->personFields($roles, $person),
        ]);
    }

    public function storePerson(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[0-9]/'],
            'email' => ['required', 'email', 'max:255', 'unique:people,email'],
            ...$this->cedulaRules($request),
            'role' => ['required', 'string', 'max:100', Rule::in($this->availableRoles())],
            'phone_prefix' => ['nullable', 'required_with:phone_number', Rule::in(self::PHONE_PREFIXES)],
            'phone_number' => ['nullable', 'required_with:phone_prefix', 'digits:7'],
            'status' => ['nullable', 'string', 'max:50'],
        ], [
            'name.not_regex' => 'El nombre no puede contener números.',
        ]);

        $validated['status'] ??= 'Disponible';

        $validated = $this->normalizePersonPhone($validated);
        DB::transaction(function () use ($validated): void {
            $person = Person::create($validated);
            $this->logOperation('person.created', $person, ['fields' => array_keys($validated)]);
        });

        return redirect()->route('people')->with('success', 'Personal registrado correctamente.');
    }

    public function updatePerson(Request $request, Person $person)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[0-9]/'],
            'email' => ['required', 'email', 'max:255', Rule::unique('people', 'email')->ignore($person->id)],
            ...$this->cedulaRules($request, $person),
            'role' => ['required', 'string', 'max:100', Rule::in($this->availableRoles())],
            'phone_prefix' => ['nullable', 'required_with:phone_number', Rule::in(self::PHONE_PREFIXES)],
            'phone_number' => ['nullable', 'required_with:phone_prefix', 'digits:7'],
            'status' => ['nullable', 'string', 'max:50'],
        ], [
            'name.not_regex' => 'El nombre no puede contener números.',
        ]);

        $validated['status'] ??= $person->status ?? 'Disponible';
        $validated = $this->normalizePersonPhone($validated);
        DB::transaction(function () use ($person, $validated): void {
            $before = $person->getAttributes();
            $person->update($validated);
            $this->logOperation('person.updated', $person, ['fields' => array_keys($validated)], $before);
        });

        return redirect()->route('people')->with('success', 'Personal actualizado correctamente.');
    }

    public function destroyPerson(Person $person)
    {
        DB::transaction(function () use ($person): void {
            $availabilities = $person->availabilities()->get();
            foreach ($availabilities as $availability) {
                $this->logOperation('availability.deleted', $availability);
            }

            $person->availabilities()->delete();
            $person->delete();
            $this->logOperation('person.deleted', $person, ['deleted_availabilities' => $availabilities->count()]);
        });

        return redirect()->route('people')->with('success', 'Personal eliminado correctamente.');
    }

    public function createShift()
    {
        $sites = Site::query()->orderBy('name')->pluck('name', 'id')->all();

        return view('operations.create', [
            'title' => 'Crear turno',
            'description' => 'Programa un turno con la sede, el rango de fechas y el horario requerido.',
            'action' => route('shifts.store'),
            'submitText' => 'Guardar turno',
            'fields' => $this->buildFields([
                ['name' => 'title', 'label' => 'Nombre del turno', 'type' => 'text', 'placeholder' => 'Triaje general', 'required' => true],
                ['name' => 'site_id', 'label' => 'Sede', 'type' => 'select', 'options' => $sites, 'required' => true],
                ['name' => 'date', 'label' => 'Fecha de inicio', 'type' => 'date', 'required' => true],
                ['name' => 'end_date', 'label' => 'Fecha de fin', 'type' => 'date', 'required' => true],
                ['name' => 'start_time', 'label' => 'Hora de inicio', 'type' => 'time', 'required' => true],
                ['name' => 'end_time', 'label' => 'Hora de fin', 'type' => 'time', 'required' => true],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['En curso' => 'En curso', 'Completo' => 'Completo', '1 vacante' => '1 vacante', 'Vacantes' => 'Vacantes', 'Cancelado' => 'Cancelado'], 'required' => true],
            ]),
        ]);
    }

    public function editShift(Shift $shift)
    {
        $sites = Site::query()->orderBy('name')->pluck('name', 'id')->all();

        return view('operations.create', [
            'title' => 'Editar turno',
            'description' => 'Actualiza la programación, el rango de fechas y el horario del turno.',
            'action' => route('shifts.update', $shift),
            'submitText' => 'Actualizar turno',
            'method' => 'PUT',
            'fields' => $this->buildFields([
                ['name' => 'title', 'label' => 'Nombre del turno', 'type' => 'text', 'placeholder' => 'Triaje general', 'required' => true],
                ['name' => 'site_id', 'label' => 'Sede', 'type' => 'select', 'options' => $sites, 'required' => true],
                ['name' => 'date', 'label' => 'Fecha de inicio', 'type' => 'date', 'required' => true],
                ['name' => 'end_date', 'label' => 'Fecha de fin', 'type' => 'date', 'required' => true],
                ['name' => 'start_time', 'label' => 'Hora de inicio', 'type' => 'time', 'required' => true],
                ['name' => 'end_time', 'label' => 'Hora de fin', 'type' => 'time', 'required' => true],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['En curso' => 'En curso', 'Completo' => 'Completo', '1 vacante' => '1 vacante', 'Vacantes' => 'Vacantes', 'Cancelado' => 'Cancelado'], 'required' => true],
            ], $shift),
        ]);
    }

    public function storeShift(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:date'],
            'start_time' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/'],
            'end_time' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/'],
            'status' => ['required', 'string', 'max:50'],
        ], [
            'start_time.regex' => 'La hora de inicio debe tener el formato HH:mm.',
            'end_time.regex' => 'La hora de fin debe tener el formato HH:mm.',
        ]);

        DB::transaction(function () use ($validated): void {
            $shift = Shift::create($validated);
            $this->logOperation('shift.created', $shift, ['fields' => array_keys($validated)]);
        });

        return redirect()->route('shifts')->with('success', 'Turno registrado correctamente.');
    }

    public function updateShift(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:date'],
            'start_time' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/'],
            'end_time' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/'],
            'status' => ['required', 'string', 'max:50'],
        ], [
            'start_time.regex' => 'La hora de inicio debe tener el formato HH:mm.',
            'end_time.regex' => 'La hora de fin debe tener el formato HH:mm.',
        ]);

        DB::transaction(function () use ($shift, $validated): void {
            $before = $shift->getAttributes();
            $shift->update($validated);
            $this->logOperation('shift.updated', $shift, ['fields' => array_keys($validated)], $before);
        });

        return redirect()->route('shifts')->with('success', 'Turno actualizado correctamente.');
    }

    public function destroyShift(Shift $shift)
    {
        DB::transaction(function () use ($shift): void {
            $this->logOperation('shift.deleted', $shift);
            $shift->delete();
        });

        return redirect()->route('shifts')->with('success', 'Turno eliminado correctamente.');
    }

    public function createSite()
    {
        return view('operations.create', [
            'title' => 'Registrar sede',
            'description' => 'Agrega una sede activa a la red operativa.',
            'action' => route('sites.store'),
            'submitText' => 'Guardar sede',
            'fields' => $this->buildFields([
                ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'placeholder' => 'Hospital San Gabriel', 'required' => true],
                ['name' => 'type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Hospital' => 'Hospital', 'Refugio' => 'Refugio', 'Clínica' => 'Clínica', 'Otro' => 'Otro'], 'required' => true],
                ['name' => 'municipality', 'label' => 'Municipio', 'type' => 'text', 'placeholder' => 'San Miguel', 'required' => true],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['Operativa' => 'Operativa', 'En revisión' => 'En revisión', 'Inactiva' => 'Inactiva'], 'required' => true],
            ]),
        ]);
    }

    public function editSite(Site $site)
    {
        return view('operations.create', [
            'title' => 'Editar sede',
            'description' => 'Actualiza la información operativa de la sede.',
            'action' => route('sites.update', $site),
            'submitText' => 'Actualizar sede',
            'method' => 'PUT',
            'fields' => $this->buildFields([
                ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'placeholder' => 'Hospital San Gabriel', 'required' => true],
                ['name' => 'type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Hospital' => 'Hospital', 'Refugio' => 'Refugio', 'Clínica' => 'Clínica', 'Otro' => 'Otro'], 'required' => true],
                ['name' => 'municipality', 'label' => 'Municipio', 'type' => 'text', 'placeholder' => 'San Miguel', 'required' => true],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['Operativa' => 'Operativa', 'En revisión' => 'En revisión', 'Inactiva' => 'Inactiva'], 'required' => true],
            ], $site),
        ]);
    }

    public function storeSite(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated): void {
            $site = Site::create($validated);
            $this->logOperation('site.created', $site, ['fields' => array_keys($validated)]);
        });

        return redirect()->route('sites')->with('success', 'Sede registrada correctamente.');
    }

    public function updateSite(Request $request, Site $site)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($site, $validated): void {
            $before = $site->getAttributes();
            $site->update($validated);
            $this->logOperation('site.updated', $site, ['fields' => array_keys($validated)], $before);
        });

        return redirect()->route('sites')->with('success', 'Sede actualizada correctamente.');
    }

    public function destroySite(Site $site)
    {
        DB::transaction(function () use ($site): void {
            $shifts = $site->shifts()->get();
            foreach ($shifts as $shift) {
                $this->logOperation('shift.deleted', $shift);
            }

            $site->shifts()->delete();
            $site->delete();
            $this->logOperation('site.deleted', $site, ['deleted_shifts' => $shifts->count()]);
        });

        return redirect()->route('sites')->with('success', 'Sede eliminada correctamente.');
    }

    public function createAvailability()
    {
        $shifts = $this->availableShifts();

        return view('operations.create', [
            'title' => 'Registrar disponibilidad',
            'description' => 'Selecciona a la persona, verifica sus datos y registra su disponibilidad.',
            'action' => route('availability.store'),
            'submitText' => 'Guardar disponibilidad',
            'fields' => $this->buildFields([
                $this->availabilityPersonField(),
                ['name' => 'shift_id', 'label' => 'Turno', 'type' => 'select', 'options' => $shifts, 'required' => true],
            ]),
            'showPersonPreview' => true,
        ]);
    }

    public function editAvailability(Availability $availability)
    {
        $shifts = $this->availableShifts();

        return view('operations.create', [
            'title' => 'Editar disponibilidad',
            'description' => 'Verifica los datos de la persona antes de actualizar su disponibilidad.',
            'action' => route('availability.update', $availability),
            'submitText' => 'Actualizar disponibilidad',
            'method' => 'PUT',
            'fields' => $this->buildFields([
                $this->availabilityPersonField(),
                ['name' => 'shift_id', 'label' => 'Turno', 'type' => 'select', 'options' => $shifts, 'required' => true],
            ], $availability),
            'showPersonPreview' => true,
        ]);
    }

    public function storeAvailability(Request $request)
    {
        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'date' => ['nullable', 'date'],
        ]);

        $shift = Shift::findOrFail($validated['shift_id']);
        $validated['date'] ??= $shift->date ?? now()->toDateString();
        $validated['start_time'] = $shift->start_time;
        $validated['end_time'] = $shift->end_time;
        $validated['status'] = 'No disponible';

        DB::transaction(function () use ($validated, $shift): void {
            Person::query()->whereKey($validated['person_id'])->lockForUpdate()->firstOrFail();
            $this->ensureNoAvailabilityConflict(
                (int) $validated['person_id'],
                $shift,
                candidateDate: $validated['date'],
            );
            $availability = Availability::create($validated);
            $this->logOperation('availability.created', $availability, ['fields' => array_keys($validated)]);
        });

        return redirect()->route('availability')->with('success', 'Disponibilidad registrada.');
    }

    public function updateAvailability(Request $request, Availability $availability)
    {
        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'date' => ['nullable', 'date'],
        ]);

        $shift = Shift::findOrFail($validated['shift_id']);
        $validated['date'] ??= $shift->date ?? $availability->date ?? now()->toDateString();
        $validated['start_time'] = $shift->start_time;
        $validated['end_time'] = $shift->end_time;
        $validated['status'] = 'No disponible';

        DB::transaction(function () use ($availability, $validated, $shift): void {
            Person::query()->whereKey($validated['person_id'])->lockForUpdate()->firstOrFail();
            $this->ensureNoAvailabilityConflict(
                (int) $validated['person_id'],
                $shift,
                $availability->id,
                $validated['date'],
            );
            $before = $availability->getAttributes();
            $availability->update($validated);
            $this->logOperation('availability.updated', $availability, ['fields' => array_keys($validated)], $before);
        });

        return redirect()->route('availability')->with('success', 'Disponibilidad actualizada correctamente.');
    }

    public function destroyAvailability(Availability $availability)
    {
        DB::transaction(function () use ($availability): void {
            $this->logOperation('availability.deleted', $availability);
            $availability->delete();
        });

        return redirect()->route('availability')->with('success', 'Disponibilidad eliminada correctamente.');
    }
}
