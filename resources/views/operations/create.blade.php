@extends('layouts.app')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">GESTIÓN</div>
            <h1>{{ $title }}</h1>
            <p class="lead-copy">{{ $description }}</p>
        </div>
    </div>

    <div class="panel module-panel">
        <div class="panel-head">
            <div>
                <h3>{{ $title }}</h3>
                <p>Completa la información y guarda el registro</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-4">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="mt-4 operation-form">
            @csrf
            @if (!empty($method))
                @method($method)
            @endif

            @foreach ($fields as $field)
                @php
                    $value = old($field['name'], $field['value'] ?? '');
                @endphp

                <div class="form-group">
                    <label for="{{ $field['name'] }}">{{ $field['label'] }}</label>

                    @if (($field['type'] ?? 'text') === 'select')
                        <select id="{{ $field['name'] }}" name="{{ $field['name'] }}" @if(!empty($field['required'])) required @endif>
                            <option value="">Selecciona una opción</option>
                            @foreach (($field['options'] ?? []) as $valueOption => $label)
                                <option value="{{ $valueOption }}" {{ $value == $valueOption ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            type="{{ $field['type'] ?? 'text' }}"
                            value="{{ $value }}"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            @if(!empty($field['required'])) required @endif
                            @if(($field['type'] ?? '') === 'number') step="1" @endif
                            @if(!empty($field['min'])) min="{{ $field['min'] }}" @endif
                            @if(!empty($field['max'])) max="{{ $field['max'] }}" @endif
                        >
                    @endif
                </div>
            @endforeach

            <div class="form-actions">
                <a href="{{ url()->previous() ?: route('dashboard') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">{{ $submitText }}</button>
            </div>
        </form>
    </div>
@endsection
