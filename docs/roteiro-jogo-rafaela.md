# Roteiro de Desenvolvimento — Jogo da Memória da Rafaela
> Documento de especificação para agente de desenvolvimento
> Stack: Ubuntu Linux + PHP 8.2 + Laravel 11 + SQLite (ou MySQL) + Blade + Vite

---

## 1. Visão Geral

Aplicação web de jogo da memória infantil com painel administrativo para acompanhamento de jogadas.

**Funcionalidades principais:**
- Jogo da memória com progressão de dificuldade em 7 níveis
- Emojis infantis como peças do jogo
- Registro automático de cada partida (IP, tempo, erros, acertos, nível)
- Painel admin protegido por login para visualização dos logs

---

## 2. Stack Técnica

| Camada | Tecnologia |
|---|---|
| Servidor | Ubuntu 24.04 LTS |
| Runtime | PHP 8.2+ |
| Framework | Laravel 11 |
| Frontend | Blade + Vite + CSS puro (sem framework CSS) |
| Banco de dados | SQLite (dev) / MySQL 8 (prod) |
| Web server | Nginx + PHP-FPM |
| Dependências JS | Nenhuma (vanilla JS) |
| Deploy | Git + Artisan |

---

## 3. Estrutura de Diretórios Esperada

```
/var/www/jogo-rafaela/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── GameController.php       ← lógica do jogo (SPA)
│   │   │   ├── GameLogController.php    ← salvar log de partida
│   │   │   └── Admin/
│   │   │       ├── AuthController.php   ← login admin
│   │   │       └── DashboardController.php ← painel de logs
│   │   └── Middleware/
│   │       └── AdminAuth.php            ← proteção das rotas admin
│   └── Models/
│       └── GameLog.php
├── database/
│   └── migrations/
│       └── xxxx_create_game_logs_table.php
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── game.blade.php           ← layout do jogo
│   │   │   └── admin.blade.php          ← layout do admin
│   │   ├── game/
│   │   │   └── index.blade.php          ← tela principal do jogo
│   │   └── admin/
│   │       ├── login.blade.php
│   │       └── dashboard.blade.php
│   ├── css/
│   │   ├── game.css
│   │   └── admin.css
│   └── js/
│       ├── game.js                      ← toda lógica do jogo
│       └── admin.js
├── routes/
│   └── web.php
└── public/
    └── images/
        └── cards/                       ← (opcional) imagens customizadas
```

---

## 4. Banco de Dados

### 4.1 Migration: `game_logs`

```php
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedTinyInteger('level');          // 1–7
    $table->string('grid', 10);                    // ex: "4×4"
    $table->unsignedSmallInteger('time_seconds');  // tempo da partida
    $table->unsignedSmallInteger('moves');         // total de jogadas (pares virados)
    $table->unsignedSmallInteger('errors');        // tentativas erradas
    $table->unsignedSmallInteger('hits');          // pares encontrados
    $table->string('score', 5);                    // "S", "A+", "A", "B", "C"
    $table->string('status', 20)->default('completed'); // completed | abandoned
    $table->string('ip_address', 45)->nullable();  // IPv4 ou IPv6
    $table->string('user_agent')->nullable();
    $table->string('session_id', 64)->nullable();  // identificar sessão contínua
    $table->timestamps();                           // created_at = momento da partida
});
```

### 4.2 Model: `GameLog.php`

```php
protected $fillable = [
    'level', 'grid', 'time_seconds', 'moves',
    'errors', 'hits', 'score', 'status',
    'ip_address', 'user_agent', 'session_id',
];

protected $casts = [
    'level'        => 'integer',
    'time_seconds' => 'integer',
    'moves'        => 'integer',
    'errors'       => 'integer',
    'hits'         => 'integer',
];
```

---

## 5. Rotas — `routes/web.php`

```php
// ── JOGO ─────────────────────────────────────────────
Route::get('/', [GameController::class, 'index'])->name('game');
Route::post('/api/log', [GameLogController::class, 'store'])->name('game.log');

// ── ADMIN ─────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Login (sem middleware)
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

    // Painel (protegido)
    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::delete('/logs',   [DashboardController::class, 'clearLogs'])->name('logs.clear');
        Route::get('/logs/export', [DashboardController::class, 'export'])->name('logs.export');
    });
});
```

---

## 6. Controllers

### 6.1 `GameController.php`

Responsável por servir a view do jogo. Pode injetar as configurações de nível via `@json` no Blade para o JS consumir.

```php
public function index()
{
    $levels = [
        ['id' => 1, 'rows' => 2, 'cols' => 2, 'label' => '2×2'],
        ['id' => 2, 'rows' => 2, 'cols' => 3, 'label' => '2×3'],
        ['id' => 3, 'rows' => 4, 'cols' => 4, 'label' => '4×4'],
        ['id' => 4, 'rows' => 4, 'cols' => 5, 'label' => '4×5'],
        ['id' => 5, 'rows' => 6, 'cols' => 6, 'label' => '6×6'],
        ['id' => 6, 'rows' => 6, 'cols' => 8, 'label' => '6×8'],
        ['id' => 7, 'rows' => 8, 'cols' => 8, 'label' => '8×8'],
    ];

    return view('game.index', compact('levels'));
}
```

### 6.2 `GameLogController.php`

Recebe `POST /api/log` com JSON do frontend ao completar cada nível.

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'level'        => 'required|integer|min:1|max:7',
        'grid'         => 'required|string|max:10',
        'time_seconds' => 'required|integer|min:0',
        'moves'        => 'required|integer|min:0',
        'errors'       => 'required|integer|min:0',
        'hits'         => 'required|integer|min:0',
        'score'        => 'required|string|max:5',
        'status'       => 'required|string|in:completed,abandoned',
        'session_id'   => 'nullable|string|max:64',
    ]);

    GameLog::create(array_merge($validated, [
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]));

    return response()->json(['ok' => true]);
}
```

> **CSRF:** A rota `/api/log` deve ser excluída do CSRF via `bootstrap/app.php`
> ou usar o header `X-CSRF-TOKEN` no fetch do frontend.

### 6.3 `Admin/AuthController.php`

Credenciais hardcoded em `.env` para não ficarem no código-fonte:

```php
// .env
ADMIN_EMAIL=samirhv@gmail.com
ADMIN_PASSWORD=samir123123
```

```php
public function login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (
        $request->email    === config('admin.email') &&
        $request->password === config('admin.password')
    ) {
        $request->session()->put('admin_logged_in', true);
        return redirect()->route('admin.dashboard');
    }

    return back()->withErrors(['email' => 'Credenciais inválidas.']);
}

public function logout(Request $request)
{
    $request->session()->forget('admin_logged_in');
    return redirect()->route('admin.login');
}
```

Criar `config/admin.php`:
```php
return [
    'email'    => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
```

### 6.4 `Admin/DashboardController.php`

```php
public function index(Request $request)
{
    $query = GameLog::query();

    // Filtros opcionais
    if ($request->filled('level')) {
        $query->where('level', $request->level);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    $logs  = $query->latest()->paginate(50);
    $stats = $this->buildStats();

    return view('admin.dashboard', compact('logs', 'stats'));
}

private function buildStats(): array
{
    return [
        'total'       => GameLog::count(),
        'completed'   => GameLog::where('status', 'completed')->count(),
        'avg_time'    => round(GameLog::avg('time_seconds') ?? 0),
        'avg_errors'  => round(GameLog::avg('errors') ?? 1, 1),
        'max_level'   => GameLog::max('level') ?? 0,
        'unique_ips'  => GameLog::distinct('ip_address')->count('ip_address'),
        'today'       => GameLog::whereDate('created_at', today())->count(),
    ];
}

public function clearLogs(Request $request)
{
    GameLog::truncate();
    return back()->with('success', 'Registros apagados com sucesso.');
}

public function export()
{
    $logs = GameLog::latest()->get();
    $csv  = "id,level,grid,time_seconds,moves,errors,hits,score,status,ip_address,created_at\n";

    foreach ($logs as $log) {
        $csv .= implode(',', [
            $log->id, $log->level, $log->grid, $log->time_seconds,
            $log->moves, $log->errors, $log->hits, $log->score,
            $log->status, $log->ip_address, $log->created_at,
        ]) . "\n";
    }

    return response($csv, 200, [
        'Content-Type'        => 'text/csv',
        'Content-Disposition' => 'attachment; filename="logs-rafaela-' . now()->format('Y-m-d') . '.csv"',
    ]);
}
```

### 6.5 Middleware `AdminAuth.php`

```php
public function handle(Request $request, Closure $next): Response
{
    if (! $request->session()->get('admin_logged_in')) {
        return redirect()->route('admin.login');
    }

    return $next($request);
}
```

Registrar em `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['admin.auth' => AdminAuth::class]);
})
```

---

## 7. Views Blade

### 7.1 `layouts/game.blade.php`

Layout limpo com fundo colorido. Incluir:
- `@vite(['resources/css/game.css', 'resources/js/game.js'])`
- Meta viewport
- Variável JS com os níveis: `<script>window.LEVELS = @json($levels);</script>`

### 7.2 `game/index.blade.php`

Estrutura HTML das telas (tela inicial, tela de jogo, tela de vitória, tela final).
Todo o HTML das telas pode ser transposto do protótipo HTML já existente.

O Blade deve expor para o JS:
```blade
<script>
    window.LEVELS    = @json($levels);
    window.LOG_URL   = "{{ route('game.log') }}";
    window.CSRF_TOKEN = "{{ csrf_token() }}";
</script>
```

### 7.3 `admin/login.blade.php`

Formulário simples com `@error` para feedback de validação.
Exibir mensagem de erro se credenciais inválidas.

### 7.4 `admin/dashboard.blade.php`

Deve conter:
- Cards de estatísticas no topo (total partidas, concluídas, tempo médio, erros médios, nível máximo, IPs únicos, jogadas hoje)
- Filtros por nível e por período (date_from / date_to)
- Tabela paginada com colunas: #, Data/Hora, IP, User Agent resumido, Nível, Grid, Tempo, Jogadas, Erros, Acertos, Nota, Status
- Botão "Exportar CSV"
- Botão "Limpar Registros" (com confirmação JS antes de submeter)
- Paginação Laravel (`{{ $logs->links() }}`)

---

## 8. Frontend JavaScript — `game.js`

O JS é vanilla (sem frameworks). Estrutura sugerida:

### 8.1 Constantes

```javascript
// Injetadas pelo Blade
const LEVELS     = window.LEVELS;
const LOG_URL    = window.LOG_URL;
const CSRF_TOKEN = window.CSRF_TOKEN;

const ALL_EMOJIS = [
  '🐱','🐶','🐸','🐰','🦊','🐼','🐨','🦁',
  '🐷','🐮','🐧','🦆','🦋','🐢','🦄','🐬',
  '🍎','🍓','🍦','🍭','🎂','🍩','🎈','🎀',
  '⭐','🌈','🌸','🍀','🌙','☀️','❤️','🎵',
];
```

### 8.2 Estado do jogo

```javascript
let state = {
    currentLevel : 0,
    cards        : [],
    flipped      : [],
    matched      : 0,
    moves        : 0,
    errors       : 0,
    timerSeconds : 0,
    timerInterval: null,
    canFlip      : true,
    sessionId    : crypto.randomUUID(),
};
```

### 8.3 Funções principais

| Função | Responsabilidade |
|---|---|
| `startGame()` | Zera estado, chama `loadLevel(0)`, troca tela |
| `loadLevel(idx)` | Monta tabuleiro, embaralha emojis, inicia timer |
| `flipCard(card, idx)` | Lógica de virada, comparação e contagem |
| `showWin()` | Calcula nota, exibe tela de vitória, chama `saveLog()` |
| `nextLevel()` | Avança nível ou exibe tela final |
| `calcScore(time, errors, pairs)` | Retorna string de nota (S/A+/A/B/C) |
| `saveLog(data)` | POST para `/api/log` com fetch |
| `showPage(id)` | Troca de tela (remove/adiciona classe `active`) |

### 8.4 `saveLog` — chamada à API Laravel

```javascript
async function saveLog(data) {
    try {
        await fetch(LOG_URL, {
            method  : 'POST',
            headers : {
                'Content-Type' : 'application/json',
                'X-CSRF-TOKEN' : CSRF_TOKEN,
            },
            body: JSON.stringify({
                level        : data.level,
                grid         : data.grid,
                time_seconds : data.time,
                moves        : data.moves,
                errors       : data.errors,
                hits         : data.hits,
                score        : data.score,
                status       : 'completed',
                session_id   : state.sessionId,
            }),
        });
    } catch (err) {
        console.warn('Falha ao registrar log:', err);
        // Falha silenciosa — não interrompe a experiência da Rafaela
    }
}
```

---

## 9. CSS — Guia de Estilo

O CSS deve seguir o protótipo HTML já desenvolvido. Pontos importantes:

- **Paleta principal:** rosa `#FF6B9D`, roxo `#A855F7`, fundo `#FFF0F5`
- **Fonte:** `'Segoe UI', 'Comic Sans MS', cursive` — tom infantil
- **Animações:** flip 3D das cartas via `transform: rotateY`, bounce no mascote, confetes na vitória
- **Responsividade:** o tabuleiro deve se adaptar ao tamanho da tela com `min(90vw, 550px)` e calcular o tamanho das células proporcionalmente
- **Nenhum framework CSS** (Bootstrap, Tailwind etc.) — CSS puro para controle total

---

## 10. Configuração do Servidor

### 10.1 Nginx — `/etc/nginx/sites-available/jogo-rafaela`

```nginx
server {
    listen 80;
    server_name seudominio.com.br;   # ajustar

    root /var/www/jogo-rafaela/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.ht { deny all; }

    # Segurança: não expor arquivos sensíveis
    location ~* \.(env|log|git)$ { deny all; }
}
```

### 10.2 Permissões

```bash
chown -R www-data:www-data /var/www/jogo-rafaela
chmod -R 755 /var/www/jogo-rafaela
chmod -R 775 /var/www/jogo-rafaela/storage
chmod -R 775 /var/www/jogo-rafaela/bootstrap/cache
```

### 10.3 `.env` mínimo

```env
APP_NAME="Jogo da Rafaela"
APP_ENV=production
APP_KEY=        # gerar com artisan key:generate
APP_DEBUG=false
APP_URL=https://seudominio.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jogo_rafaela
DB_USERNAME=rafaela_user
DB_PASSWORD=senha_segura_aqui

SESSION_DRIVER=file
SESSION_LIFETIME=120

ADMIN_EMAIL=samirhv@gmail.com
ADMIN_PASSWORD=samir123123
```

---

## 11. Sequência de Instalação

```bash
# 1. Clonar/enviar código para o servidor
cd /var/www
git clone https://github.com/seu-usuario/jogo-rafaela.git
cd jogo-rafaela

# 2. Instalar dependências PHP
composer install --no-dev --optimize-autoloader

# 3. Instalar e buildar assets JS/CSS
npm install
npm run build

# 4. Configurar ambiente
cp .env.example .env
php artisan key:generate
# (editar .env com os valores corretos)

# 5. Banco de dados
php artisan migrate --force

# 6. Otimizar para produção
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Nginx
ln -s /etc/nginx/sites-available/jogo-rafaela /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

# 8. Permissões finais
chown -R www-data:www-data storage bootstrap/cache
```

---

## 12. Níveis do Jogo — Referência

| Nível | Grid | Total de Cartas | Pares |
|---|---|---|---|
| 1 | 2×2 | 4 | 2 |
| 2 | 2×3 | 6 | 3 |
| 3 | 4×4 | 16 | 8 |
| 4 | 4×5 | 20 | 10 |
| 5 | 6×6 | 36 | 18 |
| 6 | 6×8 | 48 | 24 |
| 7 | 8×8 | 64 | 32 |

> **Atenção:** Os grids 2×3 e 4×5 têm número ímpar de colunas × linhas, resultando em número par de cartas — OK para o jogo.

---

## 13. Sistema de Notas

| Nota | Critério |
|---|---|
| **S** | 0 erros e tempo < pares × 3s |
| **A+** | ≤ 1 erro e tempo < pares × 4s |
| **A** | ≤ 2 erros e tempo < pares × 6s |
| **B** | ≤ 4 erros |
| **C** | Mais de 4 erros |

---

## 14. Registro de Logs — Fluxo Completo

```
Rafaela completa um nível
        ↓
JS chama saveLog(data)
        ↓
POST /api/log  (JSON + CSRF token no header)
        ↓
GameLogController@store
        ↓
Validação dos campos
        ↓
GameLog::create([...dados + $request->ip() + $request->userAgent()])
        ↓
Registro salvo no banco
        ↓
Response JSON {"ok": true}
        ↓
JS exibe tela de vitória (independente do resultado da API)
```

---

## 15. Painel Admin — Telas

### Tela de Login
- Formulário com email e senha
- Feedback de erro com `@error` Blade
- Redirecionamento para dashboard após login bem-sucedido
- Sem "lembrar acesso" — sessão simples

### Dashboard
- **Cards de Stats:** Total de Partidas, Concluídas, Tempo Médio, IPs Únicos, Partidas Hoje, Nível Máximo Atingido
- **Filtros:** Nível (select 1–7), Data de/até, botão Filtrar e Limpar
- **Tabela:** paginada (50 por página), colunas ordenadas por data desc
- **Ações:** Exportar CSV, Limpar todos os registros (com `confirm()` JS)
- **Design:** sóbrio, roxo escuro `#2D1B69` no header, branco no conteúdo

---

## 16. Segurança

- Credenciais admin apenas no `.env`, lidas via `config/admin.php`
- Rota `/api/log` com rate limiting via `throttle:60,1` para evitar spam
- Middleware `AdminAuth` em todas as rotas do painel
- Validação rigorosa no `GameLogController` antes de salvar
- CSRF token em todas as chamadas POST do frontend
- `APP_DEBUG=false` em produção
- `.env` no `.gitignore`

---

## 17. Melhorias Futuras (fora do escopo inicial)

- [ ] Foto/avatar personalizado da Rafaela na tela inicial
- [ ] Temas de emojis selecionáveis (animais, comidas, espaço, etc.)
- [ ] Ranking dos melhores tempos por nível
- [ ] Som de virada de carta e efeito de vitória (Web Audio API)
- [ ] Modo "desafio": jogar todos os 7 níveis sem parar, com score cumulativo
- [ ] Suporte a SSL via Let's Encrypt (Certbot)
- [ ] Gráficos de evolução no painel admin (Chart.js)
- [ ] Notificação por e-mail quando Rafaela completar todos os níveis

---

## 18. Referência: Protótipo HTML

Existe um protótipo funcional em `protótipo/jogo-memoria-rafaela.html` com todo o CSS e JS do jogo já desenvolvido. O agente deve usar esse arquivo como base para:

1. Transcrever o HTML das telas para as views Blade
2. Mover o CSS para `resources/css/game.css`
3. Mover o JS para `resources/js/game.js`, adaptando para consumir `window.LEVELS`, `window.LOG_URL` e `window.CSRF_TOKEN` em vez do localStorage

---

*Fim do roteiro — versão 1.0*
