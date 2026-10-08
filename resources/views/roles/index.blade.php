@extends('layouts.app')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">CONFIGURACIÓN</div>
            <h1>Roles de personal</h1>
            <p class="lead-copy">Administra los roles disponibles. El formulario de personal muestra únicamente los registros que aparecen aquí.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="panel module-panel mb-4">
        <div class="panel-head">
            <div>
                <h3>Agregar rol</h3>
                <p>Al guardarlo, estará disponible inmediatamente al añadir o editar personal.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('roles.store') }}" class="d-flex flex-column flex-sm-row gap-3 mt-3">
            @csrf
            <label class="visually-hidden" for="nombre">Nombre del rol</label>
            <input
                id="nombre"
                name="nombre"
                type="text"
                class="form-control"
                value="{{ old('nombre') }}"
                maxlength="50"
                pattern="[^0-9]+"
                data-name-only
                placeholder="Nombre del rol"
                required
            >
            <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Agregar rol</button>
        </form>
    </div>

    <div class="panel module-panel">
        <div class="panel-head">
            <div>
                <h3>Opciones registradas</h3>
                <p>{{ $roles->count() }} {{ $roles->count() === 1 ? 'rol disponible' : 'roles disponibles' }}</p>
            </div>
        </div>

        @if ($roles->isEmpty())
            <p class="text-muted mt-3 mb-0">No hay roles registrados. El selector de rol del formulario de personal no ofrecerá opciones hasta que agregues uno.</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Rol</th>
                            <th>Personas asignadas</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>{{ $role->nombre }}</td>
                                <td>{{ $role->people_count }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('roles.destroy', $role->id) }}" onsubmit="return confirm('¿Eliminar este rol de las opciones disponibles?')">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                            @disabled($role->people_count > 0)
                                            aria-label="Eliminar rol {{ $role->nombre }}"
                                        >
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    </form>
                                    @if ($role->people_count > 0)
                                        <small class="text-muted">No se puede eliminar mientras esté asignado.</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
