<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión | RedSalud</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/login.css'])
</head>
<body class="login-page">
    <main class="login-card">
        <a class="login-brand" href="{{ route('login') }}" aria-label="RedSalud">
            <span class="brand-mark"><i class="bi bi-plus-lg"></i></span>
            <span>Red<span class="brand-accent">Salud</span></span>
        </a>
        <p class="login-eyebrow">CENTRO DE COORDINACIÓN</p>
        <h1>Bienvenido</h1>
        <p class="login-description">Inicia sesión para acceder al panel de RedSalud.</p>

        @if ($errors->any())
            <div class="login-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="login-form">
            @csrf
            <label for="username">Usuario</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" required autofocus>

            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">Iniciar sesión</button>
        </form>
        <p class="login-hint">Acceso inicial: usuario <strong>admin</strong>, contraseña <strong>admin</strong>.</p>
    </main>
</body>
</html>
