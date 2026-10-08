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
    <div class="stats-grid dashboard-summary-grid">
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
            <span>Sedes registradas</span>
            <strong>{{ $sitesCount }}</strong>
            <small class="neutral">{{ $operationalSites }} operativas</small>
        </div>
    </div>

    <div class="panel operational-stats-panel">
        <div class="panel-head">
            <div>
                <h3>Estadísticas operativas</h3>
                <p>Sedes agrupadas por estado</p>
            </div>
        </div>
        <div class="stats-status-group">
            <h4>Sedes</h4>
            <div class="stats-status-grid">
                <div class="stats-status-item">
                    <span>Operativas</span>
                    <strong data-stat="operational-sites">{{ $operationalSites }}</strong>
                </div>
                <div class="stats-status-item">
                    <span>En revisión</span>
                    <strong data-stat="sites-in-review">{{ $sitesInReview }}</strong>
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
