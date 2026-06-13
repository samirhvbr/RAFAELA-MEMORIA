<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sessão expirada</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
            font-family:'Segoe UI',system-ui,sans-serif;background:#FFF0F5;color:#7A2E5A;text-align:center}
        .box{padding:2rem}.big{font-size:4rem;margin:0}h1{font-size:1.4rem;margin:.5rem 0}
        a{color:#A855F7;font-weight:600;text-decoration:none}
    </style>
</head>
<body>
    <div class="box">
        <p class="big">⏳</p>
        <h1>Sua sessão expirou.</h1>
        <p>Atualize a página e tente novamente.</p>
        <p><a href="{{ url('/') }}">← Voltar</a></p>
    </div>
</body>
</html>
