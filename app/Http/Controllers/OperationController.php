<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationController extends Controller
{
    protected function buildFields(array $fields, $model = null): array
    {
        foreach ($fields as $index => $field) {
            $name = $field['name'];
            $fields[$index]['value'] = $model && isset($model->{$name}) ? $model->{$name} : old($name);
        }

        return $fields;
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

    public function createPerson()
    {
        $roles = $this->availableRoles();

        return view('operations.create', [
            'title' => 'Añadir personal',
            'description' => 'Registra a un profesional, voluntario o colaborador en la red operativa.',
            'action' => route('people.store'),
            'submitText' => 'Guardar personal',
            'fields' => $this->buildFields([
                ['name' => 'name', 'label' => 'Nombre completo', 'type' => 'text', 'placeholder' => 'Ana García', 'required' => true],
                ['name' => 'email', 'label' => 'Correo electrónico', 'type' => 'email', 'placeholder' => 'ana@redsalud.org', 'required' => true],
                ['name' => 'role', 'label' => 'Rol', 'type' => 'select', 'options' => array_combine($roles, $roles), 'required' => true],
                ['name' => 'phone', 'label' => 'Teléfono', 'type' => 'text', 'placeholder' => '777123456', 'required' => false],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['Disponible' => 'Disponible', 'En turno' => 'En turno', 'No disponible' => 'No disponible'], 'required' => true],
            ]),
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
            'fields' => $this->buildFields([
                ['name' => 'name', 'label' => 'Nombre completo', 'type' => 'text', 'placeholder' => 'Ana García', 'required' => true],
                ['name' => 'email', 'label' => 'Correo electrónico', 'type' => 'email', 'placeholder' => 'ana@redsalud.org', 'required' => true],
                ['name' => 'role', 'label' => 'Rol', 'type' => 'select', 'options' => array_combine($roles, $roles), 'required' => true],
                ['name' => 'phone', 'label' => 'Teléfono', 'type' => 'text', 'placeholder' => '777123456', 'required' => false],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['Disponible' => 'Disponible', 'En turno' => 'En turno', 'No disponible' => 'No disponible'], 'required' => true],
            ], $person),
        ]);
    }

    public function storePerson(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:people,email'],
            'role' => ['required', 'string', 'max:100', Rule::in($this->availableRoles())],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        Person::create($validated);

        return redirect()->route('people')->with('success', 'Personal registrado correctamente.');
    }

    public function updatePerson(Request $request, Person $person)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('people', 'email')->ignore($person->id)],
            'role' => ['required', 'string', 'max:100', Rule::in($this->availableRoles())],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $person->update($validated);

        return redirect()->route('people')->with('success', 'Personal actualizado correctamente.');
    }

    public function destroyPerson(Person $person)
    {
        $person->availabilities()->delete();
        $person->delete();

        return redirect()->route('people')->with('success', 'Personal eliminado correctamente.');
    }

    public function createShift()
    {
        $sites = Site::query()->orderBy('name')->pluck('name', 'id')->all();

        return view('operations.create', [
            'title' => 'Crear turno',
            'description' => 'Programa un turno con la sede, horario y cobertura requerida.',
            'action' => route('shifts.store'),
            'submitText' => 'Guardar turno',
            'fields' => $this->buildFields([
                ['name' => 'title', 'label' => 'Nombre del turno', 'type' => 'text', 'placeholder' => 'Triaje general', 'required' => true],
                ['name' => 'site_id', 'label' => 'Sede', 'type' => 'select', 'options' => $sites, 'required' => true],
                ['name' => 'start_time', 'label' => 'Hora de inicio', 'type' => 'time', 'required' => true],
                ['name' => 'end_time', 'label' => 'Hora de fin', 'type' => 'time', 'required' => true],
                ['name' => 'coverage', 'label' => 'Cobertura (%)', 'type' => 'number', 'placeholder' => '100', 'required' => true, 'min' => 0, 'max' => 100],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['En curso' => 'En curso', 'Completo' => 'Completo', '1 vacante' => '1 vacante'], 'required' => true],
            ]),
        ]);
    }

    public function editShift(Shift $shift)
    {
        $sites = Site::query()->orderBy('name')->pluck('name', 'id')->all();

        return view('operations.create', [
            'title' => 'Editar turno',
            'description' => 'Actualiza la programación de turno y cobertura.',
            'action' => route('shifts.update', $shift),
            'submitText' => 'Actualizar turno',
            'method' => 'PUT',
            'fields' => $this->buildFields([
                ['name' => 'title', 'label' => 'Nombre del turno', 'type' => 'text', 'placeholder' => 'Triaje general', 'required' => true],
                ['name' => 'site_id', 'label' => 'Sede', 'type' => 'select', 'options' => $sites, 'required' => true],
                ['name' => 'start_time', 'label' => 'Hora de inicio', 'type' => 'time', 'required' => true],
                ['name' => 'end_time', 'label' => 'Hora de fin', 'type' => 'time', 'required' => true],
                ['name' => 'coverage', 'label' => 'Cobertura (%)', 'type' => 'number', 'placeholder' => '100', 'required' => true, 'min' => 0, 'max' => 100],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['En curso' => 'En curso', 'Completo' => 'Completo', '1 vacante' => '1 vacante'], 'required' => true],
            ], $shift),
        ]);
    }

    public function storeShift(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'coverage' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        Shift::create($validated);

        return redirect()->route('shifts')->with('success', 'Turno registrado correctamente.');
    }

    public function updateShift(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'coverage' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $shift->update($validated);

        return redirect()->route('shifts')->with('success', 'Turno actualizado correctamente.');
    }

    public function destroyShift(Shift $shift)
    {
        $shift->delete();

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
                ['name' => 'type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Hospital' => 'Hospital', 'Refugio' => 'Refugio', 'Clínica' => 'Clínica'], 'required' => true],
                ['name' => 'municipality', 'label' => 'Municipio', 'type' => 'text', 'placeholder' => 'San Miguel', 'required' => true],
                ['name' => 'coverage', 'label' => 'Cobertura (%)', 'type' => 'number', 'placeholder' => '95', 'required' => true, 'min' => 0, 'max' => 100],
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
                ['name' => 'type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Hospital' => 'Hospital', 'Refugio' => 'Refugio', 'Clínica' => 'Clínica'], 'required' => true],
                ['name' => 'municipality', 'label' => 'Municipio', 'type' => 'text', 'placeholder' => 'San Miguel', 'required' => true],
                ['name' => 'coverage', 'label' => 'Cobertura (%)', 'type' => 'number', 'placeholder' => '95', 'required' => true, 'min' => 0, 'max' => 100],
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
            'coverage' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        Site::create($validated);

        return redirect()->route('sites')->with('success', 'Sede registrada correctamente.');
    }

    public function updateSite(Request $request, Site $site)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:120'],
            'coverage' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $site->update($validated);

        return redirect()->route('sites')->with('success', 'Sede actualizada correctamente.');
    }

    public function destroySite(Site $site)
    {
        $site->shifts()->delete();
        $site->delete();

        return redirect()->route('sites')->with('success', 'Sede eliminada correctamente.');
    }

    public function createAvailability()
    {
        $people = Person::query()->orderBy('name')->pluck('name', 'id')->all();

        return view('operations.create', [
            'title' => 'Registrar disponibilidad',
            'description' => 'Define la disponibilidad del personal para turnos y descansos.',
            'action' => route('availability.store'),
            'submitText' => 'Guardar disponibilidad',
            'fields' => $this->buildFields([
                ['name' => 'person_id', 'label' => 'Persona', 'type' => 'select', 'options' => $people, 'required' => true],
                ['name' => 'date', 'label' => 'Fecha', 'type' => 'date', 'required' => true],
                ['name' => 'start_time', 'label' => 'Hora de inicio', 'type' => 'time', 'required' => true],
                ['name' => 'end_time', 'label' => 'Hora de fin', 'type' => 'time', 'required' => true],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['Disponible' => 'Disponible', 'Asignado' => 'Asignado', 'No disponible' => 'No disponible'], 'required' => true],
            ]),
        ]);
    }

    public function editAvailability(Availability $availability)
    {
        $people = Person::query()->orderBy('name')->pluck('name', 'id')->all();

        return view('operations.create', [
            'title' => 'Editar disponibilidad',
            'description' => 'Actualiza la ventana horaria y el estado de disponibilidad.',
            'action' => route('availability.update', $availability),
            'submitText' => 'Actualizar disponibilidad',
            'method' => 'PUT',
            'fields' => $this->buildFields([
                ['name' => 'person_id', 'label' => 'Persona', 'type' => 'select', 'options' => $people, 'required' => true],
                ['name' => 'date', 'label' => 'Fecha', 'type' => 'date', 'required' => true],
                ['name' => 'start_time', 'label' => 'Hora de inicio', 'type' => 'time', 'required' => true],
                ['name' => 'end_time', 'label' => 'Hora de fin', 'type' => 'time', 'required' => true],
                ['name' => 'status', 'label' => 'Estado', 'type' => 'select', 'options' => ['Disponible' => 'Disponible', 'Asignado' => 'Asignado', 'No disponible' => 'No disponible'], 'required' => true],
            ], $availability),
        ]);
    }

    public function storeAvailability(Request $request)
    {
        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        Availability::create($validated);

        return redirect()->route('availability')->with('success', 'Disponibilidad registrada.');
    }

    public function updateAvailability(Request $request, Availability $availability)
    {
        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $availability->update($validated);

        return redirect()->route('availability')->with('success', 'Disponibilidad actualizada correctamente.');
    }

    public function destroyAvailability(Availability $availability)
    {
        $availability->delete();

        return redirect()->route('availability')->with('success', 'Disponibilidad eliminada correctamente.');
    }
}
