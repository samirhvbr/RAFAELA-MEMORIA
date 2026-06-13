# ShvTerm-WEB

Site de marketing e autenticação do **ShvTerm** — cliente SSH/SFTP moderno para equipes e desenvolvedores (estilo Termius).

> Versão atual: leia `version.md` (propagada via `config('app.version')`).

---

## Visão Geral

| Página       | Rota         | Status            |
|---|---|---|
| Home         | `/`          | ativo             |
| Pricing      | `/pricing`   | em desenvolvimento|
| Docs         | `/docs`      | futuro            |
| Login        | `/login`     | ativo             |
| Sign Up      | `/register`  | ativo             |

**Design**: dark mode exclusivo, sem alternância claro/escuro.
**Referência visual**: Cuba Premium Theme (Bootstrap 5) — assets em `public/assets/`.

---

## Stack

| Camada       | Tecnologia                          |
|---|---|
| Backend      | Laravel 12.x / PHP 8.2+             |
| Frontend     | Bootstrap 5 + Cuba Premium Theme    |
| Assets       | Vite 6 + SASS                       |
| Database     | MariaDB / MySQL                     |
| Web Server   | Apache 2.4 (PHP-FPM)                |
| Auth         | Laravel Breeze (sessão + API token) |

---

## Primeiro Setup (instalação limpa)

O scaffold Laravel + assets do Cuba Theme estão em `tmp/cuba-theme/`. Copie para a raiz:

```bash
# 1. Copiar scaffold para a raiz (git e docs já existem — não sobrescrever)
rsync -av --exclude='.git' --exclude='README.md' --exclude='CLAUDE.md' \
  --exclude='CLAUDE.local.md' --exclude='AGENTS.md' \
  --exclude='SECURITY_GUIDELINES.md' --exclude='version.md' \
  --exclude='.claude' --exclude='.gitignore' \
  tmp/cuba-theme/ ./

# 2. Instalar dependências PHP
composer install

# 3. Instalar dependências Node
npm install

# 4. Configurar ambiente
cp .env.example .env
php artisan key:generate

# 5. Criar banco de dados no MariaDB
# mysql -u root -p -e "CREATE DATABASE shvterm_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Editar .env (credenciais do banco, APP_URL, etc.)
# DB_DATABASE=shvterm_dev
# DB_USERNAME=seu_usuario
# DB_PASSWORD=sua_senha

# 7. Rodar migrations
php artisan migrate

# 8. Build dos assets
npm run build
# ou para dev com HMR:
# npm run dev
```

---

## Comandos do Dia a Dia

```bash
# Desenvolvimento (servidor + Vite HMR)
composer run dev          # artisan serve + queue:listen + pail + vite

# Testes
composer run test

# Formatar código
php artisan pint

# Validar sintaxe PHP
php -l app/Http/Controllers/HomeController.php

# Migrations
php artisan migrate
php artisan migrate:status
php artisan migrate:rollback --step=1

# Limpar caches
php artisan optimize:clear
```

---

## Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/          # Thin — apenas request/response
│   │   ├── HomeController.php
│   │   ├── PricingController.php
│   │   └── Auth/             # Login, Register, etc.
│   └── Middleware/
├── Models/
│   └── User.php
└── Services/                 # Lógica de negócio (quando necessário)

resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php     # Layout principal (dark mode)
│   ├── home.blade.php
│   ├── pricing.blade.php
│   ├── docs.blade.php
│   └── auth/
│       ├── login.blade.php
│       └── register.blade.php
├── sass/
│   └── app.scss              # Entry point SASS + variáveis Cuba dark
└── js/
    └── app.js

public/
├── assets/                   # Assets estáticos do Cuba Theme
│   ├── css/
│   ├── js/
│   └── images/
└── build/                    # Output do Vite (gerado, não versionar)

routes/
├── web.php                   # Rotas web públicas
└── auth.php                  # Rotas de autenticação
```

---

## Convenções de Versão e Commit

Versão no arquivo `version.md` (raiz), padrão `X.Y.Z`:

- **X**: versão estável final — manual
- **Y**: mudança estrutural — manual
- **Z**: incremento automático — bump obrigatório ao:
  - Criar tela nova
  - Criar tabela nova
  - Modificar layout
  - Renomear botão/label

**Formato de commit obrigatório**: `X.Y.Z - Descrição em português`

```bash
git commit -m "0.1.1 - Adiciona layout base dark mode e página Home"
```

O bump do `version.md` entra em **um** commit por entrega. Múltiplos commits na mesma versão são normais — só o primeiro inclui o bump.

---

## Ambiente de Desenvolvimento

- **DEV local**: `http://localhost:8000` (via `php artisan serve`)
- **DB local**: `shvterm_dev`
- **Arquivo .env**: nunca versionar; documentar novas variáveis em `.env.example`

---

## Checklist Pré-Commit

- [ ] `php artisan pint` — formatação
- [ ] `php -l` — sintaxe PHP
- [ ] `php artisan view:cache && php artisan view:clear` — validar Blade
- [ ] Dark mode testado no navegador
- [ ] `.env.example` atualizado se adicionou variável nova
- [ ] `version.md` com bump se aplicável
