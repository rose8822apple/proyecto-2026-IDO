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
        @php
            $roleField = collect($fields)->firstWhere('name', 'role');
        @endphp
        @if ($roleField && empty($roleField['options']))
            <div class="alert alert-warning mt-4" role="status">
                No hay roles registrados, así que no existen opciones para asignar. <a href="{{ route('roles') }}">Agrega un rol</a> para continuar.
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="mt-4 operation-form">
            @csrf
            @if (!empty($method))
                @method($method)
            @endif
            @if (!empty($isSiteForm))
                <script type="application/json" data-territory-catalog>@json($territoryCatalog)</script>
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
                                data-numeric-only
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
                    @elseif (in_array(($field['type'] ?? 'text'), ['select', 'territory_state', 'territory_municipality', 'territory_parish'], true))
                        <select
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            @if(($field['name'] ?? '') === 'person_id') data-person-select @endif
                            @if(($field['name'] ?? '') === 'state') data-territory-state @endif
                            @if(($field['name'] ?? '') === 'municipality') data-territory-municipality @endif
                            @if(($field['name'] ?? '') === 'parish') data-territory-parish @endif
                            @if(!empty($field['required'])) required @endif
                            @if(!empty($field['disabled'])) disabled @endif
                        >
                            <option value="">Selecciona una opción</option>
                            @foreach (($field['options'] ?? []) as $valueOption => $label)
                                @php
                                    $optionDetails = $field['option_details'][$valueOption] ?? [];
                                @endphp
                                <option
                                    value="{{ $valueOption }}"
                                    @if(($field['name'] ?? '') === 'person_id')
                                        data-person-name="{{ $optionDetails['name'] ?? '' }}"
                                        data-person-email="{{ $optionDetails['email'] ?? '' }}"
                                        data-person-role="{{ $optionDetails['role'] ?? '' }}"
                                        data-person-cedula="{{ $optionDetails['cedula'] ?? '' }}"
                                        data-person-phone="{{ $optionDetails['phone'] ?? '' }}"
                                        data-person-status="{{ $optionDetails['status'] ?? '' }}"
                                    @endif
                                    {{ $value == $valueOption ? 'selected' : '' }}
                                >{{ $label }}</option>
                            @endforeach
                        </select>
                        @if(!empty($field['help_text']))
                            <small class="form-text">{{ $field['help_text'] }}</small>
                        @endif
                        @if(($field['name'] ?? '') === 'parish')
                            <small class="form-text" data-territory-empty-message hidden>Este municipio no tiene parroquias disponibles en el catálogo.</small>
                        @endif
                    @else
                        <input
                            id="{{ $field['name'] }}"
                            name="{{ $field['name'] }}"
                            type="{{ $field['type'] ?? 'text' }}"
                            value="{{ $value }}"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            @if(!empty($field['required'])) required @endif
                            @if(in_array(($field['name'] ?? ''), ['name', 'title', 'municipality'], true)) pattern="[^0-9]+" data-name-only @endif
                            @if(($field['type'] ?? '') === 'date')
                                min="{{ now()->toDateString() }}"
                                data-date-field="{{ $field['name'] }}"
                            @endif
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

            @if (!empty($showPersonPreview))
                <section class="person-preview" data-person-preview hidden aria-live="polite" aria-atomic="true">
                    <div class="person-preview-heading">
                        <i class="bi bi-person-check-fill" aria-hidden="true"></i>
                        <div>
                            <h4>Verifica la persona seleccionada</h4>
                            <p>Confirma estos datos antes de guardar la disponibilidad.</p>
                        </div>
                    </div>
                    <dl class="person-preview-details">
                        <div><dt>Nombre</dt><dd data-person-preview-name></dd></div>
                        <div><dt>Cédula</dt><dd data-person-preview-cedula></dd></div>
                        <div><dt>Rol</dt><dd data-person-preview-role></dd></div>
                        <div><dt>Teléfono</dt><dd data-person-preview-phone></dd></div>
                        <div><dt>Correo</dt><dd data-person-preview-email></dd></div>
                        <div><dt>Estado actual</dt><dd data-person-preview-status></dd></div>
                    </dl>
                </section>
            @endif

            <div class="form-actions">
                <a href="{{ url()->previous() ?: route('dashboard') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" @if($roleField && empty($roleField['options'])) disabled @endif>{{ $submitText }}</button>
            </div>
        </form>
    </div>
@endsection
