#!/bin/bash
# versão 1.0 - 2026-07-01
#
# Deploy idempotente do Jogo da Memória da Rafaela (Laravel 11 / PHP 8.2+) em
# /srv/www/rafa.hannaverza.com.br. Web server: Nginx + PHP-FPM. Banco: MariaDB.
# Modelado no deploy.sh do ONLINE/INTRANET (mesma frota). Substitui o antigo
# deploy/deploy.sh (versão simples, sem lock/backup/split de usuário).
#
# EXECUTAR COMO ROOT. O script delega cada operação ao usuário correto:
#   - APP_USER (default: b3sys)    → git, composer, npm (dono dos arquivos,
#     do .git, de vendor/ e node_modules/)
#   - WEB_USER (default: www-data) → php artisan (escreve em storage/ e
#     bootstrap/cache/)
# Permissões são ajustadas pelo próprio script (root faz chown direto).
#
# - Sai cedo se nada novo no branch.
# - Backup MySQL antes de mexer em schema (mysqldump nativo).
# - Pula composer quando composer.lock não mudou (e vendor existe); idem para o
#   npm install. Sem lockfile commitado (1ª subida), instala e gera o lock.
# - Migrations rodam ANTES do npm build — se o frontend falhar, o schema já está certo.
# - Recarrega o PHP-FPM ao final para limpar o OPcache (código novo entra em vigor).
# - Em caso de erro: sai do modo manutenção, tenta rollback e avisa no Telegram.
# - Lock impede dois deploys simultâneos.
#
# Variáveis OPCIONAIS no .env:
#   DEPLOY_BRANCH               - branch a seguir            (default: master)
#   DEPLOY_APP_USER             - dono dos arquivos/git      (default: b3sys)
#   DEPLOY_WEB_USER             - usuário do servidor web     (default: www-data)
#   DEPLOY_BACKUP               - 1 = backup antes de migrate | 0 = pula (default: 1)
#   DEPLOY_PHP_FPM              - versão do PHP-FPM, ex "8.4" (default: auto-detecta)
#   DEPLOY_TELEGRAM_BOT_TOKEN   - token do bot Telegram (opcional)
#   DEPLOY_TELEGRAM_CHAT_ID     - chat id do grupo/canal (opcional)
#   DB_DATABASE / DB_USERNAME / DB_PASSWORD / DB_HOST / DB_PORT - conexão MySQL.
#
# Uso (sempre como root):
#   bash /srv/www/rafa.hannaverza.com.br/deploy.sh
#   DEPLOY_BRANCH=main bash deploy.sh   # forçar outro branch

set -euo pipefail

# ── Root obrigatório ──────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    printf '❌ Este script deve ser executado como root.\n' >&2
    printf '   Uso: bash %s\n' "${BASH_SOURCE[0]}" >&2
    exit 1
fi

# ── Config ────────────────────────────────────────────────────────────────────
DIR="/srv/www/rafa.hannaverza.com.br"
LOCK="${DIR}/.deploy.lock"
BACKUP_DIR="/var/backups/rafaela"
DEFAULT_APP_USER="b3sys"       # dono dos arquivos: git, composer, npm
DEFAULT_WEB_USER="www-data"    # usuário do Nginx/PHP-FPM (Debian usa www-data)

# ── Helpers ───────────────────────────────────────────────────────────────────
log() { printf '[%(%H:%M:%S)T] %s\n' -1 "$*"; }

# Lê uma chave do .env sem usar source (evita quebra com quoting).
get_env() {
    local key=$1
    [ -f .env ] || return 0
    { grep -E "^${key}=" .env 2>/dev/null || true; } | head -1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

# Executa como o dono dos arquivos: git, composer, npm.
# -H garante HOME correto (chaves SSH, ~/.gitconfig, cache do composer/npm).
as_app() { sudo -u "$APP_USER" -H "$@"; }

# Executa como o usuário do servidor web: php artisan (storage/, bootstrap/cache/).
as_web() { sudo -u "$WEB_USER" -H "$@"; }

# Notificação Telegram (silenciosa se não configurado).
notify() {
    [ -n "${TG_BOT:-}" ] && [ -n "${TG_CHAT:-}" ] || return 0
    curl -fsS -m 5 -X POST "https://api.telegram.org/bot${TG_BOT}/sendMessage" \
        --data-urlencode "chat_id=${TG_CHAT}" \
        --data-urlencode "text=$1" \
        --data-urlencode "parse_mode=HTML" \
        >/dev/null 2>&1 || log "(notify Telegram falhou; ignorando)"
}

cleanup_on_failure() {
    log "❌ Deploy falhou — reativando aplicação..."
    as_web php artisan up >/dev/null 2>&1 || true
    notify "❌ <b>Deploy Rafaela falhou</b> em <code>$(as_app git rev-parse --short HEAD 2>/dev/null || echo '?')</code>"
}

fail() {
    log "❌ $*"
    cleanup_on_failure
    exit 1
}

# ── Lock ──────────────────────────────────────────────────────────────────────
exec 9>"$LOCK"
flock -n 9 || { printf '❌ outro deploy já está rodando (lock: %s)\n' "$LOCK" >&2; exit 1; }

cd "$DIR" || { printf '❌ %s não existe\n' "$DIR" >&2; exit 1; }

# ── Lê variáveis do .env ──────────────────────────────────────────────────────
BRANCH="${DEPLOY_BRANCH:-$(get_env DEPLOY_BRANCH)}"
BRANCH="${BRANCH:-master}"
APP_USER="$(get_env DEPLOY_APP_USER)"; APP_USER="${APP_USER:-$DEFAULT_APP_USER}"
WEB_USER="$(get_env DEPLOY_WEB_USER)"; WEB_USER="${WEB_USER:-$DEFAULT_WEB_USER}"
BACKUP="${DEPLOY_BACKUP:-$(get_env DEPLOY_BACKUP)}"; BACKUP="${BACKUP:-1}"
PHP_FPM="$(get_env DEPLOY_PHP_FPM)"
TG_BOT="$(get_env DEPLOY_TELEGRAM_BOT_TOKEN)"
TG_CHAT="$(get_env DEPLOY_TELEGRAM_CHAT_ID)"
DB_NAME="$(get_env DB_DATABASE)"
DB_USER="$(get_env DB_USERNAME)"
DB_PASS="$(get_env DB_PASSWORD)"
DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"

# Valida usuários antes de qualquer operação
id "$APP_USER" &>/dev/null || { printf '❌ usuário APP_USER "%s" não existe\n' "$APP_USER" >&2; exit 1; }
id "$WEB_USER" &>/dev/null || { printf '❌ usuário WEB_USER "%s" não existe\n' "$WEB_USER" >&2; exit 1; }

log "=== Deploy Rafaela — dir: $DIR | app: $APP_USER | web: $WEB_USER | branch: $BRANCH ==="

# ── 0. Neutraliza o efeito do chmod aos olhos do git ──────────────────────────
# O chmod g+w em storage/ (passos 1.5/7) pode deixar "mode-only diffs" em arquivos
# rastreados (.gitignore internos) que quebram o `git merge --ff-only`. Idempotente.
as_app git config core.fileMode false 2>/dev/null || true

# ── 1. Fetch e checagem de mudanças (como APP_USER, dono do .git) ─────────────
log "==> [1/9] Buscando alterações no branch $BRANCH..."
as_app git fetch --quiet origin "$BRANCH" || { log "git fetch falhou"; exit 1; }

LOCAL=$(as_app git rev-parse HEAD)
REMOTE=$(as_app git rev-parse "origin/$BRANCH")

if [ "$LOCAL" = "$REMOTE" ]; then
    log "✓ Nada novo em origin/$BRANCH. Saindo."
    exit 0
fi

if ! as_app git diff --quiet || ! as_app git diff --cached --quiet; then
    log "⚠️  Working tree tem mudanças locais não commitadas — continuando."
fi

log "==> Trazendo $(as_app git rev-parse --short "$LOCAL") → $(as_app git rev-parse --short "$REMOTE")..."
as_app git merge --ff-only "origin/$BRANCH" \
    || { log "❌ fast-forward falhou (divergiu? resolva manual e rode de novo)"; exit 1; }

# Garantia pós-merge: o working tree TEM que estar em origin/BRANCH.
HEAD_NOW=$(as_app git rev-parse HEAD)
if [ "$HEAD_NOW" != "$REMOTE" ]; then
    log "❌ ABORTANDO: HEAD ($HEAD_NOW) != origin/$BRANCH ($REMOTE) após o merge."
    exit 1
fi

CHANGED=$(as_app git diff --name-only "$LOCAL" "$REMOTE")

# Daqui pra frente, qualquer falha dispara o cleanup automático.
trap cleanup_on_failure ERR

# ── 1.5 Garante storage/ e bootstrap/cache/ graváveis pelo web ────────────────
# 'php artisan down' (passo 2) roda como WEB_USER e escreve em storage/framework/.
# Numa 1ª subida ou após drift de permissão isso falha; normaliza ANTES.
log "==> Garantindo permissões de storage/ e bootstrap/cache/..."
chown -R "$APP_USER":"$WEB_USER" storage bootstrap/cache
chmod -R g+w storage bootstrap/cache

# ── 2. Modo manutenção ────────────────────────────────────────────────────────
log "==> [2/9] Ativando modo de manutenção..."
as_web php artisan down --refresh=15

# ── 3. Backup do banco (antes de qualquer migrate) ────────────────────────────
log "==> [3/9] Backup do banco MySQL..."
BACKUP_FILE=""
if [ "$BACKUP" = "1" ]; then
    if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
        mkdir -p "$BACKUP_DIR"
        BACKUP_FILE="$BACKUP_DIR/rafaela_$(date +%Y%m%d_%H%M%S).sql.gz"
        if MYSQL_PWD="$DB_PASS" mysqldump \
                -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" \
                -u "$DB_USER" --single-transaction --quick --no-tablespaces \
                "$DB_NAME" | gzip > "$BACKUP_FILE"; then
            log "✓ Backup salvo em: $BACKUP_FILE"
            ls -t "$BACKUP_DIR"/rafaela_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --
        else
            fail "mysqldump falhou — abortando antes de tocar no schema"
        fi
    else
        log "⚠️  DB_DATABASE/DB_USERNAME ausentes no .env — backup pulado (risco!)."
    fi
else
    log "⚠️  Backup desativado (DEPLOY_BACKUP=0) — pulando."
fi

# ── 4. Dependências PHP (só se composer.lock mudou, ou vendor ausente) ────────
log "==> [4/9] Verificando dependências PHP..."
if [ ! -d vendor ] || echo "$CHANGED" | grep -q '^composer\.lock$'; then
    log "    Instalando dependências PHP (como $APP_USER)..."
    as_app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
else
    log "✓ composer.lock inalterado — pulando composer install."
fi

# ── 5. Migrations (ANTES do npm: se o front falhar, o schema já está certo) ───
log "==> [5/9] Migrations..."
PENDING=$(as_web php artisan migrate:status 2>/dev/null | grep -i "pending" || true)
if [ -n "$PENDING" ]; then echo "$PENDING"; else log "    (nenhuma pendente)"; fi

if ! as_web php artisan migrate --force; then
    log "❌ migrate falhou — tentando rollback do último batch..."
    if as_web php artisan migrate:rollback --force --step=1; then
        log "✓ Rollback OK — schema voltou ao estado anterior."
    else
        log "⚠️  Rollback também falhou. Restaure manualmente de: ${BACKUP_FILE:-<sem backup>}"
    fi
    fail "migrate falhou"
fi

# ── 6. Frontend (npm install só se deps mudaram; build sempre p/ manifest Vite) ─
log "==> [6/9] Frontend (npm + build)..."
if echo "$CHANGED" | grep -qE '^package(-lock)?\.json$' || [ ! -d node_modules ]; then
    log "    Dependências mudaram (ou node_modules ausente) — instalando..."
    if [ -f package-lock.json ]; then
        as_app npm ci --no-audit --no-fund
    else
        as_app npm install --no-audit --no-fund
    fi
fi
as_app npm run build

# ── 7. Permissões de escrita para o web (composer/npm criaram arquivos novos) ─
log "==> [7/9] Ajustando permissões..."
chown -R "$APP_USER":"$WEB_USER" storage bootstrap/cache
chmod -R g+w storage bootstrap/cache

# ── 8. Caches de produção ─────────────────────────────────────────────────────
log "==> [8/9] Reconstruindo caches..."
as_web php artisan optimize:clear
as_web php artisan config:cache
as_web php artisan route:cache
as_web php artisan view:cache
as_web php artisan event:cache 2>/dev/null || true

# ── 8.1 Reset do OPcache (reload do PHP-FPM) ──────────────────────────────────
# Com opcache.validate_timestamps=0 em produção, sem reload o PHP serve o bytecode
# ANTIGO mesmo após o pull. Feito ANTES de sair da manutenção. Tolerante a falha.
if [ -z "$PHP_FPM" ]; then
    PHP_FPM="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
fi
if [ -n "$PHP_FPM" ] && systemctl is-active --quiet "php${PHP_FPM}-fpm" 2>/dev/null; then
    systemctl reload "php${PHP_FPM}-fpm" \
        && log "✓ OPcache resetado (php${PHP_FPM}-fpm recarregado)." \
        || log "⚠️  reload do php${PHP_FPM}-fpm falhou — pode servir código antigo."
else
    log "⚠️  PHP-FPM não recarregado (versão não detectada ou serviço inativo) — OPcache pode estar velho."
fi

# ── 9. Sai do modo manutenção ─────────────────────────────────────────────────
log "==> [9/9] Desativando modo de manutenção..."
as_web php artisan up

# Sucesso — desarma o trap e notifica.
trap - ERR

SHORT_SHA=$(as_app git rev-parse --short HEAD)
SUBJECT=$(as_app git log -1 --pretty=%s | head -c 80)

log "✅ Deploy Rafaela concluído: $SHORT_SHA — $SUBJECT"
notify "✅ <b>Deploy Rafaela concluído</b>: <code>$SHORT_SHA</code> — $SUBJECT"
