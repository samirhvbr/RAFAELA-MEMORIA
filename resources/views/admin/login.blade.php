@extends('layouts.admin')

@section('content')
<div class="login-wrap">
    <div class="login-card">
        <h1 class="login-title">Painel da Rafaela</h1>
        <p class="login-sub">Acesso restrito</p>

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}" autocomplete="on">
            @csrf
            <label class="field">
                <span>E-mail</span>
                <input type="email" name="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username">
            </label>
            <label class="field">
                <span>Senha</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>
    </div>
</div>
@endsection
