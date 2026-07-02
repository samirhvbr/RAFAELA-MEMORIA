# Rafaela's Memory Game

Web application for a **children's memory game** with an admin panel for
tracking matches.

> Current version: read [`version.md`](version.md) (propagated via `config('app.version')`).
> Full specification: [`docs/roteiro-jogo-rafaela.md`](docs/roteiro-jogo-rafaela.md).

---

## Overview

| Feature | Description |
|---|---|
| Memory game | 7 difficulty levels (2×2 → 8×8), tiles with children's emojis |
| Grading system | S / A+ / A / B / C based on time and mistakes |
| Match logging | Each match recorded (IP, time, mistakes, hits, level, grade) |
| Admin panel | Protected login + dashboard with statistics, filters and CSV export |

**Target audience:** one child (Rafaela). The game experience is never
interrupted by a technical failure — logging fails silently.

---

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 / PHP 8.2+ |
| Frontend | Blade + Vite + **pure CSS/JS** (no CSS framework, no JS libs) |
| Database | MariaDB (prod) — SQLite supported for local dev |
| Server | Debian 12 (Bookworm) |
| Web server | Nginx + PHP-FPM |
| Deploy | Git + Composer + Artisan |

---

## First Setup (clean install)

Local prerequisites: PHP 8.2+, Composer, Node 18+ and (optional) MariaDB.

```bash
# 1. Install PHP dependencies
composer install

# 2. Install Node dependencies and build assets
npm install
npm run build        # or: npm run dev (HMR in development)

# 3. Configure the environment
cp .env.example .env
php artisan key:generate

# 4. Set the admin password (do NOT commit the real password)
#    Generate the hash and paste it into ADMIN_PASSWORD_HASH in .env:
php artisan tinker --execute="echo Hash::make('SUA_SENHA_AQUI');"

# 5. Database
#    MariaDB:  mysql -u root -p -e "CREATE DATABASE jogo_rafaela CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#    (or, for quick dev, use SQLite — see .env.example)
php artisan migrate

# 6. Run locally
php artisan serve    # http://localhost:8000
```

Admin login at `http://localhost:8000/admin/login`.

---

## Day-to-Day Commands

```bash
php artisan serve           # development server
npm run dev                 # Vite with HMR
npm run build               # production build of the assets

php artisan migrate         # runs migrations
php artisan migrate:status
php artisan migrate:rollback --step=1

php artisan pint            # formats the code (if installed)
php -l app/Http/Controllers/GameController.php   # validates syntax
php artisan optimize:clear  # clears caches
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── GameController.php          # serves the game SPA
│   │   ├── GameLogController.php       # POST /api/log — records the match
│   │   └── Admin/
│   │       ├── AuthController.php       # admin login/logout
│   │       └── DashboardController.php  # logs panel
│   ├── Middleware/
│   │   └── AdminAuth.php                # protects the /admin routes
│   └── Requests/
│       ├── StoreGameLogRequest.php      # match log validation
│       └── AdminLoginRequest.php        # login validation
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
config/admin.php                          # admin credentials (via .env)
docs/DEPLOY.md                            # Debian + Nginx + MariaDB deploy guide
```

---

## Version and Commit Convention

Version in [`version.md`](version.md), `X.Y.Z` standard (details and triggers in the
file itself). Summary:

- **X** stable release (manual) · **Y** structural change (manual) ·
  **Z** automatic on each delivery (screen/table/layout/label/rule/security).
- Commit format: `X.Y.Z - Description in Portuguese`.
- The `version.md` bump goes in **one** commit per delivery.

---

## Documentation

| File | Contents |
|---|---|
| [`version.md`](version.md) | Current version, convention and changelog |
| [`CLAUDE.md`](CLAUDE.md) | Operational guide for AI agents |
| [`SECURITY_GUIDELINES.md`](SECURITY_GUIDELINES.md) | Security guidelines |
| [`docs/roteiro-jogo-rafaela.md`](docs/roteiro-jogo-rafaela.md) | Game specification |
| [`docs/DEPLOY.md`](docs/DEPLOY.md) | Production deploy |

---

## Pre-Commit Checklist

- [ ] `php artisan pint` — formatting (if available)
- [ ] `php -l` on the changed PHP files
- [ ] `php artisan view:cache && php artisan view:clear` — validates Blade
- [ ] Game tested in the browser (card flip, victory, log)
- [ ] `.env.example` updated if you added a new variable
- [ ] `version.md` bumped + changelog if applicable
- [ ] `@csrf` on all forms
