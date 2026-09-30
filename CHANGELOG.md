# Registro de cambios

## 2026-09-30

### Añadido

- CRUD persistente para personal, sedes, turnos y disponibilidad mediante rutas y formularios Laravel.
- Modelos y migraciones para los registros operativos, con relaciones entre personal, disponibilidad, sedes y turnos.
- Selector de rol consultado en tiempo de petición desde `roles.nombre`, para reflejar las opciones que existen en MySQL.
- Resumen con métricas calculadas a partir de personal, sedes, cobertura y turnos guardados.
- Acciones de alta, edición y eliminación en los listados operativos.
- Formularios de creación y edición reutilizables con mensajes de validación.
- Validación del servidor para campos requeridos, correo único, roles permitidos, referencias existentes, horarios y cobertura de 0 a 100; controles JavaScript complementan la validación numérica en el navegador.
- Pruebas de integración que comprueban la carga de roles, el rechazo de cobertura negativa y las operaciones CRUD.
- Instrucciones de instalación, ejecución, configuración de MySQL y alcance funcional en el README.

### Verificado

- `php artisan test --filter=OperationFormsTest`: 3 pruebas aprobadas y 43 aserciones.
- La tabla `roles` de la base `red_atencion` contiene cinco roles: Médico, Enfermero, Voluntario, Coordinador y Paramédico.
- El archivo `.env` local está excluido de Git.

### Alcance pendiente

- La vista Configuración y algunas etiquetas informativas de los listados siguen siendo estáticas y no guardan parámetros en MySQL.
