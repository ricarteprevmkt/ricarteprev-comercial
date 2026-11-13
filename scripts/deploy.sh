#!/usr/bin/env bash
#
# Deploy na Hostinger (plano Premium), executado NO SERVIDOR via SSH:
#
#   cd ~/domains/SEU_DOMINIO/ricarteprev && bash scripts/deploy.sh
#
# Os assets (public/build) já vêm compilados no repositório, porque o plano
# não roda Node.js. Rode `npm run build` na máquina local e faça commit antes.
#
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$(dirname "$0")/.."

echo "==> Versão do PHP"
"$PHP_BIN" -r 'if (version_compare(PHP_VERSION, "8.3.0", "<")) { fwrite(STDERR, "PHP 8.3 ou superior é obrigatório. Atual: ".PHP_VERSION."\n"); exit(1); } echo PHP_VERSION, PHP_EOL;'

if [ ! -f .env ]; then
  echo "Arquivo .env não encontrado. Copie .env.production.example para .env e preencha antes do primeiro deploy." >&2
  exit 1
fi

echo "==> Modo de manutenção"
"$PHP_BIN" artisan down --retry=30 || true

echo "==> Código"
git pull --ff-only

echo "==> Dependências PHP"
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [ ! -f public/build/manifest.json ]; then
  echo "public/build/manifest.json não encontrado. Rode 'npm run build' localmente e faça commit." >&2
  "$PHP_BIN" artisan up
  exit 1
fi

echo "==> Banco de dados"
"$PHP_BIN" artisan migrate --force
# Seeders de referência são idempotentes e não criam dados fictícios.
"$PHP_BIN" artisan db:seed --force

echo "==> Caches"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

echo "==> Permissões de escrita"
chmod -R ug+rwX storage bootstrap/cache

"$PHP_BIN" artisan up
echo "Deploy concluído."
