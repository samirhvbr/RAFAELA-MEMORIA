/**
 * Jogo da Memória da Rafaela — lógica vanilla JS.
 *
 * Consome do Blade:
 *   window.LEVELS      — configuração dos níveis
 *   window.LOG_URL     — endpoint POST /api/log
 *   window.CSRF_TOKEN  — token CSRF
 */

const LEVELS = Array.isArray(window.LEVELS) ? window.LEVELS : [];
const LOG_URL = window.LOG_URL;
const CSRF_TOKEN = window.CSRF_TOKEN;

// >= 32 emojis distintos (nível 7 = 32 pares). Margem extra para robustez.
const ALL_EMOJIS = [
    '🐱', '🐶', '🐸', '🐰', '🦊', '🐼', '🐨', '🦁',
    '🐷', '🐮', '🐧', '🦆', '🦋', '🐢', '🦄', '🐬',
    '🍎', '🍓', '🍦', '🍭', '🎂', '🍩', '🎈', '🎀',
    '⭐', '🌈', '🌸', '🍀', '🌙', '☀️', '❤️', '🎵',
    '🐝', '🐞', '🐙', '🐠', '🌻', '🍉', '🚀', '🎁',
];

const state = {
    currentLevel: 0,
    cards: [],
    flipped: [],
    matched: 0,
    pairs: 0,
    moves: 0,
    errors: 0,
    hits: 0,
    timerSeconds: 0,
    timerInterval: null,
    canFlip: true,
    sessionId: makeUuid(),
    totalTime: 0,
    totalErrors: 0,
};

/* ── Helpers ──────────────────────────────────────────────── */

function makeUuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

function el(id) {
    return document.getElementById(id);
}

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

function formatTime(totalSeconds) {
    const s = Math.max(0, Math.floor(totalSeconds));
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

function showPage(id) {
    document.querySelectorAll('.screen').forEach((s) => s.classList.remove('active'));
    const target = el(id);
    if (target) target.classList.add('active');
}

/* ── Timer ────────────────────────────────────────────────── */

function stopTimer() {
    if (state.timerInterval) {
        clearInterval(state.timerInterval);
        state.timerInterval = null;
    }
}

function startTimer() {
    stopTimer();
    state.timerSeconds = 0;
    updateTimerHud();
    state.timerInterval = setInterval(() => {
        state.timerSeconds++;
        updateTimerHud();
    }, 1000);
}

function updateTimerHud() {
    const t = el('hud-time');
    if (t) t.textContent = formatTime(state.timerSeconds);
}

/* ── Fluxo do jogo ────────────────────────────────────────── */

function startGame() {
    state.currentLevel = 0;
    state.totalTime = 0;
    state.totalErrors = 0;
    state.sessionId = makeUuid();
    loadLevel(0);
}

function loadLevel(idx) {
    const level = LEVELS[idx];
    if (!level) {
        showPage('screen-final');
        return;
    }

    const total = level.rows * level.cols;
    state.currentLevel = idx;
    state.pairs = total / 2;
    state.flipped = [];
    state.matched = 0;
    state.moves = 0;
    state.errors = 0;
    state.hits = 0;
    state.canFlip = true;

    // HUD
    if (el('hud-level')) el('hud-level').textContent = level.id;
    if (el('hud-grid')) el('hud-grid').textContent = level.label;
    if (el('hud-errors')) el('hud-errors').textContent = '0';

    buildBoard(level);
    showPage('screen-game');
    startTimer();
}

function buildBoard(level) {
    const board = el('board');
    if (!board) return;

    board.innerHTML = '';
    board.style.setProperty('--cols', level.cols);
    board.style.setProperty('--rows', level.rows);

    const chosen = shuffle(ALL_EMOJIS.slice()).slice(0, state.pairs);
    if (chosen.length < state.pairs) {
        // Invariante: precisamos de um emoji distinto por par. Falha observável
        // em vez de montar um tabuleiro incompleto (vitória nunca dispararia).
        console.error(
            `ALL_EMOJIS insuficiente: ${ALL_EMOJIS.length} emojis para ${state.pairs} pares (nível ${level.id}).`
        );
        return;
    }
    const deck = shuffle(chosen.concat(chosen));

    state.cards = deck.map((emoji, i) => ({ emoji, matched: false, index: i }));

    deck.forEach((emoji, i) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'card';
        card.dataset.idx = String(i);
        card.setAttribute('aria-label', 'carta virada para baixo');
        card.innerHTML =
            '<span class="card-inner">' +
            '<span class="card-face card-back">★</span>' +
            `<span class="card-face card-front">${emoji}</span>` +
            '</span>';
        card.addEventListener('click', () => onCardClick(i, card));
        board.appendChild(card);
    });
}

function onCardClick(i, cardEl) {
    if (!state.canFlip) return;
    const card = state.cards[i];
    if (!card || card.matched) return;
    if (state.flipped.some((f) => f.i === i)) return; // já virada neste turno

    flip(cardEl, true);
    card.faceUp = true;
    state.flipped.push({ i, el: cardEl });

    if (state.flipped.length === 2) {
        state.canFlip = false;
        state.moves++;
        compare();
    }
}

function compare() {
    const [a, b] = state.flipped;
    const cardA = state.cards[a.i];
    const cardB = state.cards[b.i];

    if (cardA.emoji === cardB.emoji) {
        // Acerto
        cardA.matched = cardB.matched = true;
        state.hits++;
        state.matched++;
        a.el.classList.add('matched');
        b.el.classList.add('matched');
        a.el.setAttribute('aria-label', 'par encontrado');
        b.el.setAttribute('aria-label', 'par encontrado');
        state.flipped = [];
        state.canFlip = true;

        if (state.matched === state.pairs) {
            window.setTimeout(showWin, 450);
        }
    } else {
        // Erro
        state.errors++;
        if (el('hud-errors')) el('hud-errors').textContent = String(state.errors);
        window.setTimeout(() => {
            flip(a.el, false);
            flip(b.el, false);
            cardA.faceUp = cardB.faceUp = false;
            state.flipped = [];
            state.canFlip = true;
        }, 800);
    }
}

function flip(cardEl, faceUp) {
    cardEl.classList.toggle('flipped', faceUp);
}

function calcScore(time, errors, pairs) {
    if (errors === 0 && time < pairs * 3) return 'S';
    if (errors <= 1 && time < pairs * 4) return 'A+';
    if (errors <= 2 && time < pairs * 6) return 'A';
    if (errors <= 4) return 'B';
    return 'C';
}

function showWin() {
    stopTimer();

    const level = LEVELS[state.currentLevel];
    const time = state.timerSeconds;
    const score = calcScore(time, state.errors, state.pairs);

    state.totalTime += time;
    state.totalErrors += state.errors;

    // Preenche a tela de vitória
    if (el('win-score')) {
        el('win-score').textContent = score;
        el('win-score').dataset.score = score.replace('+', 'p').toLowerCase();
    }
    if (el('win-time')) el('win-time').textContent = formatTime(time);
    if (el('win-errors')) el('win-errors').textContent = String(state.errors);
    if (el('win-hits')) el('win-hits').textContent = String(state.hits);

    const isLast = state.currentLevel >= LEVELS.length - 1;
    if (el('btn-next')) {
        el('btn-next').textContent = isLast ? 'Ver resultado 🎉' : 'Próximo nível →';
    }

    saveLog({
        level: level.id,
        grid: level.label,
        time,
        moves: state.moves,
        errors: state.errors,
        hits: state.hits,
        score,
    });

    spawnConfetti();
    showPage('screen-win');
}

function nextLevel() {
    const next = state.currentLevel + 1;
    if (next >= LEVELS.length) {
        if (el('final-time')) el('final-time').textContent = formatTime(state.totalTime);
        if (el('final-errors')) el('final-errors').textContent = String(state.totalErrors);
        spawnConfetti();
        showPage('screen-final');
        return;
    }
    loadLevel(next);
}

/* ── Registro de log (falha silenciosa) ───────────────────── */

async function saveLog(data) {
    if (!LOG_URL) return;
    try {
        await fetch(LOG_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN || '',
            },
            body: JSON.stringify({
                level: data.level,
                grid: data.grid,
                time_seconds: data.time,
                moves: data.moves,
                errors: data.errors,
                hits: data.hits,
                score: data.score,
                status: 'completed',
                session_id: state.sessionId,
            }),
            keepalive: true,
        });
    } catch (err) {
        // Falha silenciosa — nunca interrompe a experiência da Rafaela.
        console.warn('Falha ao registrar log:', err);
    }
}

/* ── Confete ──────────────────────────────────────────────── */

function spawnConfetti() {
    const host = el('confetti');
    if (!host) return;
    const colors = ['#FF6B9D', '#A855F7', '#FFD166', '#06D6A0', '#4CC9F0', '#FF9F1C'];
    const N = 36;
    for (let i = 0; i < N; i++) {
        const piece = document.createElement('span');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + 'vw';
        piece.style.background = colors[i % colors.length];
        piece.style.animationDelay = Math.random() * 0.6 + 's';
        piece.style.animationDuration = 1.8 + Math.random() * 1.4 + 's';
        host.appendChild(piece);
        window.setTimeout(() => piece.remove(), 3600);
    }
}

/* ── Inicialização ────────────────────────────────────────── */

function init() {
    if (el('btn-start')) el('btn-start').addEventListener('click', startGame);
    if (el('btn-next')) el('btn-next').addEventListener('click', nextLevel);
    if (el('btn-restart')) el('btn-restart').addEventListener('click', () => loadLevel(state.currentLevel));
    if (el('btn-again')) el('btn-again').addEventListener('click', startGame);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
