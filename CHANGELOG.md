# Registro de cambios

## 2026-10-08

### Añadido

- Selectores dependientes de estado, municipio y parroquia al registrar o editar una sede, con validación de las relaciones también en el servidor.
- Campos de estado y parroquia para las sedes; las ubicaciones históricas sin estado se mantienen editables sin alterar municipio.
- Catálogo territorial venezolano con atribución a Edutherz y pruebas para validar su jerarquía y municipios sin parroquias cargadas.

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
- Los roles disponibles en el selector de personal se administran desde los registros de la tabla `roles`; no hay opciones predeterminadas.
- El archivo `.env` local está excluido de Git.

### Alcance pendiente

- Algunas etiquetas informativas de los listados siguen siendo estáticas y no guardan parámetros en MySQL.
