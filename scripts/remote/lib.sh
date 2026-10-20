# Funciones comunes de los scripts que corren EN EL SERVIDOR (check.sh,
# apply.sh, rollback.sh). Las sube scripts/deploy.sh a ~/deploy/api/tools/.
#
# Variables que llegan desde scripts/deploy.sh:
#   API_DIR     carpeta del API relativa al home (api.cappixtremo.com)
#   BRANCH      rama que se despliega (main)
#   PHP_BIN     binario de PHP (php)
#   SEEDS       seeders separados por coma (opcional)
#   MODE        check | apply | rollback (para el nombre del log)

APP="$HOME/${API_DIR#/}"
APP="${APP%/}"
BRANCH="${BRANCH:-main}"
PHP="${PHP_BIN:-php}"
WORK="$HOME/deploy/api"
TOOLS="$WORK/tools"
STAMP="$(date +%Y%m%d-%H%M%S)"
CPUSER="$(whoami)"

mkdir -p "$WORK/logs"
LOG="$WORK/logs/${MODE:-run}-$STAMP.log"
exec > >(tee -a "$LOG") 2>&1

FAILS=0
WARNS=0
step() { echo; echo "==> $*"; }
pass() { echo "    [OK]   $*"; }
info() { echo "           $*"; }
warn() { echo "    [WARN] $*"; WARNS=$((WARNS + 1)); }
fail() { echo "    [FAIL] $*"; FAILS=$((FAILS + 1)); }

# Valor de una clave en un archivo .env (sin comillas envolventes ni \r).
envval() {
  { grep -E "^$2=" "$1" 2>/dev/null || true; } | tail -1 | cut -d= -f2- | tr -d '\r' \
    | sed -E 's/^"(.*)"$/\1/' | sed -E "s/^'(.*)'$/\1/"
}

# Fija (o agrega) una clave en un .env.
setenv() {
  local file=$1 key=$2 value=$3
  if grep -qE "^$key=" "$file"; then
    local tmp; tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" 'BEGIN{FS=OFS="="} $1==k {print k "=" v; next} {print}' "$file" > "$tmp" && cat "$tmp" > "$file"
    rm -f "$tmp"
  else
    printf '%s=%s\n' "$key" "$value" >> "$file"
  fi
}

artisan() { "$PHP" artisan "$@"; }

composer_run() {
  local bin
  bin="$(command -v composer || true)"
  [ -n "$bin" ] || { fail "composer no está en el PATH del servidor"; return 1; }
  "$PHP" -d memory_limit=-1 "$bin" "$@"
}

# Credenciales MySQL de producción en un archivo de opciones temporal
# (chmod 600): la contraseña nunca va en la línea de comandos.
DB_DEFAULTS=""
load_db() {
  DB_CONNECTION_PROD="$(envval "$APP/.env" DB_CONNECTION)"
  DB_NAME="$(envval "$APP/.env" DB_DATABASE)"
  DB_USER="$(envval "$APP/.env" DB_USERNAME)"
  local pass host port
  pass="$(envval "$APP/.env" DB_PASSWORD)"
  host="$(envval "$APP/.env" DB_HOST)"; host="${host:-localhost}"
  port="$(envval "$APP/.env" DB_PORT)"; port="${port:-3306}"
  DB_HOST_PROD="$host"
  DB_PORT_PROD="$port"
  DB_DEFAULTS="$(mktemp)"
  chmod 600 "$DB_DEFAULTS"
  pass="${pass//\\/\\\\}"; pass="${pass//\"/\\\"}"
  printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\nport=%s\n' "$DB_USER" "$pass" "$host" "$port" > "$DB_DEFAULTS"
}

sql() { mysql --defaults-extra-file="$DB_DEFAULTS" -N -B "$@"; }

# Respaldo de la base de producción. Imprime la ruta; falla si queda vacío.
backup_db() {
  mkdir -p "$HOME/backups"
  local out="$HOME/backups/cappi-$STAMP.sql"
  if mysqldump --defaults-extra-file="$DB_DEFAULTS" --single-transaction --no-tablespaces "$DB_NAME" > "$out" && [ -s "$out" ]; then
    BACKUP_FILE="$out"
    return 0
  fi
  rm -f "$out"
  return 1
}

# Respaldo de los archivos del API: código (con .git), .env y storage/
# (fotos de participantes, comprobantes de pago). Fuera: vendor/ (se
# reinstala con composer) y cachés/logs regenerables.
backup_files() {
  mkdir -p "$HOME/backups"
  local out="$HOME/backups/api-files-$STAMP.tgz"
  if tar czf "$out" -C "$APP" \
      --exclude=./vendor --exclude=./node_modules \
      --exclude=./storage/framework/cache --exclude=./storage/framework/sessions \
      --exclude=./storage/framework/views --exclude=./storage/logs . && [ -s "$out" ]; then
    FILES_BACKUP="$out"
    return 0
  fi
  rm -f "$out"
  return 1
}

# Conserva los N respaldos más recientes de cada tipo.
prune_backups() {
  local keep=${1:-7}
  ls -1t "$HOME"/backups/cappi-*.sql 2>/dev/null | tail -n +$((keep + 1)) | xargs -r rm -f
  ls -1t "$HOME"/backups/api-files-*.tgz 2>/dev/null | tail -n +$((keep + 1)) | xargs -r rm -f
}

# Respaldo completo (base + archivos). Aborta con fail si alguno no sale.
backup_all() {
  step "Respaldo automático (base + archivos del API)"
  backup_db || { fail "mysqldump falló o salió vacío"; return 1; }
  pass "base:     $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"
  backup_files || { fail "no se pudo empaquetar ~/${API_DIR#/}"; return 1; }
  pass "archivos: $FILES_BACKUP ($(du -h "$FILES_BACKUP" | cut -f1))"
  echo "$BACKUP_FILE" > "$WORK/previous-backup"
  echo "$FILES_BACKUP" > "$WORK/previous-files-backup"
  prune_backups 7
}

# Seeders permitidos. DatabaseSeeder nunca (datos de ejemplo); el de
# métodos de pago solo con la tabla vacía (primer despliegue): pisa los
# datos bancarios reales editados desde el admin.
seed_allowed() {
  local seeder=$1 db=$2
  case "$seeder" in
    DatabaseSeeder|AdminUserSeeder)
      fail "$seeder no se corre en producción (datos de desarrollo)"; return 1 ;;
    PaymentMethodSeeder)
      local n
      n="$(sql -e "SELECT COUNT(*) FROM \`$db\`.payment_methods" 2>/dev/null || echo 0)"
      if [ "${n:-0}" -gt 0 ]; then
        fail "PaymentMethodSeeder pisaría $n método(s) de pago reales; solo vale con la tabla vacía"; return 1
      fi ;;
  esac
  [[ "$seeder" =~ ^[A-Za-z0-9_\\]+$ ]] || { fail "Nombre de seeder inválido: $seeder"; return 1; }
  return 0
}

summary() {
  echo
  echo "==> Resultado: $FAILS error(es), $WARNS aviso(s). Log: $LOG"
}
