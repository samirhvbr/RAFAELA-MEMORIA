#!/usr/bin/env bash
# ===========================================================
# Redeploy do Jogo da Memória da Rafaela
# Uso: ./deploy/deploy.sh   (a partir da raiz do projeto)
# ===========================================================
set -euo pipefail

cd "$(dirname "$0")/.."   # raiz do projeto

echo "▶ Modo manutenção..."
php artisan down --retry=15 || true

echo "▶ Atualizando código..."
git pull --ff-only

echo "▶ Dependências PHP..."
composer install --no-dev --optimize-autoloader

echo "▶ Assets (Vite)..."
npm ci
npm run build

echo "▶ Migrations..."
php artisan migrate --force

echo "▶ Recache de produção..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "▶ Permissões..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
    echo "  (pulei chown — rode com sudo se necessário)"

echo "▶ Saindo do modo manutenção..."
php artisan up

echo "✓ Deploy concluído — v$(grep -oE '[0-9]+\.[0-9]+\.[0-9]+' version.md | head -n1)"
