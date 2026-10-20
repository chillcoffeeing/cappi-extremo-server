#!/usr/bin/env bash
# Ensayo de una actualización del API EN EL SERVIDOR, sin tocar producción.
#
# 1. Repositorio de producción: sin cambios locales, commits nuevos, fast-forward.
# 2. Qué trae: migraciones nuevas (y escaneo de operaciones peligrosas),
#    dependencias, seeders, claves nuevas del .env.
# 3. Copia de la app (otra carpeta) + copia de la base (otra base MySQL).
# 4. migrate --pretend y migrate contra la COPIA; seeders pedidos.
# 5. Antes/después de la copia: tablas o columnas que desaparecen y filas
#    que se pierden = actualización destructiva.
# 6. Compatibilidad con el portal: rutas api/* que desaparecen; peticiones
#    de humo a la app nueva.
# 7. migrate:rollback + migrate otra vez (que el down() sirva).
# 8. php artisan test contra MySQL (la copia; se destruye al final).
#
# Variables extra: TESTS=mysql|sqlite|skip, ALLOW_DESTRUCTIVE=1,
#   DRYRUN_DB (base ya creada a mano en cPanel si uapi no está disponible),
#   FORCE_CHECK=1 (ensayar aunque no haya commits nuevos).
set -uo pipefail
export LC_ALL=C   # sort/comm/join con el mismo orden binario
MODE=check
source "$(dirname "$0")/lib.sh"

TESTS="${TESTS:-mysql}"
STG="$WORK/staging"
SNAP="$WORK/snapshots"
CREATED_DB=""

cleanup() {
  if [ -n "$CREATED_DB" ]; then
    uapi Mysql delete_database name="$CREATED_DB" >/dev/null 2>&1 \
      && echo "    Base de ensayo $CREATED_DB eliminada" \
      || echo "    [WARN] No se pudo borrar la base de ensayo $CREATED_DB: bórrala en cPanel"
  fi
  rm -rf "$STG" "$SNAP"
  [ -n "$DB_DEFAULTS" ] && rm -f "$DB_DEFAULTS"
}
trap cleanup EXIT

finish() {
  summary
  local verdict=PASS
  [ "$FAILS" -gt 0 ] && verdict=FAIL
  {
    echo "TARGET=${TARGET:-}"
    echo "CURRENT=${CUR:-}"
    echo "RESULT=$verdict"
    echo "PENDING_MIGRATIONS=${PENDING_COUNT:-0}"
    echo "COMPOSER_CHANGED=${COMPOSER_CHANGED:-0}"
    echo "SEEDS=${SEEDS:-}"
    echo "AT=$STAMP"
  } > "$WORK/last-check"
  [ "$verdict" = PASS ] && echo "==> ENSAYO OK: se puede aplicar (scripts/deploy.sh --apply)." \
    || echo "==> ENSAYO CON ERRORES: no apliques esta actualización."
  [ "$verdict" = PASS ] && exit 0 || exit 1
}

# --- 1. Repositorio de producción ----------------------------------------------------------------
step "Producción: ~/${API_DIR#/}"
[ -d "$APP/.git" ] || { fail "$APP no es un repositorio git (¿primer despliegue? ver --init)"; finish; }
cd "$APP" || { fail "No se pudo entrar a $APP"; finish; }
[ -f .env ] || { fail "Falta $APP/.env"; finish; }

DIRTY="$(g status --porcelain --untracked-files=no)"
if [ -z "$DIRTY" ]; then pass "sin cambios hechos a mano en el servidor"
else fail "hay archivos modificados en el servidor (alguien editó código en producción):"; echo "$DIRTY" | sed 's/^/           /'; fi

CUR="$(git rev-parse HEAD)"
info "commit actual: $(git log --oneline -1)"
if ! git fetch -q origin "$BRANCH"; then fail "git fetch origin $BRANCH falló (token de GitHub del servidor)"; finish; fi
TARGET="$(git rev-parse "origin/$BRANCH")"

if [ "$CUR" = "$TARGET" ]; then
  # Sin commits nuevos solo queda algo que ensayar si hay migraciones
  # pendientes (primer despliegue) o si se pide (--force-check).
  PROD_PENDING="$(artisan migrate:status --no-ansi 2>/dev/null | grep -cE '[[:space:]]Pending[[:space:]]*$' || true)"
  if [ "${PROD_PENDING:-0}" = 0 ] && [ "${FORCE_CHECK:-0}" != 1 ]; then
    warn "origin/$BRANCH no trae commits nuevos ni hay migraciones pendientes (¿hiciste push?)"
    PENDING_COUNT=0; finish
  fi
  info "sin commits nuevos; migraciones pendientes en producción: ${PROD_PENDING:-0}"
else
  info "commits a aplicar:"
  git log --oneline "$CUR..$TARGET" | sed 's/^/             /'
  if git merge-base --is-ancestor "$CUR" "$TARGET"; then pass "fast-forward (no hay historia reescrita)"
  else fail "origin/$BRANCH no desciende del commit actual: alguien reescribió la historia"; fi
fi

# --- 2. Qué trae ---------------------------------------------------------------------------------
step "Qué cambia"
CHANGED="$(git diff --name-only "$CUR" "$TARGET")"
info "$(echo "$CHANGED" | grep -c . ) archivo(s) cambiados"

NEW_MIGS="$(git diff --name-only --diff-filter=A "$CUR" "$TARGET" -- database/migrations)"
TOUCHED_MIGS="$(git diff --name-only --diff-filter=MDR "$CUR" "$TARGET" -- database/migrations)"
[ -n "$NEW_MIGS" ] && { info "migraciones nuevas:"; echo "$NEW_MIGS" | sed 's/^/             /'; } || pass "sin migraciones nuevas"
[ -n "$TOUCHED_MIGS" ] && { warn "se modificaron o borraron migraciones existentes (las ya corridas no se re-ejecutan):"; echo "$TOUCHED_MIGS" | sed 's/^/           /'; }

COMPOSER_CHANGED=0
if echo "$CHANGED" | grep -qE '^composer\.(json|lock)$'; then COMPOSER_CHANGED=1; warn "cambian las dependencias (composer install)"; fi
echo "$CHANGED" | grep -q '^database/seeders/' && warn "cambian seeders: lee el informe de la feature; solo se corren los que pases con --seed"

# Claves nuevas en la plantilla de producción que el .env del servidor no tiene.
if echo "$CHANGED" | grep -q '^\.env\.production\.example$'; then
  MISSING="$(git show "$TARGET:.env.production.example" | grep -oE '^[A-Z0-9_]+=' | tr -d '=' | while read -r k; do grep -qE "^$k=" .env || echo "$k"; done)"
  [ -n "$MISSING" ] && warn "claves nuevas en .env.production.example que faltan en el .env del servidor: $(echo $MISSING)"
fi

# Escaneo estático: operaciones que pueden perder datos. El veredicto real
# lo da la comparación de la copia (paso 5); esto sirve para leerlas.
if [ -n "$NEW_MIGS" ]; then
  RISKY='dropColumn|dropIfExists|Schema::drop\(|->drop\(|truncate\(|renameColumn|rename\(|->change\(\)|DB::(statement|unprepared|delete|update|table)|->delete\(\)|dropForeign|dropPrimary|dropUnique'
  HITS=""
  while read -r m; do
    [ -n "$m" ] || continue
    h="$(git show "$TARGET:$m" | grep -nE "$RISKY" || true)"
    [ -n "$h" ] && HITS+="$m"$'\n'"$(echo "$h" | sed 's/^/      /')"$'\n'
  done <<< "$NEW_MIGS"
  if [ -n "$HITS" ]; then warn "operaciones a revisar en las migraciones nuevas (up y down):"; echo "$HITS" | sed 's/^/           /'
  else pass "las migraciones nuevas no usan drop/rename/change/delete/SQL crudo"; fi
fi

# --- 3. Copia de la app y de la base -------------------------------------------------------------
step "Preparando la copia (app en $STG, base de ensayo)"
load_db
[ "$DB_CONNECTION_PROD" = mysql ] || [ "$DB_CONNECTION_PROD" = mariadb ] || { fail "DB_CONNECTION=$DB_CONNECTION_PROD: el ensayo solo sabe copiar MySQL"; finish; }
sql -e "SELECT 1" >/dev/null 2>&1 || { fail "no se pudo conectar a MySQL con las credenciales del .env"; finish; }

if [ -n "${DRYRUN_DB:-}" ]; then
  DRY_DB="$DRYRUN_DB"
  [ "$DRY_DB" != "$DB_NAME" ] || { fail "DRYRUN_DB no puede ser la base de producción"; finish; }
  # Base dedicada creada a mano: se vacía antes de usarla.
  for t in $(sql -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DRY_DB'"); do
    sql -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE \`$DRY_DB\`.\`$t\`" || true
  done
else
  command -v uapi >/dev/null 2>&1 || { fail "uapi no disponible: crea una base vacía en cPanel y pásala con DRYRUN_DB"; finish; }
  DRY_DB="${CPUSER}_cappidry"
  uapi Mysql delete_database name="$DRY_DB" >/dev/null 2>&1 || true   # resto de un ensayo cortado
  # 2>/dev/null: los hooks de Namecheap escriben errores propios en stderr aunque la base se cree.
  if ! uapi Mysql create_database name="$DRY_DB" 2>/dev/null | grep -qE '^\s*status: 1'; then
    fail "uapi no pudo crear la base $DRY_DB (¿límite de bases de la cuenta?)"; finish
  fi
  CREATED_DB="$DRY_DB"
  if ! uapi Mysql set_privileges_on_database user="$DB_USER" database="$DRY_DB" privileges=ALL%20PRIVILEGES 2>/dev/null | grep -qE '^\s*status: 1'; then
    fail "uapi no pudo dar permisos a $DB_USER sobre $DRY_DB"; finish
  fi
fi
info "base de ensayo: $DRY_DB"

if mysqldump --defaults-extra-file="$DB_DEFAULTS" --single-transaction --no-tablespaces "$DB_NAME" | sql "$DRY_DB"; then
  pass "base de producción copiada ($(sql -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DRY_DB'") tablas)"
else
  fail "no se pudo copiar la base de producción a $DRY_DB"; finish
fi

rm -rf "$STG"
git clone -q --no-checkout "$APP" "$STG" \
  && git -C "$STG" fetch -q origin "+refs/remotes/origin/$BRANCH:refs/remotes/src/$BRANCH" \
  && git -C "$STG" -c advice.detachedHead=false checkout -q "$TARGET" \
  || { fail "no se pudo preparar la copia del código en $STG"; finish; }

cp "$APP/.env" "$STG/.env"
setenv "$STG/.env" DB_DATABASE "$DRY_DB"
setenv "$STG/.env" DB_URL ""                # DB_URL, si existiera, ganaría a DB_DATABASE
setenv "$STG/.env" MAIL_MAILER log         # el ensayo nunca envía correos
setenv "$STG/.env" QUEUE_CONNECTION sync
setenv "$STG/.env" LOG_CHANNEL single
mkdir -p "$STG/storage/framework/"{cache,sessions,views} "$STG/storage/logs" "$STG/bootstrap/cache"

COMPOSER_FLAGS=(install --no-interaction --prefer-dist --no-progress --optimize-autoloader)
[ "$TESTS" = skip ] && COMPOSER_FLAGS+=(--no-dev)
if (cd "$STG" && composer_run "${COMPOSER_FLAGS[@]}" >"$WORK/logs/composer-$STAMP.log" 2>&1); then pass "composer install en la copia"
else fail "composer install falló en la copia (ver $WORK/logs/composer-$STAMP.log)"; finish; fi

cd "$STG" || finish

# Candado: la copia DEBE apuntar a la base de ensayo. Si no, se para aquí
# (migrar o correr los tests con la base de producción la destruiría).
RESOLVED_DB="$(artisan config:show "database.connections.$DB_CONNECTION_PROD.database" --no-ansi 2>/dev/null | grep -oE "${DRY_DB}|${DB_NAME}" | head -1)"
if [ "$RESOLVED_DB" != "$DRY_DB" ] || [ "$DRY_DB" = "$DB_NAME" ]; then
  fail "la copia no apunta a la base de ensayo (resuelve '${RESOLVED_DB:-?}'): se aborta sin migrar"; finish
fi
pass "la copia usa la base de ensayo $DRY_DB (producción: $DB_NAME, intacta)"

# --- 4. Migraciones contra la copia ---------------------------------------------------------------
step "Migraciones (contra la copia)"
PENDING="$(artisan migrate:status --no-ansi 2>/dev/null | grep -E '[[:space:]]Pending[[:space:]]*$' | sed -E 's/^[[:space:]]+//; s/[[:space:]]*\.+[[:space:]]*Pending[[:space:]]*$//' || true)"
PENDING_COUNT="$(printf '%s' "$PENDING" | grep -c . || true)"
info "migraciones pendientes en producción: $PENDING_COUNT"
[ -n "$PENDING" ] && echo "$PENDING" | sed 's/^/             /'

snapshot() {  # $1 = prefijo de salida
  mkdir -p "$SNAP"
  sql -e "SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME,'|',COLUMN_TYPE,'|',IS_NULLABLE) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DRY_DB' ORDER BY 1" > "$SNAP/$1.cols"
  : > "$SNAP/$1.rows"
  for t in $(sql -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DRY_DB' AND TABLE_TYPE='BASE TABLE' ORDER BY 1"); do
    echo "$t|$(sql -e "SELECT COUNT(*) FROM \`$DRY_DB\`.\`$t\`")" >> "$SNAP/$1.rows"
  done
  cut -d'|' -f1 "$SNAP/$1.rows" > "$SNAP/$1.tables"
  # Huella del CONTENIDO de cada columna (independiente del orden de las filas): si cambia una
  # columna que la actualización no debería tocar, se ve en la comparación.
  : > "$SNAP/$1.hash"
  local t cols expr
  for t in $(cut -d'|' -f1 "$SNAP/$1.rows"); do
    cols="$(sql -e "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DRY_DB' AND TABLE_NAME='$t' ORDER BY ORDINAL_POSITION")"
    [ -n "$cols" ] || continue
    expr="$(for c in $cols; do printf "CONCAT('%s.%s|', MD5(COALESCE(GROUP_CONCAT(COALESCE(CAST(\`%s\` AS CHAR),'<NULL>') ORDER BY COALESCE(CAST(\`%s\` AS CHAR),'<NULL>') SEPARATOR '\\\\n'),''))) AS h_%s," "$t" "$c" "$c" "$c" "$c"; done)"
    sql -e "SET SESSION group_concat_max_len = 1073741824; SELECT ${expr%,} FROM \`$DRY_DB\`.\`$t\`" | tr '\t' '\n' >> "$SNAP/$1.hash"
  done
}

snapshot before

if [ "$PENDING_COUNT" -gt 0 ]; then
  if artisan migrate --pretend --force --no-ansi > "$SNAP/pretend.sql" 2>&1; then
    pass "migrate --pretend (SQL que se ejecutaría):"
    sed 's/^/             /' "$SNAP/pretend.sql" | head -200
    SQLHITS="$(grep -inE 'drop table|drop column|truncate|delete from|rename (table|column)|modify|change column|drop primary' "$SNAP/pretend.sql" || true)"
    [ -n "$SQLHITS" ] && warn "el SQL incluye operaciones destructivas o transformadoras (se verifican abajo con datos reales):" && echo "$SQLHITS" | sed 's/^/           /'
  else
    fail "migrate --pretend falló:"; sed 's/^/           /' "$SNAP/pretend.sql"
  fi
  if artisan migrate --force --no-ansi > "$SNAP/migrate.log" 2>&1; then pass "migrate --force sobre la copia de producción"
  else fail "migrate falló sobre la copia (en producción fallaría igual):"; tail -30 "$SNAP/migrate.log" | sed 's/^/           /'; finish; fi
fi

if [ -n "${SEEDS:-}" ]; then
  IFS=',' read -ra SEED_LIST <<< "$SEEDS"
  for s in "${SEED_LIST[@]}"; do
    seed_allowed "$s" "$DRY_DB" || continue
    if artisan db:seed --class="$s" --force --no-ansi > "$SNAP/seed-$s.log" 2>&1; then pass "db:seed --class=$s sobre la copia"
    else fail "db:seed --class=$s falló:"; tail -20 "$SNAP/seed-$s.log" | sed 's/^/           /'; fi
  done
fi

# --- 5. ¿Destructiva? -----------------------------------------------------------------------------
step "Comparación antes/después de la copia"
snapshot after
destructive() {
  if [ "${ALLOW_DESTRUCTIVE:-0}" = 1 ]; then warn "$* (permitido con --allow-destructive)"; else fail "$*"; fi
}
GONE_TABLES="$(comm -23 <(sort "$SNAP/before.tables") <(sort "$SNAP/after.tables"))"
[ -n "$GONE_TABLES" ] && destructive "tablas que desaparecen: $(echo $GONE_TABLES)"

GONE_COLS="$(comm -23 <(cut -d'|' -f1 "$SNAP/before.cols" | sort) <(cut -d'|' -f1 "$SNAP/after.cols" | sort))"
[ -n "$GONE_COLS" ] && destructive "columnas que desaparecen (o se renombran): $(echo $GONE_COLS)"

TYPE_CHANGES="$(comm -23 <(sort "$SNAP/before.cols") <(sort "$SNAP/after.cols") | cut -d'|' -f1 | grep -vxF -f <(echo "$GONE_COLS" | grep . || echo '#none#') || true)"
for c in $TYPE_CHANGES; do
  b="$(grep -F "$c|" "$SNAP/before.cols" | head -1 | cut -d'|' -f2-)"
  a="$(grep -F "$c|" "$SNAP/after.cols" | head -1 | cut -d'|' -f2-)"
  warn "columna $c cambia: $b -> $a (transformadora: revisa que no recorte datos)"
done

LOST=""
# Tablas que cambian solas (caché, sesiones, colas): no cuentan como datos perdidos.
VOLATILE='^(cache|cache_locks|sessions|jobs|job_batches)$'
while IFS='|' read -r t n; do
  [[ "$t" =~ $VOLATILE ]] && continue
  a="$(grep -E "^$t\|" "$SNAP/after.rows" | cut -d'|' -f2)"
  [ -n "$a" ] && [ "$a" -lt "$n" ] && LOST+="$t: $n -> $a"$'\n'
done < "$SNAP/before.rows"
[ -n "$LOST" ] && destructive "filas que se pierden:"$'\n'"$(echo "$LOST" | sed 's/^/             /')"

# Columnas que siguen existiendo y cuyo contenido cambia (fuera de las tablas volátiles y
# `migrations`). No bloquea: una migración puede transformar datos a propósito, pero hay que
# leer la lista y confirmar que son solo las previstas.
CONTENT_CHANGED="$(LC_ALL=C join -t'|' <(LC_ALL=C sort -t"|" -k1,1 "$SNAP/before.hash") <(LC_ALL=C sort -t"|" -k1,1 "$SNAP/after.hash") \
  | awk -F'|' '$2 != $3 {print $1}' | grep -vE '^(migrations|cache|cache_locks|sessions|jobs|job_batches)\.' || true)"
if [ -n "$CONTENT_CHANGED" ]; then
  warn "columnas cuyo CONTENIDO cambia (confirma que son solo las previstas): $(echo $CONTENT_CHANGED)"
else
  pass "ninguna otra columna cambia de contenido"
fi
echo "$CONTENT_CHANGED" > "$WORK/last-check-content-changes"

NEW_TABLES="$(comm -13 <(sort "$SNAP/before.tables") <(sort "$SNAP/after.tables"))"
NEW_COLS="$(comm -13 <(cut -d'|' -f1 "$SNAP/before.cols" | sort) <(cut -d'|' -f1 "$SNAP/after.cols" | sort))"
[ -n "$NEW_TABLES" ] && info "tablas nuevas: $(echo $NEW_TABLES)"
[ -n "$NEW_COLS" ] && info "columnas nuevas: $(echo $NEW_COLS)"
[ -z "$GONE_TABLES$GONE_COLS$LOST" ] && pass "no se pierde ninguna tabla, columna ni fila"

# --- 6. Compatibilidad con el portal --------------------------------------------------------------
step "Compatibilidad con el portal y el admin"
routes() {  # $1 = carpeta de la app -> "METODO uri" de las rutas api/*
  (cd "$1" && "$PHP" artisan route:list --json --no-ansi 2>/dev/null) | "$PHP" -r '
    $r = json_decode(stream_get_contents(STDIN), true) ?: [];
    foreach ($r as $x) { if (str_starts_with($x["uri"], "api/")) {
      foreach (explode("|", $x["method"]) as $m) { if ($m !== "HEAD") echo $m, " ", $x["uri"], "\n"; } } }' | sort -u
}
routes "$APP" > "$SNAP/routes.before"
routes "$STG" > "$SNAP/routes.after"
if [ ! -s "$SNAP/routes.after" ]; then
  fail "la app nueva no lista rutas (route:list falló: ¿error al arrancar?)"
else
  GONE_ROUTES="$(comm -23 "$SNAP/routes.before" "$SNAP/routes.after")"
  # Quitadas a propósito (--allow-removed-route="GET api/x"): aviso, no error.
  ALLOWED_GONE=""
  if [ -n "${ALLOW_REMOVED_ROUTES:-}" ] && [ -n "$GONE_ROUTES" ]; then
    ALLOWED_GONE="$(echo "$GONE_ROUTES" | grep -xF -f <(echo "$ALLOW_REMOVED_ROUTES" | tr ',' '\n') || true)"
    GONE_ROUTES="$(echo "$GONE_ROUTES" | grep -vxF -f <(echo "$ALLOW_REMOVED_ROUTES" | tr ',' '\n') || true)"
  fi
  [ -n "$ALLOWED_GONE" ] && warn "rutas que desaparecen, permitidas a propósito: $(echo "$ALLOWED_GONE" | tr '\n' ';')"
  if [ -n "$GONE_ROUTES" ]; then fail "rutas del API que desaparecen (el portal publicado las puede estar usando):"; echo "$GONE_ROUTES" | sed 's/^/           /'
    info "si se quitaron a propósito y nadie las usa: --allow-removed-route=\"METODO uri\""
  else pass "ninguna ruta api/* desaparece sin permiso ($(wc -l < "$SNAP/routes.after") rutas)"; fi
  NEW_ROUTES="$(comm -13 "$SNAP/routes.before" "$SNAP/routes.after")"
  [ -n "$NEW_ROUTES" ] && info "rutas nuevas: $(echo "$NEW_ROUTES" | tr '\n' ';')"
fi

# Peticiones de humo a la app NUEVA sobre la copia: nada debe dar 5xx.
ADMIN_HOST="$(envval "$STG/.env" ADMIN_PANEL_DOMAIN)"
SMOKE_OUT="$("$PHP" "$TOOLS/smoke.php" "$STG" "$ADMIN_HOST" 2>&1)"
SMOKE_RC=$?
echo "$SMOKE_OUT" | sed 's/^/           /'
[ "$SMOKE_RC" = 0 ] && pass "peticiones de humo sin errores 5xx" || fail "peticiones de humo con errores (ver arriba)"

# --- 7. Rollback ------------------------------------------------------------------------------------
if [ "$PENDING_COUNT" -gt 0 ]; then
  step "Rollback de las $PENDING_COUNT migración(es) nuevas (contra la copia)"
  if artisan migrate:rollback --step="$PENDING_COUNT" --force --no-ansi > "$SNAP/rollback.log" 2>&1; then
    pass "migrate:rollback --step=$PENDING_COUNT funciona"
    if artisan migrate --force --no-ansi > "$SNAP/remigrate.log" 2>&1; then pass "y se puede volver a migrar"
    else warn "después del rollback, migrar otra vez falló:"; tail -10 "$SNAP/remigrate.log" | sed 's/^/           /'; fi
  else
    warn "el rollback falla: si algo sale mal habrá que restaurar el respaldo .sql:"; tail -15 "$SNAP/rollback.log" | sed 's/^/           /'
  fi
fi

# --- 8. Tests ---------------------------------------------------------------------------------------
case "$TESTS" in
  skip) step "Tests"; warn "tests omitidos (--tests=skip)" ;;
  sqlite)
    step "Tests (SQLite en memoria, como en local)"
    if "$PHP" -m | grep -qi pdo_sqlite; then
      artisan test --no-ansi > "$SNAP/tests.log" 2>&1 && pass "php artisan test: $(grep -E 'Tests:' "$SNAP/tests.log" | tail -1 | xargs)" \
        || { fail "php artisan test falló:"; tail -40 "$SNAP/tests.log" | sed 's/^/           /'; }
    else fail "el PHP del servidor no tiene pdo_sqlite: usa --tests=mysql"; fi ;;
  *)
    step "Tests contra MySQL (la base de ensayo; se vacía)"
    # Sin ADMIN_PANEL_DOMAIN: los tests esperan el panel en /admin, como en local.
    if ADMIN_PANEL_DOMAIN= DB_CONNECTION=mysql DB_DATABASE="$DRY_DB" DB_HOST="$DB_HOST_PROD" DB_PORT="$DB_PORT_PROD" DB_URL= \
        DB_USERNAME="$DB_USER" DB_PASSWORD="$(envval "$APP/.env" DB_PASSWORD)" \
        artisan test --no-ansi > "$SNAP/tests.log" 2>&1; then
      pass "php artisan test: $(grep -E 'Tests:' "$SNAP/tests.log" | tail -1 | xargs)"
    else
      fail "php artisan test falló contra MySQL:"; grep -E 'FAIL|Tests:|Error|Exception' "$SNAP/tests.log" | head -40 | sed 's/^/           /'
      cp "$SNAP/tests.log" "$WORK/logs/tests-$STAMP.log"; info "log completo: $WORK/logs/tests-$STAMP.log"
    fi ;;
esac

finish
