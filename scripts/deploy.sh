#!/usr/bin/env bash
# Despliegue y actualización del API (Laravel + Filament) en cPanel por SSH.
# Guía: scripts/README.md · Procedimiento manual equivalente: ../docs/update-cpanel.md
#
#   bash scripts/deploy.sh                      = --check
#   bash scripts/deploy.sh --check              ensayo EN EL SERVIDOR sin tocar producción: copia la
#                                               base y la app, migra la copia, detecta pérdida de
#                                               datos, rutas que desaparecen, prueba rollback y tests
#   bash scripts/deploy.sh --apply              ensayo + confirmación + despliegue real
#   bash scripts/deploy.sh --rollback           código al commit anterior al último --apply
#   bash scripts/deploy.sh --rollback --with-migrations   además revierte las migraciones que entraron
#   bash scripts/deploy.sh --init               primer despliegue (clona en la carpeta vacía)
#   bash scripts/deploy.sh --backup             respaldo completo ahora (base + archivos); --apply y
#                                               --rollback lo hacen solos antes de tocar nada
#
# Opciones:
#   --seed=Clase          seeder a correr (repetible). DatabaseSeeder/AdminUserSeeder nunca;
#                         PaymentMethodSeeder solo con la tabla vacía (primer despliegue)
#   --tests=mysql|sqlite|skip   tests del ensayo (por defecto mysql, contra la copia)
#   --allow-destructive   aplica aunque el ensayo detecte tablas/columnas/filas perdidas
#   --force-check         ensaya aunque no haya commits nuevos
#   --yes                 no pide confirmación
#
# Lee el .env de la raíz del monorepo (../.env; otro: DEPLOY_ENV_FILE=ruta):
#   IP_SSH_FOR_DEPLOY, USER_SSH_FOR_DEPLOY, PORT_SSH_FOR_DEPLOY   obligatorias
#   KEY_SSH_FOR_DEPLOY, PHRASE_SSH_FOR_DEPLOY                     clave y su frase
#   API_PATH_FOR_DEPLOY      carpeta del API en el home (api.cappixtremo.com)
#   API_URL_FOR_DEPLOY       https://api.cappixtremo.com
#   ADMIN_URL_FOR_DEPLOY     https://administrador.cappixtremo.com
#   PHP_BIN_FOR_DEPLOY       binario de PHP en el servidor (php)
#   DRYRUN_DB_FOR_DEPLOY     base vacía creada a mano para el ensayo, si uapi no funciona
#   GIT_REPO_URL_FOR_DEPLOY  solo --init: https://<token>@github.com/... del repo
# DOMAIN_ROOT_FOR_DEPLOY / DOMAIN_FOR_DEPLOY NO se usan aquí (son de otro proyecto).
set -euo pipefail

SELF="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
cd "$(dirname "$0")/.."

MODE=check; SEEDS=""; TESTS=mysql; ALLOW_DESTRUCTIVE=0; FORCE_CHECK=0; YES=0; WITH_MIGRATIONS=0
for arg in "$@"; do
  case "$arg" in
    --check|--dry-run) MODE=check ;;
    --apply) MODE=apply ;;
    --rollback) MODE=rollback ;;
    --init) MODE=init ;;
    --backup) MODE=backup ;;
    --seed=*) s="${arg#--seed=}"; SEEDS="${SEEDS:+$SEEDS,}$s" ;;
    --tests=mysql|--tests=sqlite|--tests=skip) TESTS="${arg#--tests=}" ;;
    --allow-destructive) ALLOW_DESTRUCTIVE=1 ;;
    --force-check) FORCE_CHECK=1 ;;
    --with-migrations) WITH_MIGRATIONS=1 ;;
    --yes) YES=1 ;;
    -h|--help) sed -n '2,/^set -euo/{/^set -euo/d;p}' "$SELF"; exit 0 ;;
    *) echo "Opción desconocida: $arg (ver --help)" >&2; exit 2 ;;
  esac
done

# --- .env ----------------------------------------------------------------------------------------
ENV_FILE="${DEPLOY_ENV_FILE:-../.env}"
[ -f "$ENV_FILE" ] || { echo "No encuentro $ENV_FILE (el .env de la raíz del monorepo)." >&2; exit 1; }
env_get() { { grep -E "^$1=" "$ENV_FILE" || true; } | tail -1 | cut -d= -f2- | tr -d '\r' | sed -E 's/^"(.*)"$/\1/'; }

HOST="$(env_get IP_SSH_FOR_DEPLOY)"
SSH_USER="$(env_get USER_SSH_FOR_DEPLOY)"
PORT="$(env_get PORT_SSH_FOR_DEPLOY)"
PHRASE="$(env_get PHRASE_SSH_FOR_DEPLOY)"
KEY="$(env_get KEY_SSH_FOR_DEPLOY)"; KEY_SET="$KEY"
KEY="${KEY:-$HOME/.ssh/id_ed25519}"; KEY="${KEY/#\~/$HOME}"
command -v cygpath >/dev/null 2>&1 && KEY="$(cygpath -u "$KEY")"
API_DIR="$(env_get API_PATH_FOR_DEPLOY)"; API_DIR="${API_DIR:-api.cappixtremo.com}"; API_DIR="${API_DIR#/}"; API_DIR="${API_DIR%/}"
API_URL="$(env_get API_URL_FOR_DEPLOY)"; API_URL="${API_URL:-https://api.cappixtremo.com}"
ADMIN_URL="$(env_get ADMIN_URL_FOR_DEPLOY)"; ADMIN_URL="${ADMIN_URL:-https://administrador.cappixtremo.com}"
PHP_BIN="$(env_get PHP_BIN_FOR_DEPLOY)"; PHP_BIN="${PHP_BIN:-php}"
DRYRUN_DB="$(env_get DRYRUN_DB_FOR_DEPLOY)"
BRANCH=main

for v in HOST SSH_USER PORT; do [ -n "${!v}" ] || { echo "Falta $v en $ENV_FILE." >&2; exit 1; }; done
[ -n "$KEY_SET" ] && [ ! -f "$KEY" ] && { echo "KEY_SSH_FOR_DEPLOY apunta a un archivo que no existe: $KEY" >&2; exit 1; }
case "$API_DIR" in
  ""|public_html*|*bysensei*|*..*) echo "API_PATH_FOR_DEPLOY='$API_DIR' no parece la carpeta del API de Cappi; me niego." >&2; exit 1 ;;
esac

# --- SSH -----------------------------------------------------------------------------------------
ASKPASS=""
cleanup() { [ -n "$ASKPASS" ] && rm -f "$ASKPASS"; return 0; }
trap cleanup EXIT
if [ -n "$PHRASE" ]; then
  # La frase viaja por variable de entorno al helper, nunca por la línea de comandos.
  ASKPASS="$(mktemp)"
  printf '#!/bin/sh\nprintf "%%s\\n" "$DEPLOY_SSH_PHRASE"\n' > "$ASKPASS"
  chmod 700 "$ASKPASS"
  export DEPLOY_SSH_PHRASE="$PHRASE" SSH_ASKPASS="$ASKPASS" SSH_ASKPASS_REQUIRE=force DISPLAY="${DISPLAY:-:0}"
fi
SSH_OPTS=(-p "$PORT" -o ConnectTimeout=20 -o ServerAliveInterval=15)
[ -f "$KEY" ] && SSH_OPTS+=(-i "$KEY" -o IdentitiesOnly=yes)
remote() { ssh "${SSH_OPTS[@]}" "$SSH_USER@$HOST" "$@"; }

q() { printf '%q' "$1"; }
REMOTE_ENV="API_DIR=$(q "$API_DIR") BRANCH=$BRANCH PHP_BIN=$(q "$PHP_BIN") SEEDS=$(q "$SEEDS")"

confirm() {  # $1 = palabra que hay que escribir
  [ "$YES" = 1 ] && return 0
  [ -t 0 ] || { echo "Sin terminal interactiva: usa --yes para confirmar." >&2; exit 1; }
  local ans; read -r -p "    Escribe $1 para continuar: " ans
  [ "$ans" = "$1" ] || { echo "Cancelado."; exit 1; }
}

# --- Comprobaciones locales ----------------------------------------------------------------------
preflight() {
  echo "==> Repositorio local (api/)"
  local strict=$1
  if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    [ "$strict" = 1 ] && { echo "    [FAIL] hay cambios sin commit: el servidor despliega origin/$BRANCH, no tu copia." >&2; exit 1; }
    echo "    [WARN] hay cambios sin commit: el ensayo usa origin/$BRANCH, no tu copia"
  fi
  git fetch -q origin "$BRANCH"
  local ahead behind
  ahead="$(git rev-list --count "origin/$BRANCH..HEAD")"; behind="$(git rev-list --count "HEAD..origin/$BRANCH")"
  if [ "$ahead" -gt 0 ]; then
    [ "$strict" = 1 ] && { echo "    [FAIL] tienes $ahead commit(s) sin push: haz git push primero." >&2; exit 1; }
    echo "    [WARN] $ahead commit(s) sin push: el ensayo no los incluye"
  fi
  [ "$behind" -gt 0 ] && echo "    [WARN] origin/$BRANCH tiene $behind commit(s) que no tienes en local"
  echo "    se despliega origin/$BRANCH = $(git log --oneline -1 "origin/$BRANCH")"
}

upload_tools() {
  echo "==> Subiendo herramientas a ~/deploy/api/tools ($SSH_USER@$HOST:$PORT)"
  tar czf - -C scripts/remote . | remote 'rm -rf ~/deploy/api/tools && mkdir -p ~/deploy/api/tools && tar xzf - -C ~/deploy/api/tools && sed -i "s/\r$//" ~/deploy/api/tools/*.sh'
}

run_check() {
  remote "$REMOTE_ENV TESTS=$TESTS ALLOW_DESTRUCTIVE=$ALLOW_DESTRUCTIVE FORCE_CHECK=$FORCE_CHECK DRYRUN_DB=$(q "$DRYRUN_DB") bash ~/deploy/api/tools/check.sh"
}

smoke_public() {
  command -v curl >/dev/null 2>&1 || return 0
  echo "==> Comprobación pública"
  local code bad=0
  code="$(curl -s -o /dev/null -w '%{http_code}' "$API_URL/api/planes/activo" || echo 000)"
  echo "    $API_URL/api/planes/activo  $code (esperado 200)"; [ "$code" = 200 ] || bad=1
  code="$(curl -s -o /dev/null -w '%{http_code}' "$ADMIN_URL/login" || echo 000)"
  echo "    $ADMIN_URL/login  $code (esperado 200)"; [ "$code" = 200 ] || bad=1
  [ "$bad" = 0 ] || echo "    [WARN] algo no responde como se esperaba: revisa ~/${API_DIR}/storage/logs/laravel.log"
}

case "$MODE" in
  check)
    preflight 0
    upload_tools
    run_check
    ;;
  apply)
    preflight 1
    upload_tools
    echo "==> Ensayo previo (obligatorio)"
    run_check || { echo "==> El ensayo falló: no se aplica nada." >&2; exit 1; }
    echo
    echo "==> Listo para aplicar origin/$BRANCH en PRODUCCIÓN (~/$API_DIR). Seeders: ${SEEDS:-ninguno}"
    confirm DESPLEGAR
    remote "$REMOTE_ENV bash ~/deploy/api/tools/apply.sh"
    smoke_public
    echo "Si algo falla: bash scripts/deploy.sh --rollback  (agrega una fila al historial de docs/update-cpanel.md)"
    ;;
  rollback)
    upload_tools
    echo "==> Rollback del código del API en PRODUCCIÓN$([ "$WITH_MIGRATIONS" = 1 ] && echo ' + migraciones')"
    confirm REVERTIR
    remote "$REMOTE_ENV ROLLBACK_MIGRATIONS=$WITH_MIGRATIONS bash ~/deploy/api/tools/rollback.sh"
    smoke_public
    ;;
  backup)
    upload_tools
    remote "$REMOTE_ENV bash ~/deploy/api/tools/backup.sh"
    ;;
  init)
    REPO_URL="$(env_get GIT_REPO_URL_FOR_DEPLOY)"
    [ -n "$REPO_URL" ] || { echo "Falta GIT_REPO_URL_FOR_DEPLOY en $ENV_FILE (https://<token>@github.com/chillcoffeeing/cappi-extremo-server.git)." >&2; exit 1; }
    upload_tools
    echo "==> Primer despliegue en ~/$API_DIR"
    confirm INICIAR
    remote "$REMOTE_ENV bash ~/deploy/api/tools/init.sh" <<< "$REPO_URL"
    ;;
esac
