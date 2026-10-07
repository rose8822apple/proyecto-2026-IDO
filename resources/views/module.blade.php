@extends('layouts.app')
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $eyebrow }}</div><h1>{{ $title }}</h1><p class="lead-copy">{{ $description }}</p></div><a class="btn btn-primary" href="{{ route($actionRoute) }}"><i class="bi bi-plus-lg"></i> {{ $action }}</a></div>
<div class="module-toolbar">
    <div class="search-box"><i class="bi bi-search"></i><input type="text" placeholder="Buscar en {{ strtolower($title) }}..." aria-label="Buscar"></div>
    <div class="status-filter">
        <button class="filter-button" type="button" data-status-filter-toggle aria-expanded="false" aria-haspopup="true"><i class="bi bi-funnel"></i> Filtrar <i class="bi bi-chevron-down"></i></button>
        <div class="filter-menu" data-status-filter-menu hidden></div>
    </div>
</div>
<div class="panel module-panel">
    <div class="panel-head">
        <div>
            <h3>{{ $panelTitle }}</h3>
            <p>Información vigente del sistema</p>
        </div>
        <span class="status-pill">Vista general</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle" data-table-role="module-table" data-page-size="10">
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th @if(in_array(mb_strtolower($column), ['estado', 'disponibilidad'], true)) data-status-column @endif>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{!! $cell !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pagination-note" data-table-pagination>
        <span>
            Mostrando <span data-range-start>0</span>-<span data-range-end>0</span>
            de <span data-record-count>{{ count($rows) }}</span> registros
        </span>
        <nav class="module-pagination d-flex align-items-center gap-2" aria-label="Paginación de registros">
            <button type="button" class="btn btn-secondary btn-small" data-page-previous aria-label="Página anterior">Anterior</button>
            <span data-page-indicator aria-live="polite">1 / 1</span>
            <button type="button" class="btn btn-secondary btn-small" data-page-next aria-label="Página siguiente">Siguiente</button>
        </nav>
    </div>
</div>
@endsection
