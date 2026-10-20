# Despliegue del API (scripts/deploy.sh)

Automatiza [../../docs/update-cpanel.md](../../docs/update-cpanel.md) (actualizar) y el Paso 2 de
[../../docs/despliegue-cpanel.md](../../docs/despliegue-cpanel.md) (primer despliegue). Desde
PowerShell: `.\scripts\deploy.ps1 <opciones>` (usa Git Bash). Lee el `.env` de la raíz del
monorepo (`../.env`), que no está en ningún repo.

El servidor despliega **`origin/main`**, no tu copia local: primero `git push`.

## Flujo normal

```powershell
.\scripts\deploy.ps1 --check                          # ensayo en el servidor, no cambia producción
.\scripts\deploy.ps1 --apply --seed=PermissionSeeder  # ensayo otra vez + confirmación + despliegue
.\scripts\deploy.ps1 --rollback                       # si algo sale mal (solo código)
```

Luego, el portal: `cd ..\portal; .\scripts\deploy.ps1`. **API primero, portal inmediatamente después.**

## Respaldos (automáticos)

`--apply` y `--rollback` respaldan **antes de tocar nada**, y sin respaldo no siguen:

- base: `~/backups/cappi-<fecha>.sql` (mysqldump);
- archivos: `~/backups/api-files-<fecha>.tgz` (código con `.git`, `.env`, `storage/` con fotos y comprobantes; sin `vendor/`).

Se conservan los 7 últimos de cada uno. `--backup` hace lo mismo bajo demanda. Portal y landing
respaldan la carpeta publicada completa en `~/deploy/<pieza>/backups/` en cada publicación
(también `--backup`).

## Qué hace el ensayo (`--check`)

Todo pasa en el servidor, en `~/deploy/api/`, contra **copias**:

| Paso | Bloquea (FAIL) | Avisa (WARN) |
| --- | --- | --- |
| Repo de producción | archivos editados a mano en el servidor; historia reescrita | sin commits nuevos |
| Qué cambia | — | migraciones existentes modificadas; `composer.*`; seeders; claves nuevas de `.env.production.example` que faltan en el `.env` |
| Escaneo de migraciones nuevas | — | `drop*`, `rename`, `change()`, `delete`, SQL crudo |
| Copia de la base (`<usuario>_cappidry`, vía `uapi`) y del código (`staging/`) | la copia no apunta a la base de ensayo (candado antes de migrar) | — |
| `migrate --pretend` + `migrate` sobre la copia | la migración falla | SQL con `DROP`/`TRUNCATE`/`DELETE`/`RENAME`/`MODIFY` |
| Seeders pedidos con `--seed` | `DatabaseSeeder`/`AdminUserSeeder`; `PaymentMethodSeeder` con métodos ya cargados | — |
| Antes/después de la copia | **tablas o columnas que desaparecen, filas que se pierden** | columnas que cambian de tipo o de nulabilidad |
| Portal y admin | rutas `api/*` que desaparecen; peticiones de humo con 5xx | — |
| `migrate:rollback` + `migrate` | — | el `down()` no funciona |
| `php artisan test` contra MySQL (la copia) | tests en rojo | `--tests=skip` |

Al terminar borra la base de ensayo y la copia del código. El log queda en
`~/deploy/api/logs/check-<fecha>.log`. Con mail en `log` y colas en `sync`, el ensayo no envía
correos.

No cuentan como pérdida las tablas volátiles (`cache`, `cache_locks`, `sessions`, `jobs`,
`job_batches`) ni los cambios de solo permisos en git (`chmod -R 775 storage`). Los tests corren
sin `ADMIN_PANEL_DOMAIN` (esperan el panel en `/admin`, como en local). En este hosting composer
no está en el PATH de SSH: se usa `~/api.cappixtremo.com/composer.phar`.

Una ruta `api/*` quitada a propósito (y que el portal no usa) se acepta con
`--allow-removed-route="GET api/x"` en el `--check` y en el `--apply`.

Una actualización destructiva a propósito (por ejemplo, quitar una columna que ya no se usa)
necesita `--allow-destructive` en el `--check` y en el `--apply`.

## Qué hace `--apply`

1. Repite el ensayo. Si falla, no toca nada.
2. Pide escribir `DESPLEGAR`.
3. En el servidor, solo si el último ensayo pasó para **el mismo commit** y los mismos seeders:
   - `mysqldump` a `~/backups/cappi-<fecha>.sql`. Sin respaldo no sigue.
   - `php artisan down --secret=…` si hay migraciones, dependencias o seeders. Imprime la URL de acceso de emergencia.
   - `git merge --ff-only`, `composer install --no-dev` (si cambió), `migrate --force`, seeders, `permission:cache-reset` (con `PermissionSeeder`), `optimize:clear` + `optimize`, `storage:link`, permisos y `up`.
   - Si algo falla después del `down`, **se queda en mantenimiento** a propósito y dice cómo seguir.
4. Comprueba `api/planes/activo` y el login del admin desde fuera.

Guarda el commit anterior en `~/deploy/api/previous-commit` y el historial en `~/deploy/api/history.log`.
Conserva los 7 últimos `.sql`. Agrega tú la fila al historial de `docs/update-cpanel.md`.

## Rollback

- `--rollback`: código al commit anterior + `composer install` si hace falta. La base no se toca.
  Con migraciones aditivas, el código viejo las ignora.
- `--rollback --with-migrations`: además corre `migrate:rollback` de las migraciones que entraron
  (antes de volver el código; el ensayo ya probó esos `down()`).
- Restaurar el `.sql` reemplaza **toda** la base: último recurso, a mano (docs/update-cpanel.md §5).

## Primer despliegue (`--init`)

Necesita `GIT_REPO_URL_FOR_DEPLOY=https://<token>@github.com/chillcoffeeing/cappi-extremo-server.git`
en el `.env` de la raíz. Clona en `~/api.cappixtremo.com` (debe estar vacía; nunca borra nada),
corre `composer install` y crea el `.env` con `APP_KEY`. Después:

1. Editar el `.env` del servidor (base de datos, `APP_DEBUG=false`).
2. `--check --seed=PermissionSeeder --seed=PaymentMethodSeeder`.
3. `--apply --seed=PermissionSeeder --seed=PaymentMethodSeeder`.
4. Crear el admin real con `tinker` y editar los métodos de pago reales.

## Variables del `.env` de la raíz

| Variable | Por defecto |
| --- | --- |
| `IP_SSH_FOR_DEPLOY`, `USER_SSH_FOR_DEPLOY`, `PORT_SSH_FOR_DEPLOY` | obligatorias |
| `KEY_SSH_FOR_DEPLOY`, `PHRASE_SSH_FOR_DEPLOY` | `~/.ssh/id_ed25519`; la frase va por `SSH_ASKPASS`, nunca en la línea de comandos |
| `API_PATH_FOR_DEPLOY` | `api.cappixtremo.com` |
| `API_URL_FOR_DEPLOY` / `ADMIN_URL_FOR_DEPLOY` | `https://api.cappixtremo.com` / `https://administrador.cappixtremo.com` |
| `PHP_BIN_FOR_DEPLOY` | `php` |
| `DRYRUN_DB_FOR_DEPLOY` | vacío: el ensayo crea y borra `<usuario>_cappidry` con `uapi`. Si `uapi` no funciona, crea en cPanel una base vacía **solo para esto**, dale permisos al usuario de la app y pon su nombre aquí (el ensayo la vacía cada vez) |
| `GIT_REPO_URL_FOR_DEPLOY` | solo `--init` |

`DOMAIN_ROOT_FOR_DEPLOY` y `DOMAIN_FOR_DEPLOY` no se usan: son del proyecto que vive en el
dominio primario de la cuenta. Los scripts se niegan a publicar en `public_html` o `bysensei*`.
