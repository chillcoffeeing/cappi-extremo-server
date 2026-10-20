#!/usr/bin/env bash
# Respaldo completo del API bajo demanda (scripts/deploy.sh --backup):
# base de datos (~/backups/cappi-<fecha>.sql) + archivos
# (~/backups/api-files-<fecha>.tgz). --apply y --rollback lo hacen solos.
set -uo pipefail
MODE=backup
source "$(dirname "$0")/lib.sh"

trap '[ -n "$DB_DEFAULTS" ] && rm -f "$DB_DEFAULTS"' EXIT
[ -d "$APP" ] && [ -f "$APP/.env" ] || { fail "No hay API desplegado en $APP"; summary; exit 1; }
load_db
backup_all
info "commit respaldado: $(git -C "$APP" log --oneline -1 2>/dev/null)"
summary
[ "$FAILS" = 0 ]
