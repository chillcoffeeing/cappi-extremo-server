#!/usr/bin/env bash
# Vuelve el CÓDIGO del API al commit anterior al último --apply.
#
# La base de datos solo se toca con ROLLBACK_MIGRATIONS=1 (--with-migrations):
# corre migrate:rollback de las migraciones que entraron, ANTES de volver el
# código (el down() vive en el código nuevo). El ensayo ya probó esos down().
# Sin la opción, la base queda como está: con migraciones aditivas el código
# viejo las ignora.
set -uo pipefail
MODE=rollback
source "$(dirname "$0")/lib.sh"

cd "$APP" || { fail "No existe $APP"; summary; exit 1; }
[ -f "$WORK/previous-commit" ] || { fail "No hay commit anterior registrado (no se aplicó nada con este script)"; summary; exit 1; }
PREV="$(cat "$WORK/previous-commit")"
NOW="$(git rev-parse HEAD)"
BACKUP="$(cat "$WORK/previous-backup" 2>/dev/null || echo '?')"
[ "$PREV" != "$NOW" ] || { pass "Producción ya está en $PREV"; summary; exit 0; }

# También antes de revertir: si el rollback sale mal, se vuelve a este punto.
load_db
backup_all || { summary; exit 1; }
trap '[ -n "$DB_DEFAULTS" ] && rm -f "$DB_DEFAULTS"' EXIT

step "Volviendo de $(git log --oneline -1 "$NOW") a $(git log --oneline -1 "$PREV")"
MIGS="$(git diff --name-only --diff-filter=A "$PREV" "$NOW" -- database/migrations)"
N_MIGS="$(printf '%s' "$MIGS" | grep -c . || true)"
COMPOSER_CHANGED="$(git diff --name-only "$PREV" "$NOW" -- composer.lock)"

artisan down --retry=60 --no-ansi >/dev/null || warn "php artisan down falló"

if [ "$N_MIGS" -gt 0 ] && [ "${ROLLBACK_MIGRATIONS:-0}" = 1 ]; then
  artisan migrate:rollback --step="$N_MIGS" --force --no-ansi \
    && pass "migrate:rollback --step=$N_MIGS" \
    || { fail "migrate:rollback falló: el sitio sigue en mantenimiento; restaurar el respaldo $BACKUP"; summary; exit 1; }
fi

g reset -q --hard "$PREV" && pass "código en $(git log --oneline -1)" || fail "git reset falló"
if [ -n "$COMPOSER_CHANGED" ]; then
  composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress >/dev/null 2>&1 && pass "composer install" || fail "composer install falló"
fi
artisan optimize:clear --no-ansi >/dev/null
artisan optimize --no-ansi >/dev/null && pass "optimize" || fail "optimize falló"
artisan up --no-ansi >/dev/null && pass "fuera de mantenimiento"
echo "$STAMP ROLLBACK $NOW -> $PREV migraciones_revertidas=${ROLLBACK_MIGRATIONS:-0}" >> "$WORK/history.log"

if [ "$N_MIGS" -gt 0 ] && [ "${ROLLBACK_MIGRATIONS:-0}" != 1 ]; then
  step "Base de datos (NO se revirtió)"
  warn "siguen aplicadas $N_MIGS migración(es) de la versión revertida:"
  echo "$MIGS" | sed 's/^/             /'
  info "Si son aditivas, el código viejo las ignora y no hace falta nada más."
  info "Si hay que deshacerlas (el down() vive en el código nuevo, por eso el orden):"
  info "  cd ~/${API_DIR#/} && php artisan down && git reset --hard $NOW && php artisan migrate:rollback --step=$N_MIGS --force \\"
  info "    && git reset --hard $PREV && php artisan optimize:clear && php artisan optimize && php artisan up"
  info "Último recurso (reemplaza TODA la base): restaurar $BACKUP"
fi
summary
[ "$FAILS" = 0 ]
