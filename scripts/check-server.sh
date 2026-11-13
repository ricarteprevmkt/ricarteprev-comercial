#!/usr/bin/env bash
#
# Confere se o servidor tem o necessário para rodar o sistema.
# Executar NO SERVIDOR, dentro da pasta do projeto:
#
#   bash scripts/check-server.sh
#
# Não altera nada. Mostra OK / FALTA / AVISO em cada item e termina com
# código 1 se algum item obrigatório estiver faltando.
#
set -u

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
cd "$(dirname "$0")/.." || exit 1

fail=0
ok()   { printf '  \033[32mOK\033[0m     %s\n' "$1"; }
bad()  { printf '  \033[31mFALTA\033[0m  %s\n' "$1"; fail=1; }
warn() { printf '  \033[33mAVISO\033[0m  %s\n' "$1"; }

echo "== PHP"
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  bad "PHP não encontrado ($PHP_BIN)"
else
  version=$("$PHP_BIN" -r 'echo PHP_VERSION;')
  if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    ok "PHP $version"
  else
    bad "PHP $version (é preciso 8.3 ou superior; veja docs/configuracao-hostinger.md, etapa 5)"
  fi

  for ext in pdo_mysql mysqli mbstring intl bcmath gd zip fileinfo openssl sodium curl tokenizer xml ctype dom; do
    if "$PHP_BIN" -m 2>/dev/null | grep -qi "^${ext}$"; then ok "extensão $ext"; else bad "extensão $ext"; fi
  done
  if "$PHP_BIN" -m 2>/dev/null | grep -qi '^exif$'; then ok "extensão exif"; else warn "extensão exif (recomendada para fotos do celular)"; fi

  mem=$("$PHP_BIN" -r 'echo ini_get("memory_limit");')
  up=$("$PHP_BIN" -r 'echo ini_get("upload_max_filesize");')
  post=$("$PHP_BIN" -r 'echo ini_get("post_max_size");')
  echo "  info   memory_limit=$mem upload_max_filesize=$up post_max_size=$post"
fi

echo "== Ferramentas"
if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then ok "$("$COMPOSER_BIN" --version 2>/dev/null | head -1)";
elif command -v composer2 >/dev/null 2>&1; then warn "use COMPOSER_BIN=composer2 ($(composer2 --version 2>/dev/null | head -1))";
else bad "Composer"; fi
if command -v git >/dev/null 2>&1; then ok "$(git --version)"; else bad "git"; fi
if command -v mysqldump >/dev/null 2>&1; then ok "mysqldump (backup próprio)"; else warn "mysqldump não encontrado (o backup próprio da Fase 8 usará outra forma)"; fi

echo "== Projeto"
if [ -f .env ]; then ok ".env presente"; else bad ".env (copie de .env.production.example)"; fi
if [ -f vendor/autoload.php ]; then ok "dependências instaladas"; else bad "dependências (rode: composer install --no-dev --optimize-autoloader)"; fi
if [ -f public/build/manifest.json ]; then ok "assets compilados (public/build)"; else bad "public/build/manifest.json"; fi
for dir in storage bootstrap/cache; do
  if [ -w "$dir" ]; then ok "$dir gravável"; else bad "$dir sem permissão de escrita (rode: chmod -R ug+rwX $dir)"; fi
done

if [ -f .env ] && [ -f vendor/autoload.php ]; then
  echo "== Banco de dados"
  if "$PHP_BIN" artisan db:show --json >/dev/null 2>&1; then
    ok "conexão com o MySQL"
  else
    bad "conexão com o MySQL (confira DB_DATABASE, DB_USERNAME e DB_PASSWORD no .env)"
  fi

  echo "== Configuração"
  env_val() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"'; }
  [ "$(env_val APP_ENV)" = "production" ] && ok "APP_ENV=production" || warn "APP_ENV não é production"
  [ "$(env_val APP_DEBUG)" = "false" ] && ok "APP_DEBUG=false" || bad "APP_DEBUG precisa ser false em produção"
  [ -n "$(env_val APP_KEY)" ] && ok "APP_KEY definida" || bad "APP_KEY (rode: php artisan key:generate)"
  case "$(env_val APP_URL)" in https://*) ok "APP_URL com https";; *) warn "APP_URL sem https";; esac
  mail_pass="$(env_val MAIL_PASSWORD)"
  [ -n "$mail_pass" ] && [ "$mail_pass" != "null" ] && ok "e-mail SMTP configurado" || warn "e-mail SMTP não configurado (links de senha não serão enviados)"
fi

echo
if [ "$fail" -eq 0 ]; then
  echo "Tudo pronto."
else
  echo "Há itens faltando. Corrija os marcados com FALTA e rode de novo."
fi
exit "$fail"
