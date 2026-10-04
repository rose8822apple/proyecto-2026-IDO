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

                    @if (($field['type'] ?? 'text') === 'phone')
                        @php
                            $phonePrefix = old('phone_prefix', $field['prefix_value'] ?? '');
                        @endphp
                        <div class="input-group phone-input">
                            <select id="phone_prefix" name="phone_prefix" class="form-select" aria-label="Prefijo telefónico">
                                <option value="">Prefijo</option>
                                @foreach (($field['options'] ?? []) as $valueOption => $label)
                                    <option value="{{ $valueOption }}" {{ $phonePrefix == $valueOption ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input
                                id="phone_number"
                                name="phone_number"
                                class="form-control"
                                type="tel"
                                value="{{ $value }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                maxlength="{{ $field['maxlength'] }}"
                                pattern="{{ $field['pattern'] }}"
                                inputmode="{{ $field['inputmode'] }}"
                                aria-label="Resto del número, 7 dígitos"
                            >
                        </div>
                    @elseif (($field['type'] ?? 'text') === 'cedula')
                        @php
                            $cedulaPrefix = old('cedula_prefix', $field['prefix_value'] ?? '');
                        @endphp
                        <div class="input-group phone-input">
                            <select id="cedula_prefix" name="cedula_prefix" class="form-select" aria-label="Tipo de identificación">
                                <option value="">V- / E-</option>
                                @foreach (($field['options'] ?? []) as $valueOption => $label)
                                    <option value="{{ $valueOption }}" {{ $cedulaPrefix == $valueOption ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input
                                id="cedula_number"
                                name="cedula_number"
                                class="form-control"
                                type="tel"
                                value="{{ $value }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                maxlength="{{ $field['maxlength'] }}"
                                pattern="{{ $field['pattern'] }}"
                                inputmode="{{ $field['inputmode'] }}"
                                aria-label="Número de identificación, hasta 10 dígitos"
                            >
                        </div>
                    @elseif (($field['type'] ?? 'text') === 'select')
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
                            @if(!empty($field['maxlength'])) maxlength="{{ $field['maxlength'] }}" @endif
                            @if(!empty($field['pattern'])) pattern="{{ $field['pattern'] }}" @endif
                            @if(!empty($field['inputmode'])) inputmode="{{ $field['inputmode'] }}" @endif
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
