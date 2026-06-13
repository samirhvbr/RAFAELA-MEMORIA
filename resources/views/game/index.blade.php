@extends('layouts.game')

@section('content')
<div class="confetti" id="confetti" aria-hidden="true"></div>

{{-- ─── Tela inicial ─────────────────────────────────────────── --}}
<section class="screen active" id="screen-start">
    <div class="start-card">
        <div class="mascot" aria-hidden="true">🦄</div>
        <h1 class="title">Jogo da<br>Memória</h1>
        <p class="subtitle">da Rafaela 💖</p>
        <button class="btn btn-play" id="btn-start" type="button">Jogar! 🎮</button>
        <p class="hint">Encontre todos os pares de figurinhas!</p>
    </div>
</section>

{{-- ─── Tela do jogo ─────────────────────────────────────────── --}}
<section class="screen" id="screen-game">
    <header class="game-bar">
        <div class="pill pill-level">Nível <span id="hud-level">1</span><small id="hud-grid">2×2</small></div>
        <div class="pill pill-time">⏱ <span id="hud-time">00:00</span></div>
        <div class="pill pill-errors">❌ <span id="hud-errors">0</span></div>
    </header>

    <div class="board-wrap">
        <div class="board" id="board" role="grid" aria-label="Tabuleiro do jogo"></div>
    </div>

    <button class="btn btn-ghost btn-restart" id="btn-restart" type="button">↺ Recomeçar</button>
</section>

{{-- ─── Tela de vitória de nível ─────────────────────────────── --}}
<section class="screen" id="screen-win">
    <div class="win-card">
        <div class="win-emoji" aria-hidden="true">🎉</div>
        <h2 class="win-title">Parabéns!</h2>
        <div class="score-badge" id="win-score">A</div>
        <ul class="win-stats">
            <li><span>Tempo</span><strong id="win-time">00:00</strong></li>
            <li><span>Erros</span><strong id="win-errors">0</strong></li>
            <li><span>Pares</span><strong id="win-hits">0</strong></li>
        </ul>
        <button class="btn btn-play" id="btn-next" type="button">Próximo nível →</button>
    </div>
</section>

{{-- ─── Tela final ───────────────────────────────────────────── --}}
<section class="screen" id="screen-final">
    <div class="final-card">
        <div class="mascot" aria-hidden="true">🏆</div>
        <h2 class="win-title">Você venceu tudo!</h2>
        <p class="subtitle">Todos os 7 níveis completos! 🌈</p>
        <ul class="win-stats">
            <li><span>Tempo total</span><strong id="final-time">00:00</strong></li>
            <li><span>Erros totais</span><strong id="final-errors">0</strong></li>
        </ul>
        <button class="btn btn-play" id="btn-again" type="button">Jogar de novo 🔁</button>
    </div>
</section>
@endsection
