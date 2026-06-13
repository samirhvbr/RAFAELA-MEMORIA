<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#FF6B9D">
    <title>{{ config('app.name', 'Jogo da Rafaela') }}</title>
    @vite(['resources/css/game.css', 'resources/js/game.js'])
</head>
<body>
    {{-- Configuração consumida pelo game.js (definida ANTES do módulo deferido) --}}
    <script>
        window.LEVELS = @json($levels);
        window.LOG_URL = @json(route('game.log'));
        window.CSRF_TOKEN = @json(csrf_token());
    </script>

    @yield('content')
</body>
</html>
