@extends('layouts.app')
@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">PANEL GENERAL</div>
        <h1>Resumen general</h1>
        <p class="lead-copy">Estado actual de la red operativa.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('shifts.create') }}"><i class="bi bi-plus-lg"></i> Crear turno</a>
</div>

@if($peopleCount > 0 || $sitesCount > 0 || $totalShifts > 0)
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-calendar-check"></i></div>
            <span>Turnos registrados</span>
            <strong>{{ $totalShifts }}</strong>
            <small class="neutral">En la base del proyecto</small>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
            <span>Personas registradas</span>
            <strong>{{ $peopleCount }}</strong>
            <small class="neutral">Usuarios del sistema</small>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-hospital"></i></div>
            <span>Sedes activas</span>
            <strong>{{ $sitesCount }}</strong>
            <small class="neutral">{{ $operationalSites }} operativas</small>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-shield-check"></i></div>
            <span>Cobertura promedio</span>
            <strong>{{ $averageCoverage }}<small class="percent">%</small></strong>
            <small class="neutral">Calculada desde registros reales</small>
        </div>
    </div>

    <div class="section-row">
        <div class="section-title">
            <h2>Actividad reciente</h2>
            <span class="status-pill">Actualizado del sistema</span>
        </div>
        <a class="text-link" href="{{ route('shifts') }}">Ver todos <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="content-grid">
        <div class="panel schedule-panel">
            <div class="panel-head">
                <div>
                    <h3>Turnos creados</h3>
                    <p>Últimos registros disponibles</p>
                </div>
            </div>
            <div class="shift-list">
                @foreach($recentShifts as $shift)
                    <div class="shift-item">
                        <div class="shift-time">{{ substr($shift->start_time, 0, 5) }}</div>
                        <div class="shift-line"></div>
                        <div class="shift-info">
                            <strong>{{ $shift->title }}</strong>
                            <span><i class="bi bi-hospital"></i> {{ $shift->site?->name ?? 'Sin sede' }}</span>
                        </div>
                        <span class="tag {{ $shift->status === 'Completo' ? 'tag-green' : ($shift->status === 'En curso' ? 'tag-red' : 'tag-yellow') }}">{{ $shift->status }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel coverage-panel">
            <div class="panel-head">
                <div>
                    <h3>Estado general</h3>
                    <p>Datos actuales del proyecto</p>
                </div>
            </div>
            <div class="coverage-chart">
                <div class="donut">
                    <strong>{{ $averageCoverage }}<small>%</small></strong>
                    <span>cobertura</span>
                </div>
                <div class="coverage-metrics">
                    <div><strong>{{ $sitesCount }}</strong><span>sedes</span></div>
                    <div><strong>{{ $peopleCount }}</strong><span>personas</span></div>
                    <div><strong>{{ $totalShifts }}</strong><span>turnos</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-row mt-5">
        <div class="section-title">
            <h2>Alertas operativas</h2>
            <span class="count-pill">{{ $alerts->count() }}</span>
        </div>
        <a class="text-link" href="{{ route('availability') }}">Gestionar disponibilidad <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="alert-list">
        @if($alerts->isEmpty())
            <div>
                <i class="bi bi-check-circle-fill success-icon"></i>
                <span>
                    <strong>No hay alertas pendientes.</strong>
                    <small>Los registros actuales están en estado operativo.</small>
                </span>
            </div>
        @else
            @foreach($alerts as $alert)
                <div>
                    <i class="bi bi-exclamation-triangle-fill warning-icon"></i>
                    <span>
                        <strong>{{ $alert->title }}</strong>
                        <small>{{ $alert->site?->name ?? 'Sede no asignada' }} · {{ $alert->start_time }} - {{ $alert->end_time }}</small>
                    </span>
                    <a href="{{ route('shifts') }}">Revisar</a>
                </div>
            @endforeach
        @endif
    </div>
@else
    <div class="panel module-panel">
        <div class="panel-head">
            <div>
                <h3>Sin registros todavía</h3>
                <p>Aún no se han creado turnos, sedes ni personal en el proyecto.</p>
            </div>
        </div>
    </div>
@endif
@endsection
