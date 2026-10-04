@extends('layouts.app')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">CONTROL DEL SISTEMA</div>
            <h1>Auditoría</h1>
            <p class="lead-copy">Consulta los cambios registrados en los módulos operativos.</p>
        </div>
    </div>

    <div class="panel module-panel audit-panel">
        <div class="panel-head">
            <div>
                <h3>Historial de cambios</h3>
                <p>{{ $auditLogs->total() }} registros</p>
            </div>
        </div>

        <form method="GET" action="{{ route('audit') }}" class="audit-filters" data-audit-filters>
            <div class="audit-filter-grid">
                <div class="form-group">
                    <label for="q">Buscar</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre, detalle o ID">
                </div>
                <div class="form-group">
                    <label for="entity">Módulo</label>
                    <select id="entity" name="entity">
                        <option value="">Todos</option>
                        @foreach ($entityOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['entity'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="event">Acción</label>
                    <select id="event" name="event">
                        <option value="">Todas</option>
                        @foreach ($events as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['event'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">Desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label for="date_to">Hasta</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
                </div>
            </div>
            <div class="audit-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('audit') }}" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle audit-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Acción</th>
                        <th>Módulo</th>
                        <th>Registro</th>
                        <th>Usuario</th>
                        <th>Antes</th>
                        <th>Después</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditLogs as $entry)
                        @php
                            $labels = [
                                'App\\Models\\Person' => 'Personal',
                                'App\\Models\\Shift' => 'Turno',
                                'App\\Models\\Site' => 'Sede',
                                'App\\Models\\Availability' => 'Disponibilidad',
                            ];
                        @endphp
                        <tr>
                            <td><time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->copy()->timezone('America/Caracas')->format('d/m/Y H:i:s') }}</time></td>
                            <td>{{ $events[$entry->event] ?? $entry->event }}</td>
                            <td>{{ $labels[$entry->auditable_type] ?? class_basename($entry->auditable_type) }}</td>
                            <td>#{{ $entry->auditable_id }}</td>
                            <td>{{ $entry->actor?->name ?? 'Sin identificar' }}</td>
                            <td class="audit-values">
                                @forelse (($entry->old_values ?? []) as $field => $value)
                                    <div><strong>{{ $field }}</strong>: {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</div>
                                @empty
                                    <span class="audit-empty">—</span>
                                @endforelse
                            </td>
                            <td class="audit-values">
                                @forelse (($entry->new_values ?? []) as $field => $value)
                                    <div><strong>{{ $field }}</strong>: {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</div>
                                @empty
                                    <span class="audit-empty">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="audit-empty-state">No hay registros que coincidan con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="audit-pagination">{{ $auditLogs->links('pagination::bootstrap-5') }}</div>
    </div>
@endsection