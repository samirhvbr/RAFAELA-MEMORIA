<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin · {{ config('app.name', 'Jogo da Rafaela') }}</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="admin">
    <header class="admin-header">
        <div class="admin-header__inner">
            <span class="admin-brand">🎮 {{ config('app.name', 'Jogo da Rafaela') }} <small>admin</small></span>
            @if (session('admin_logged_in'))
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Sair</button>
                </form>
            @endif
        </div>
    </header>

    <main class="admin-main">
        @yield('content')
    </main>

    <footer class="admin-footer">
        Jogo da Rafaela · v{{ config('app.version') }}
    </footer>
</body>
</html>
