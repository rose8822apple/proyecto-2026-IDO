<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('/dashboard', fn () => view('dashboard', ['title' => 'Resumen']))->name('dashboard');

$module = function (string $title, string $eyebrow, string $description, string $action, string $panelTitle, array $columns, array $rows) {
    return view('module', compact('title', 'eyebrow', 'description', 'action', 'panelTitle', 'columns', 'rows'));
};

Route::get('/turnos', fn () => $module('Turnos', 'GESTIÓN DE TURNOS', 'Organiza la cobertura de hospitales y refugios sin perder de vista los descansos.', 'Crear turno', 'Turnos programados', ['Turno', 'Sede', 'Horario', 'Cobertura', 'Estado'], [
    ['<strong>Triaje general</strong><small class="table-subtext">Médicos y enfermería</small>', 'Hospital San Gabriel', '08:00 - 14:00', '<span class="coverage-bar"><i style="width:100%"></i></span> 5/5', '<span class="tag tag-red">En curso</span>'],
    ['<strong>Atención primaria</strong><small class="table-subtext">Equipo mixto</small>', 'Refugio La Esperanza', '10:30 - 16:30', '<span class="coverage-bar"><i style="width:100%"></i></span> 6/6', '<span class="tag tag-green">Completo</span>'],
    ['<strong>Triaje pediátrico</strong><small class="table-subtext">Pediatría y apoyo</small>', 'Hospital San Gabriel', '14:00 - 18:00', '<span class="coverage-bar warning"><i style="width:75%"></i></span> 3/4', '<span class="tag tag-yellow">1 vacante</span>'],
]))->name('shifts');

Route::get('/personal', fn () => $module('Personal', 'EQUIPO HUMANO', 'Consulta perfiles, roles, disponibilidad declarada y horas de descanso.', 'Añadir persona', 'Personal registrado', ['Persona', 'Rol', 'Disponibilidad', 'Próximo turno', 'Estado'], [
    ['<strong>Ana Castillo</strong><small class="table-subtext">ana.castillo@redsalud.org</small>', '<span class="role-chip doctor">Médica</span>', 'Hoy · 08:00 - 18:00', 'Triaje general', '<span class="tag tag-green">Disponible</span>'],
    ['<strong>Luis Méndez</strong><small class="table-subtext">luis.mendez@redsalud.org</small>', '<span class="role-chip nurse">Enfermero</span>', 'Hoy · 08:00 - 16:00', 'Atención primaria', '<span class="tag tag-red">En turno</span>'],
    ['<strong>Carla Molina</strong><small class="table-subtext">carla.molina@redsalud.org</small>', '<span class="role-chip volunteer">Voluntaria</span>', 'Mañana · 10:00 - 20:00', 'Refugio La Esperanza', '<span class="tag tag-green">Disponible</span>'],
]))->name('people');

Route::get('/sedes', fn () => $module('Sedes activas', 'RED OPERATIVA', 'Hospitales y refugios disponibles para recibir equipos de atención.', 'Registrar sede', 'Sedes de la red', ['Sede', 'Tipo', 'Municipio', 'Cobertura', 'Estado'], [
    ['<strong>Hospital San Gabriel</strong><small class="table-subtext">HSG-001</small>', '<span class="role-chip hospital">Hospital</span>', 'San Miguel', '<span class="coverage-bar"><i style="width:95%"></i></span> 95%', '<span class="tag tag-green">Operativa</span>'],
    ['<strong>Refugio La Esperanza</strong><small class="table-subtext">RLE-014</small>', '<span class="role-chip shelter">Refugio</span>', 'San Miguel', '<span class="coverage-bar warning"><i style="width:88%"></i></span> 88%', '<span class="tag tag-green">Operativa</span>'],
    ['<strong>Refugio Los Pinos</strong><small class="table-subtext">RLP-022</small>', '<span class="role-chip shelter">Refugio</span>', 'Santa Elena', '<span class="coverage-bar"><i style="width:100%"></i></span> 100%', '<span class="tag tag-green">Operativa</span>'],
]))->name('sites');

Route::get('/disponibilidad', fn () => $module('Disponibilidad', 'PLANIFICACIÓN', 'Revisa las ventanas disponibles y protege los tiempos de descanso del equipo.', 'Registrar disponibilidad', 'Disponibilidad declarada', ['Persona', 'Lun 23', 'Mar 24', 'Mié 25', 'Descanso mínimo'], [
    ['<strong>Ana Castillo</strong><small class="table-subtext">Médica</small>', '<span class="availability available">Disponible</span>', '<span class="availability available">Disponible</span>', '<span class="availability partial">09:00 - 18:00</span>', '12 horas'],
    ['<strong>Luis Méndez</strong><small class="table-subtext">Enfermero</small>', '<span class="availability assigned">Asignado</span>', '<span class="availability assigned">Asignado</span>', '<span class="availability available">Disponible</span>', '12 horas'],
    ['<strong>Carla Molina</strong><small class="table-subtext">Voluntaria</small>', '<span class="availability unavailable">No disponible</span>', '<span class="availability available">Disponible</span>', '<span class="availability available">Disponible</span>', '8 horas'],
]))->name('availability');

Route::get('/configuracion', fn () => $module('Configuración', 'AJUSTES DEL SISTEMA', 'Parámetros generales para la coordinación de la red.', 'Nuevo parámetro', 'Reglas operativas', ['Regla', 'Descripción', 'Valor', 'Estado'], [
    ['<strong>Descanso mínimo</strong>', 'Horas entre turnos consecutivos', '12 horas', '<span class="tag tag-green">Activo</span>'],
    ['<strong>Cobertura mínima</strong>', 'Personal requerido por turno', '80%', '<span class="tag tag-green">Activo</span>'],
]))->name('settings');
