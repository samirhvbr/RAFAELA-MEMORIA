# Deploy — Jogo da Memória da Rafaela

Guia de publicação em **Debian 12 (Bookworm)** com **Nginx + PHP 8.2-FPM +
MariaDB**. Alvo de instalação: `/var/www/jogo-rafaela`.

> Convenções e segurança: ver [`CLAUDE.md`](../CLAUDE.md) e
> [`SECURITY_GUIDELINES.md`](../SECURITY_GUIDELINES.md).

---

## 1. Pré-requisitos do servidor

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.2 + extensões exigidas pelo Laravel 11
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl \
    nginx mariadb-server git unzip curl

# Composer (global)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS (para buildar os assets com Vite)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

> Em Debian 12 o pacote PHP padrão já é o 8.2. Se precisar de outra versão,
> use o repositório `deb.sury.org/php`.

---

## 2. Banco de dados (MariaDB)

```bash
sudo mysql_secure_installation   # defina senha do root, remova anônimos, etc.

sudo mysql -u root -p
```

```sql
CREATE DATABASE jogo_rafaela CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rafaela_user'@'127.0.0.1' IDENTIFIED BY 'TROQUE_POR_SENHA_FORTE';
GRANT ALL PRIVILEGES ON jogo_rafaela.* TO 'rafaela_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. Código

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/SEU_USUARIO/jogo-rafaela.git jogo-rafaela
sudo chown -R $USER:www-data /var/www/jogo-rafaela
cd /var/www/jogo-rafaela

# Dependências PHP (produção)
composer install --no-dev --optimize-autoloader

# Assets (Vite → public/build)
npm ci
npm run build
```

---

## 4. Ambiente (`.env`)

```bash
cp .env.example .env
php artisan key:generate
```

Edite o `.env` com os valores de produção:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SEU_DOMINIO.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=jogo_rafaela
DB_USERNAME=rafaela_user
DB_PASSWORD=A_SENHA_FORTE_DO_PASSO_2

SESSION_SECURE_COOKIE=true        # HTTPS em produção

ADMIN_EMAIL=samirhv@me.com
ADMIN_PASSWORD_HASH=              # gere no passo seguinte
```

Gere o **hash da senha do admin** (nunca guarde a senha em texto puro):

```bash
php artisan tinker --execute="echo Hash::make('SUA_SENHA_DO_ADMIN');"
# copie o hash (começa com \$2y\$...) para ADMIN_PASSWORD_HASH no .env
```

---

## 5. Migrations e cache de produção

```bash
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Sempre que editar o `.env` rode `php artisan config:clear` (ou
> `config:cache` de novo), pois a config fica em cache.

---

## 6. Permissões

```bash
sudo chown -R www-data:www-data /var/www/jogo-rafaela/storage \
    /var/www/jogo-rafaela/bootstrap/cache
sudo find /var/www/jogo-rafaela/storage -type d -exec chmod 775 {} \;
sudo find /var/www/jogo-rafaela/bootstrap/cache -type d -exec chmod 775 {} \;
```

---

## 7. Nginx

```bash
sudo cp deploy/nginx/jogo-rafaela.conf /etc/nginx/sites-available/jogo-rafaela
# edite o server_name dentro do arquivo
sudo ln -s /etc/nginx/sites-available/jogo-rafaela /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default   # opcional
sudo nginx -t
sudo systemctl reload nginx
```

---

## 8. HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d SEU_DOMINIO.com.br
```

Depois do certificado ativo, **descomente** a linha `Strict-Transport-Security`
no vhost e confirme `SESSION_SECURE_COOKIE=true` no `.env`.

---

## 9. Verificação pós-deploy

- [ ] `https://SEU_DOMINIO` abre o jogo e as cartas viram.
- [ ] Completar um nível grava uma linha (confira em `/admin/dashboard`).
- [ ] `/admin/login` aceita o e-mail e a senha configurados.
- [ ] Credenciais erradas mostram "Credenciais inválidas" e, após várias
      tentativas, bloqueiam (429 / mensagem de limite).
- [ ] `curl -I https://SEU_DOMINIO/.env` retorna **403/404** (nunca o arquivo).
- [ ] `APP_DEBUG=false` (erros não mostram stacktrace).
- [ ] Exportar CSV e "Limpar Registros" funcionam no painel.

---

## 10. Atualizações (redeploy)

Use o script incluso:

```bash
cd /var/www/jogo-rafaela
./deploy/deploy.sh
```

Ele executa: `git pull` → `composer install --no-dev` → `npm ci && npm run build`
→ `migrate --force` → recache de config/route/view → ajuste de permissões.

---

## 11. Troubleshooting

| Sintoma | Causa provável | Ação |
|---|---|---|
| `500` em branco | permissão de `storage/` | passo 6; ver `storage/logs/laravel.log` |
| CSS/JS não carregam | `npm run build` não rodou | rode o build; confirme `public/build/manifest.json` |
| Login nunca entra | `ADMIN_PASSWORD_HASH` vazio/errado | regenere o hash (passo 4) e `config:clear` |
| Mudança no `.env` ignorada | config em cache | `php artisan config:clear` |
| `419 Page Expired` | cookie de sessão / CSRF | confira `APP_URL`, `SESSION_*` e o relógio do servidor |
