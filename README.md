<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Red de Atención

Aplicación Laravel 12 para administrar personal, sedes, turnos y disponibilidad de una red de atención. Los módulos operativos guardan sus registros en MySQL; el resumen obtiene sus métricas de esos registros.

### Requisitos

- PHP 8.2 o posterior, Composer y Node.js/npm.
- MySQL disponible; en Windows se puede usar MySQL desde XAMPP.

### Configuración local

1. Instala dependencias con `composer install` y `npm install`.
2. Copia `.env.example` como `.env` y genera la clave con `php artisan key:generate`.
3. Configura `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`.
4. En una base de datos nueva, ejecuta `php artisan migrate` para crear las tablas del proyecto. Si conectas una base existente, revisa primero las tablas y el historial de migraciones; no vuelvas a crear tablas que ya existan. La tabla `roles` debe tener una columna `nombre`; el selector consulta sus registros y no mantiene una lista estática.
5. Inicia el servidor con `php artisan serve` y Vite, en otra terminal, con `npm run dev`. Abre `http://127.0.0.1:8000`.

En XAMPP, si PHP no está en el `PATH`, ejecuta los comandos Artisan con `C:\xampp\php\php.exe`. Si la base de datos usa otra estructura existente, respalda la base y verifica las migraciones antes de ejecutarlas.

### Funciones implementadas

- Resumen con conteos, cobertura media y turnos recientes calculados desde la base de datos.
- CRUD de personal, sedes, turnos y disponibilidad: listados, formularios de alta/edición y eliminación.
- Las tablas operativas heredadas en español se consolidan en el esquema actual mediante la migración `2026_10_03_000006_consolidate_legacy_operational_tables`; conserva registros y relaciones, añade columnas para los campos históricos y requiere respaldo antes de migrar.
- Auditoría de solo lectura en `/auditoria`, con filtros por módulo, acción, fechas y búsqueda; registra cambios antes/después desde la migración en adelante.
- Selector de rol cargado en cada formulario desde `roles.nombre`; la validación del servidor rechaza roles que no estén en esa tabla.
- Selectores de sede y personal alimentados con registros existentes.
- Validación de campos requeridos, correo único, referencias existentes, horarios y cobertura entre 0 y 100.
- Formularios con mensajes de validación y controles de entrada para valores numéricos.
- Pruebas de integración para formularios, validación de cobertura y operaciones CRUD.

### Resumen de vistas

- **Resumen (`/dashboard`)**: muestra el total de turnos, personas y sedes, agrupa personal y sedes por estado, y presenta alertas operativas con enlaces para revisar turnos o gestionar disponibilidad.
- **Turnos (`/turnos`)**: lista cada turno con su sede, fecha, horario y estado. Permite buscar, filtrar, exportar y acceder a las acciones de alta, edición y eliminación. La fecha forma parte del turno.
- **Personal (`/personal`)**: muestra personas, roles y su estado de disponibilidad más reciente. Permite buscar, filtrar, exportar y administrar los registros. La disponibilidad se administra desde su propio módulo.
- **Sedes activas (`/sedes`)**: presenta sedes, tipo, municipio y estado operativo, con opciones para buscar, filtrar, exportar, registrar, editar y eliminar.
- **Disponibilidad (`/disponibilidad`)**: relaciona personas con turnos y su estado de disponibilidad. La fecha y el horario se obtienen del turno seleccionado; los cambios se reflejan también en Personal y Resumen.
- **Auditoría (`/auditoria`)**: consulta de solo lectura de cambios, con filtros por módulo, acción, intervalo de fechas y búsqueda. Muestra quién realizó el cambio y los valores anteriores y nuevos disponibles.
- **Formularios de operación**: las pantallas de alta y edición de personal, turnos, sedes y disponibilidad comparten un formulario que valida y guarda los datos correspondientes; permiten cancelar y regresar a la vista anterior.
- **Elementos compartidos**: el menú lateral navega entre módulos; el encabezado muestra la fecha y notificaciones recientes. El perfil identifica a la coordinación actual.

### Consola del navegador

Con `APP_DEBUG=true` en el entorno local, abre las herramientas de desarrollo del navegador (F12) y selecciona **Consola** para ver mensajes de carga de vistas, envío de formularios, filtros, exportaciones y resultados o errores al marcar notificaciones. Los mensajes se desactivan cuando `APP_DEBUG=false`; no incluyen valores de formularios ni información personal.

### Pruebas

```powershell
C:\xampp\php\php.exe artisan test
```

O, si PHP está en el `PATH`:

```sh
php artisan test
```

El historial de cambios funcionales está en [CHANGELOG.md](CHANGELOG.md).
