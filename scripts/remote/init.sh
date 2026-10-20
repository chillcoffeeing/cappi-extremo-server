#!/usr/bin/env bash
# Primer despliegue del API: clona el repo en ~/$API_DIR (que debe estar
# vacía), instala dependencias y crea el .env desde .env.production.example.
# Lee la URL del repo (con token) por stdin para que no quede en `ps`.
# No migra: después se edita el .env y se sigue con --check y --apply.
set -uo pipefail
MODE=init
source "$(dirname "$0")/lib.sh"

read -r REPO_URL
[ -n "$REPO_URL" ] || { fail "Falta GIT_REPO_URL_FOR_DEPLOY"; summary; exit 1; }
[ -d "$APP/.git" ] && { fail "$APP ya tiene un despliegue: usa --check / --apply"; summary; exit 1; }

mkdir -p "$APP"
# cPanel puede crear cgi-bin/.well-known al dar de alta el subdominio: se apartan y se devuelven.
OTHERS="$(find "$APP" -mindepth 1 -maxdepth 1 ! -name cgi-bin ! -name .well-known | head -5)"
[ -z "$OTHERS" ] || { fail "$APP no está vacía (no se borra nada automáticamente):"; echo "$OTHERS" | sed 's/^/           /'; summary; exit 1; }
PARK="$WORK/init-park-$STAMP"; mkdir -p "$PARK"
find "$APP" -mindepth 1 -maxdepth 1 -exec mv {} "$PARK"/ \;

step "Clonando en $APP"
if git clone -q --branch "$BRANCH" "$REPO_URL" "$APP"; then pass "$(git -C "$APP" log --oneline -1)"
else fail "git clone falló"; find "$PARK" -mindepth 1 -maxdepth 1 -exec mv {} "$APP"/ \;; summary; exit 1; fi
find "$PARK" -mindepth 1 -maxdepth 1 -exec mv {} "$APP"/ \; ; rmdir "$PARK"

cd "$APP" || exit 1
step "Dependencias y .env"
composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress > "$WORK/logs/composer-init-$STAMP.log" 2>&1 \
  && pass "composer install --no-dev" || fail "composer install falló (ver $WORK/logs/composer-init-$STAMP.log)"
if [ -f .env ]; then warn ".env ya existía: no se toca"
else cp .env.production.example .env && artisan key:generate --force --no-ansi >/dev/null && pass ".env creado y APP_KEY generada"; fi
chmod -R 775 storage bootstrap/cache 2>/dev/null

step "Siguiente paso (a mano)"
info "1. nano ~/${API_DIR#/}/.env  -> DB_DATABASE / DB_USERNAME / DB_PASSWORD reales (con prefijo), APP_DEBUG=false"
info "2. scripts/deploy.sh --check --seed=PermissionSeeder --seed=PaymentMethodSeeder"
info "3. scripts/deploy.sh --apply --seed=PermissionSeeder --seed=PaymentMethodSeeder"
info "4. Crear el admin real con tinker (docs/despliegue-cpanel.md Paso 2) y editar los métodos de pago reales"
summary
[ "$FAILS" = 0 ]
