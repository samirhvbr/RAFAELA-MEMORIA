# Jogo da Memória da Rafaela

Aplicação web de **jogo da memória infantil** com painel administrativo para
acompanhamento das partidas.

> Versão atual: leia [`version.md`](version.md) (propagada via `config('app.version')`).
> Especificação completa: [`docs/roteiro-jogo-rafaela.md`](docs/roteiro-jogo-rafaela.md).

---

## Visão Geral

| Funcionalidade | Descrição |
|---|---|
| Jogo da memória | 7 níveis de dificuldade (2×2 → 8×8), peças com emojis infantis |
| Sistema de notas | S / A+ / A / B / C conforme tempo e erros |
| Registro de partidas | Cada partida gravada (IP, tempo, erros, acertos, nível, nota) |
| Painel admin | Login protegido + dashboard com estatísticas, filtros e exportação CSV |

**Público-alvo:** uma criança (a Rafaela). A experiência do jogo nunca é
interrompida por falha técnica — o registro de log falha de forma silenciosa.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 11 / PHP 8.2+ |
| Frontend | Blade + Vite + **CSS/JS puro** (sem framework CSS, sem libs JS) |
| Banco de dados | MariaDB (prod) — SQLite suportado para dev local |
| Servidor | Debian 12 (Bookworm) |
| Web server | Nginx + PHP-FPM |
| Deploy | Git + Composer + Artisan |

---

## Primeiro Setup (instalação limpa)

Pré-requisitos locais: PHP 8.2+, Composer, Node 18+ e (opcional) MariaDB.

```bash
# 1. Instalar dependências PHP
composer install

# 2. Instalar dependências Node e buildar assets
npm install
npm run build        # ou: npm run dev (HMR em desenvolvimento)

# 3. Configurar ambiente
cp .env.example .env
php artisan key:generate

# 4. Definir a senha do admin (NÃO commite a senha real)
#    Gere o hash e cole em ADMIN_PASSWORD_HASH no .env:
php artisan tinker --execute="echo Hash::make('SUA_SENHA_AQUI');"

# 5. Banco de dados
#    MariaDB:  mysql -u root -p -e "CREATE DATABASE jogo_rafaela CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#    (ou, para dev rápido, use SQLite — ver .env.example)
php artisan migrate

# 6. Rodar localmente
php artisan serve    # http://localhost:8000
```

Login do admin em `http://localhost:8000/admin/login`.

---

## Comandos do Dia a Dia

```bash
php artisan serve           # servidor de desenvolvimento
npm run dev                 # Vite com HMR
npm run build               # build de produção dos assets

php artisan migrate         # roda migrations
php artisan migrate:status
php artisan migrate:rollback --step=1

php artisan pint            # formata o código (se instalado)
php -l app/Http/Controllers/GameController.php   # valida sintaxe
php artisan optimize:clear  # limpa caches
```

---

## Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── GameController.php          # serve a SPA do jogo
│   │   ├── GameLogController.php       # POST /api/log — grava a partida
│   │   └── Admin/
│   │       ├── AuthController.php       # login/logout do admin
│   │       └── DashboardController.php  # painel de logs
│   ├── Middleware/
│   │   └── AdminAuth.php                # protege as rotas /admin
│   └── Requests/
│       ├── StoreGameLogRequest.php      # validação do log de partida
│       └── AdminLoginRequest.php        # validação do login
├── Models/
│   └── GameLog.php
└── Providers/
    └── AppServiceProvider.php           # rate limiters

database/migrations/
└── xxxx_create_game_logs_table.php

resources/
├── views/
│   ├── layouts/{game,admin}.blade.php
│   ├── game/index.blade.php
│   ├── admin/{login,dashboard}.blade.php
│   └── errors/{404,500,419,429}.blade.php
├── css/{game,admin}.css
└── js/{game,admin}.js

routes/web.php
config/admin.php                          # credenciais do admin (via .env)
docs/DEPLOY.md                            # guia de deploy Debian + Nginx + MariaDB
```

---

## Convenção de Versão e Commit

Versão em [`version.md`](version.md), padrão `X.Y.Z` (detalhes e gatilhos no
próprio arquivo). Resumo:

- **X** release estável (manual) · **Y** mudança estrutural (manual) ·
  **Z** automático a cada entrega (tela/tabela/layout/label/regra/segurança).
- Formato de commit: `X.Y.Z - Descrição em português`.
- O bump do `version.md` vai em **um** commit por entrega.

---

## Documentação

| Arquivo | Conteúdo |
|---|---|
| [`version.md`](version.md) | Versão atual, convenção e changelog |
| [`CLAUDE.md`](CLAUDE.md) | Guia operacional para agentes de IA |
| [`SECURITY_GUIDELINES.md`](SECURITY_GUIDELINES.md) | Diretrizes de segurança |
| [`docs/roteiro-jogo-rafaela.md`](docs/roteiro-jogo-rafaela.md) | Especificação do jogo |
| [`docs/DEPLOY.md`](docs/DEPLOY.md) | Deploy em produção |

---

## Checklist Pré-Commit

- [ ] `php artisan pint` — formatação (se disponível)
- [ ] `php -l` nos arquivos PHP alterados
- [ ] `php artisan view:cache && php artisan view:clear` — valida Blade
- [ ] Jogo testado no navegador (virada de cartas, vitória, log)
- [ ] `.env.example` atualizado se adicionou variável nova
- [ ] `version.md` com bump + changelog se aplicável
- [ ] `@csrf` em todos os formulários
