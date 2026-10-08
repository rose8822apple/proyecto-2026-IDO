<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Panel de coordinación' }} | RedSalud</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/dashboard.css', 'resources/css/readability.css', 'resources/js/app.js'])
</head>
<body data-debug="{{ config('app.debug') ? 'true' : 'false' }}">
<div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark" aria-label="Logo Red Salud"><i class="bi bi-plus-lg"></i></span>
            <span>Red<span class="brand-accent">Salud</span></span>
        </a>
        <div class="sidebar-label">Centro de coordinación</div>
        <nav class="sidebar-nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2-fill"></i> Resumen</a>
            <a class="nav-link {{ request()->routeIs('shifts') ? 'active' : '' }}" href="{{ route('shifts') }}"><i class="bi bi-calendar3"></i> Turnos</a>
            <a class="nav-link {{ request()->routeIs('people') ? 'active' : '' }}" href="{{ route('people') }}"><i class="bi bi-people-fill"></i> Personal</a>
            <a class="nav-link {{ request()->routeIs('roles*') ? 'active' : '' }}" href="{{ route('roles') }}"><i class="bi bi-person-badge"></i> Roles</a>
            <a class="nav-link {{ request()->routeIs('sites') ? 'active' : '' }}" href="{{ route('sites') }}"><i class="bi bi-hospital-fill"></i> Sedes activas</a>
            <a class="nav-link {{ request()->routeIs('availability') ? 'active' : '' }}" href="{{ route('availability') }}"><i class="bi bi-clock-history"></i> Disponibilidad</a>
            <a class="nav-link {{ request()->routeIs('audit') ? 'active' : '' }}" href="{{ route('audit') }}"><i class="bi bi-journal-text"></i> Auditoría</a>
        </nav>
        <div class="sidebar-footer"><div class="profile-mini"><span class="avatar">AC</span><span><strong>Anderson Crespo</strong><small>Coordinación general</small></span></div></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="mobile-brand">
                <span class="brand-mark" aria-label="Logo Red Salud"><i class="bi bi-plus-lg"></i></span>
                Red<span class="brand-accent">Salud</span>
            </div>
            <div class="topbar-actions">
                <div class="notification-wrap">
                    <button class="icon-button notification" type="button" data-notification-toggle data-read-url="{{ route('notifications.read') }}" aria-label="Notificaciones" aria-expanded="false" aria-controls="notifications-menu">
                        <i class="bi bi-bell"></i>
                    </button>
                    @if ($notificationUnreadCount > 0)
                        <span class="notification-count" data-notification-count aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
                    @endif
                    <div class="notification-menu" id="notifications-menu" data-notification-panel hidden>
                        <div class="notification-menu-head">
                            <div><strong>Actividad reciente</strong><small>Últimos registros agregados</small></div>
                            <span data-notification-total>{{ $notificationUnreadCount }}</span>
                        </div>
                        @if ($notifications->isEmpty())
                            <p class="notification-empty">Todavía no hay registros recientes.</p>
                        @else
                            <ul class="notification-list">
                                @foreach ($notifications as $notification)
                                    <li>
                                        <a class="notification-item" href="{{ $notification['url'] }}">
                                            <span class="notification-item-icon"><i class="bi {{ $notification['icon'] }}"></i></span>
                                            <span class="notification-item-copy"><strong>{{ $notification['category'] }}</strong><span>{{ $notification['title'] }}</span></span>
                                            <time datetime="{{ $notification['created_at']->toIso8601String() }}">{{ $notification['created_at']->copy()->timezone('America/Caracas')->locale('es')->diffForHumans() }}</time>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                <div class="topbar-divider"></div>
                <div class="d-flex flex-column align-items-end gap-1">
                    <time class="topbar-date" datetime="{{ now('America/Caracas')->toDateString() }}"><i class="bi bi-calendar3"></i> {{ ucfirst(now('America/Caracas')->locale('es')->translatedFormat('l, j \\d\\e F')) }}</time>
                    <time class="topbar-date" data-live-clock datetime="{{ now('America/Caracas')->toIso8601String() }}">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <span>{{ now('America/Caracas')->format('H:i:s') }}</span>
                    </time>
                </div>
            </div>
        </header>
        <div class="page-container">@yield('content')</div>
        <footer class="site-footer">
            <p class="site-footer-copyright">© {{ now('America/Caracas')->year }} Instituto universitario jesus obrero IUJO CARACAS, Grupo 2 Investigacion de operaciones AC</p>
            <nav class="site-footer-links" aria-label="Redes sociales y repositorio del proyecto">
                <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <i class="bi bi-instagram" aria-hidden="true"></i>
                </a>
                <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                    <i class="bi bi-facebook" aria-hidden="true"></i>
                </a>
                <a href="https://x.com/" target="_blank" rel="noopener noreferrer" aria-label="X">
                    <i class="bi bi-twitter-x" aria-hidden="true"></i>
                </a>
                <a href="https://github.com/rose8822apple/proyecto-2026-IDO" target="_blank" rel="noopener noreferrer" aria-label="Repositorio de RedSalud en GitHub">
                    <i class="bi bi-github" aria-hidden="true"></i>
                </a>
            </nav>
        </footer>
    </main>
</div>
</body>
</html>
