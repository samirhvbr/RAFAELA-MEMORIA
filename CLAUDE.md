# Jogo da Memória da Rafaela — Guia para Agentes de IA

Jogo da memória infantil (Laravel) com painel administrativo de partidas.
Este documento é a referência operacional para agentes de IA neste projeto.

> **Fonte de verdade**: o código manda. Em divergência, use `composer.json` /
> `php artisan about`. A especificação original está em
> [`docs/roteiro-jogo-rafaela.md`](docs/roteiro-jogo-rafaela.md).

---

## 🔄 Antes de começar: `git pull`

**SEMPRE** verifique atualizações remotas antes de escrever ou alterar qualquer coisa neste repositório:

```bash
git pull          # já está pré-autorizado (allow)
```

Trabalhar sobre uma base desatualizada gera conflitos. Puxe primeiro, sempre. Para só inspecionar antes: `git fetch && git status`.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 11 / PHP 8.2+ |
| Frontend | Blade + Vite + CSS/JS **puro** (sem Bootstrap/Tailwind, sem libs JS) |
| Banco | MariaDB (prod) · SQLite (dev opcional) |
| Web server | Nginx + PHP-FPM (Debian 12) |
| Auth admin | Sessão simples (flag), credenciais via `.env` |

Não há autenticação de usuário final — o jogo é público e anônimo. O único
login do sistema é o do **admin**, baseado em flag de sessão.

---

## Versão e Commits

Versão em [`version.md`](version.md) (raiz), lida via `config('app.version')`
(primeiro semver do arquivo). Padrão `X.Y.Z`:

- **Z** sobe a cada entrega: criar tela, criar tabela, mudar layout, renomear
  label/rota, alterar regra do jogo ou config de segurança.
- **Y / X** são manuais (mudança estrutural / release estável).

**Formato obrigatório de commit**: `X.Y.Z - Descrição em português`.
O bump do `version.md` vai em **um** commit por entrega; registre o changelog
no próprio `version.md`.

---

## Convenções de Código

### Organização

- **Controllers finos**: apenas request handling e montagem de resposta.
- **Form Requests** (`app/Http/Requests/`): toda validação de entrada.
- **Models** (`app/Models/`): `$fillable` explícito, casts, scopes.
- **Sem libs JS**: a lógica do jogo é vanilla JS em `resources/js/game.js`.

### Estrutura de views

```
resources/views/
├── layouts/
│   ├── game.blade.php       # layout do jogo (colorido, infantil)
│   └── admin.blade.php      # layout do admin (sóbrio, roxo escuro)
├── game/
│   └── index.blade.php      # telas do jogo (inicial, jogo, vitória, final)
├── admin/
│   ├── login.blade.php
│   └── dashboard.blade.php
└── errors/                  # páginas de erro genéricas (sem stacktrace)
    ├── 404.blade.php
    ├── 419.blade.php
    ├── 429.blade.php
    └── 500.blade.php
```

### Assets (Vite)

- CSS/JS ficam em `resources/css/` e `resources/js/`, carregados via
  `@vite([...])` no layout. **Não** usar `<style>`/`<script>` inline com lógica
  nas views — apenas a injeção de constantes (`window.LEVELS`, etc.) é permitida
  via `@json`.
- Build de produção: `npm run build`. Dev com HMR: `npm run dev`.

---

## Banco de Dados & Migrations

**Banco padrão de produção: MariaDB.** SQLite é permitido apenas para dev local
rápido. PostgreSQL não é usado.

### Idempotência (obrigatória)

```php
public function up(): void
{
    if (! Schema::hasTable('game_logs')) {
        Schema::create('game_logs', function (Blueprint $table) {
            $table->id();
            // ...
            $table->timestamps();
        });
    }
}

public function down(): void
{
    Schema::dropIfExists('game_logs');
}
```

### Boas práticas

- Índices em colunas usadas em `WHERE` / `ORDER BY` (ex.: `level`, `created_at`).
- Evite `ENUM` no schema — use `string` e valide no Form Request.
- Sempre `$table->timestamps()` (`created_at` = momento da partida).
- **NUNCA** `migrate:fresh` em produção. Use `migrate:rollback --step=N`.

---

## UI & Frontend

### Identidade visual

Dois contextos visuais **distintos** (não há dark mode global aqui):

| Contexto | Paleta | Tom |
|---|---|---|
| **Jogo** | rosa `#FF6B9D`, roxo `#A855F7`, fundo `#FFF0F5` | infantil, lúdico, animado |
| **Admin** | header roxo escuro `#2D1B69`, conteúdo branco | sóbrio, funcional |

- Fonte do jogo: `'Segoe UI', 'Comic Sans MS', cursive`.
- Animações do jogo: flip 3D (`transform: rotateY`), bounce do mascote, confete.
- Responsividade do tabuleiro: `min(90vw, 550px)`, células proporcionais.
- **Nenhum framework CSS.** CSS puro para controle total.

### Regras de Blade

- Output sempre escapado com `{{ }}`. `{!! !!}` é proibido com dado de usuário.
- Dados do servidor para o JS: `@json($var)` (nunca interpolação em `<script>`).
- `@csrf` em **todos** os formulários.
- `@vite([...])` no layout para CSS/JS.

---

## Registro de Partidas (fluxo central)

```
Rafaela completa um nível
   → game.js chama saveLog(data)
   → POST /api/log (JSON + header X-CSRF-TOKEN, rota com throttle)
   → StoreGameLogRequest valida
   → GameLogController grava GameLog (+ ip/user-agent do servidor)
   → resposta {"ok": true}
   → a tela de vitória aparece independentemente do resultado da API
```

O frontend **nunca** trava por falha de log — `saveLog` falha em silêncio.

---

## Segurança

Regras completas em [SECURITY_GUIDELINES.md](SECURITY_GUIDELINES.md). Resumo:

- **Admin**: senha em **hash** no `.env` (`ADMIN_PASSWORD_HASH`), comparada com
  `Hash::check`/`hash_equals`; rate-limit no login; `session()->regenerate()`
  no login e `invalidate()` + `regenerateToken()` no logout.
- **CSRF**: `@csrf` em forms; `/api/log` usa header `X-CSRF-TOKEN`.
- **Mass Assignment**: `$fillable` explícito; IP/user-agent definidos no servidor,
  **fora** do input do usuário.
- **Validação**: sempre via Form Request; nunca confiar no JS.
- **Throttle**: `/api/log` limitado; login admin limitado.
- **SQL/XSS**: Eloquent/Query Builder parametrizado; `{{ }}` no output.
- **Prod**: `APP_DEBUG=false`, `.env` fora do git, headers de segurança no Nginx.

---

## Comandos Rápidos

```bash
php artisan serve
npm run dev / npm run build
php artisan migrate / migrate:status / migrate:rollback --step=1
php artisan pint            # se instalado
php artisan about
php -l caminho/arquivo.php
php artisan optimize:clear
```

---

## DEV Files (não vão para produção)

`.env`, `.env.*`, `storage/`, `bootstrap/cache/`, `.git/`, `vendor/`,
`node_modules/`, `public/build/`, `.claude/`, `README.md`,
`CLAUDE.md`, `SECURITY_GUIDELINES.md`, `.vscode/`.

---

## Checklist Pré-Commit

- [ ] `php -l` nos arquivos PHP alterados
- [ ] `php artisan view:cache && php artisan view:clear` — valida Blade
- [ ] Jogo testado no navegador (virada, vitória, avanço de nível, log)
- [ ] Migrations com `hasTable()`/`dropIfExists()` e `down()` funcional
- [ ] `$fillable` explícito em models
- [ ] `@csrf` em todos os formulários; `/api/log` com CSRF no header
- [ ] `.env.example` atualizado se adicionou variável
- [ ] `version.md` com bump + changelog se aplicável
- [ ] `APP_DEBUG` continua `false` em PROD
