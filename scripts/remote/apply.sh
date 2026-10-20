#!/usr/bin/env bash
# Aplica la actualización del API en producción. Solo corre si el último
# ensayo (check.sh) pasó para EXACTAMENTE el commit que hoy está en
# origin/$BRANCH. Procedimiento de docs/update-cpanel.md, automatizado:
# respaldo -> mantenimiento (si hay migraciones/dependencias/seeders) ->
# pull -> composer -> migrate -> seeders -> cachés -> up.
set -uo pipefail
MODE=apply
source "$(dirname "$0")/lib.sh"

abort() { fail "$*"; summary; exit 1; }
cleanup() { [ -n "$DB_DEFAULTS" ] && rm -f "$DB_DEFAULTS"; }
trap cleanup EXIT

cd "$APP" || abort "No existe $APP"
[ -f "$WORK/last-check" ] || abort "No hay ensayo previo: corre scripts/deploy.sh --check"
CHECK_TARGET="$(envval "$WORK/last-check" TARGET)"
CHECK_RESULT="$(envval "$WORK/last-check" RESULT)"
PENDING_COUNT="$(envval "$WORK/last-check" PENDING_MIGRATIONS)"
COMPOSER_CHANGED="$(envval "$WORK/last-check" COMPOSER_CHANGED)"
CHECK_SEEDS="$(envval "$WORK/last-check" SEEDS)"

step "Comprobaciones previas"
git fetch -q origin "$BRANCH" || abort "git fetch falló"
TARGET="$(git rev-parse "origin/$BRANCH")"
PREV="$(git rev-parse HEAD)"
[ "$CHECK_RESULT" = PASS ] || abort "El último ensayo no pasó"
[ "$CHECK_TARGET" = "$TARGET" ] || abort "origin/$BRANCH cambió desde el ensayo ($CHECK_TARGET -> $TARGET): vuelve a ensayar"
[ "$CHECK_SEEDS" = "${SEEDS:-}" ] || abort "Los seeders pedidos (${SEEDS:-ninguno}) no son los del ensayo (${CHECK_SEEDS:-ninguno})"
[ -z "$(g status --porcelain --untracked-files=no)" ] || abort "Hay archivos modificados en el servidor"
if [ "$PREV" = "$TARGET" ] && [ "${PENDING_COUNT:-0}" = 0 ] && [ -z "${SEEDS:-}" ]; then
  pass "Producción ya está en $(git log --oneline -1)"; summary; exit 0
fi
pass "ensayo OK para $(git log --oneline -1 "$TARGET")"

load_db
backup_all || abort "sin respaldo completo no se despliega"
echo "$PREV" > "$WORK/previous-commit"

NEEDS_DOWN=0
{ [ "${PENDING_COUNT:-0}" -gt 0 ] || [ "$COMPOSER_CHANGED" = 1 ] || [ -n "${SEEDS:-}" ]; } && NEEDS_DOWN=1
if [ "$NEEDS_DOWN" = 1 ]; then
  SECRET="cappi-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  artisan down --retry=60 --secret="$SECRET" --no-ansi >/dev/null || abort "php artisan down falló"
  pass "modo mantenimiento. Acceso de emergencia: $(envval .env APP_URL)/$SECRET"
fi

stay_down() {
  fail "$*"
  echo "    El sitio SIGUE EN MANTENIMIENTO a propósito. Revisa el error y decide:"
  echo "      - reintentar el paso, o"
  echo "      - scripts/deploy.sh --rollback (código al commit $PREV; respaldo: $BACKUP_FILE)"
  summary; exit 1
}

step "Código"
g merge -q --ff-only "origin/$BRANCH" || { [ "$NEEDS_DOWN" = 1 ] && artisan up >/dev/null; abort "git merge --ff-only falló (nada cambió)"; }
pass "$(git log --oneline -1)"

if [ "$COMPOSER_CHANGED" = 1 ]; then
  composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress > "$WORK/logs/composer-apply-$STAMP.log" 2>&1 \
    || stay_down "composer install falló (ver $WORK/logs/composer-apply-$STAMP.log)"
  pass "composer install --no-dev"
fi

if [ "${PENDING_COUNT:-0}" -gt 0 ]; then
  step "Migraciones"
  artisan migrate --force --no-ansi || stay_down "migrate falló: MySQL no revierte DDL; puede haber quedado a medias"
  artisan migrate:status --no-ansi | tail -"$((PENDING_COUNT + 1))" | sed 's/^/           /'
fi

if [ -n "${SEEDS:-}" ]; then
  step "Seeders"
  IFS=',' read -ra SEED_LIST <<< "$SEEDS"
  for s in "${SEED_LIST[@]}"; do
    seed_allowed "$s" "$DB_NAME" || stay_down "seeder no permitido: $s"
    artisan db:seed --class="$s" --force --no-ansi || stay_down "db:seed --class=$s falló"
    pass "db:seed --class=$s"
    [ "$s" = PermissionSeeder ] && artisan permission:cache-reset --no-ansi >/dev/null && pass "permission:cache-reset"
  done
fi

step "Cachés y permisos"
artisan optimize:clear --no-ansi >/dev/null || warn "optimize:clear falló"
artisan optimize --no-ansi >/dev/null || stay_down "php artisan optimize falló (¿error de configuración o rutas?)"
[ -e public/storage ] || artisan storage:link --no-ansi >/dev/null || warn "storage:link falló"
chmod -R 775 storage bootstrap/cache 2>/dev/null || warn "chmod storage/bootstrap"
pass "optimize + storage + permisos"

[ "$NEEDS_DOWN" = 1 ] && { artisan up --no-ansi >/dev/null && pass "fuera de mantenimiento" || fail "php artisan up falló"; }

echo "$STAMP $PREV -> $(git rev-parse HEAD) migraciones=${PENDING_COUNT:-0} seeds=${SEEDS:-} respaldo=$BACKUP_FILE archivos=$FILES_BACKUP" >> "$WORK/history.log"
summary
[ "$FAILS" = 0 ]
