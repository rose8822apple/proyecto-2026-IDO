<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Panel de coordinación' }} | RedSalud</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css'])
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark"><i class="bi bi-plus-lg"></i></span><span>Red<span class="brand-accent">Salud</span></span></a>
        <div class="sidebar-label">Centro de coordinación</div>
        <nav class="sidebar-nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2-fill"></i> Resumen</a>
            <a class="nav-link {{ request()->routeIs('shifts') ? 'active' : '' }}" href="{{ route('shifts') }}"><i class="bi bi-calendar3"></i> Turnos</a>
            <a class="nav-link {{ request()->routeIs('people') ? 'active' : '' }}" href="{{ route('people') }}"><i class="bi bi-people-fill"></i> Personal</a>
            <a class="nav-link {{ request()->routeIs('sites') ? 'active' : '' }}" href="{{ route('sites') }}"><i class="bi bi-hospital-fill"></i> Sedes activas</a>
            <a class="nav-link {{ request()->routeIs('availability') ? 'active' : '' }}" href="{{ route('availability') }}"><i class="bi bi-clock-history"></i> Disponibilidad</a>
        </nav>
        <div class="sidebar-footer"><a class="nav-link" href="{{ route('settings') }}"><i class="bi bi-sliders2"></i> Configuración</a><div class="profile-mini"><span class="avatar">MR</span><span><strong>María Ríos</strong><small>Coordinación general</small></span><i class="bi bi-three-dots"></i></div></div>
    </aside>
    <main class="main-content">
        <header class="topbar"><div class="mobile-brand"><span class="brand-mark"><i class="bi bi-plus-lg"></i></span>Red<span class="brand-accent">Salud</span></div><div class="topbar-actions"><button class="icon-button" aria-label="Buscar"><i class="bi bi-search"></i></button><button class="icon-button notification" aria-label="Notificaciones"><i class="bi bi-bell"></i><span></span></button><div class="topbar-divider"></div><span class="topbar-date"><i class="bi bi-calendar3"></i> Martes, 24 de septiembre</span></div></header>
        <div class="page-container">@yield('content')</div>
    </main>
</div>
</body>
</html>
