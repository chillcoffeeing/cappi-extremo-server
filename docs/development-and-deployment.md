# Desarrollo y despliegue

## Requisitos

- PHP 8.3 o superior.
- Composer.
- MySQL en desarrollo y producción.
- Node solo para compilar assets; Namecheap compartido no ejecuta Node en producción.

## Comandos

Desde `api/`:

```powershell
php artisan serve
php artisan migrate
php artisan test
vendor/bin/pint --test
```

Antes de desarrollar una funcionalidad, verificar `php -v` y `composer -V`. Las pruebas de endpoints deben cubrir éxito, `422` de validación y `401` cuando la ruta sea privada. Usar `RefreshDatabase` y SQLite en memoria cuando sea compatible con el caso.

## Orden de implementación

1. Migraciones, modelos, relaciones, factories y seeders.
2. Lecturas básicas: representante, participantes, plan e inscripción.
3. Sanctum y flujo de registro/login/refresh/logout.
4. Escrituras y endpoints de pagos, órdenes y fichas individuales.
5. Conectar un service del portal a la vez, empezando por participantes.
6. Admin y operaciones de aprobación.

## Producción en Namecheap

El docroot apunta a `api/public`. El build del portal se genera fuera del hosting y se copia a `public/portal/` cuando corresponda.

```text
npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Variables esenciales: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` real, `APP_URL` real, credenciales MySQL, CORS para el subdominio del portal y `QUEUE_CONNECTION=sync` salvo cron configurado.

### Límites de subida de archivos (F-035)

El API acepta comprobantes de hasta **10 MB** y fotos de hasta **5 MB**
(`app/Support/UploadRules.php`). Los defaults de PHP (`upload_max_filesize=2M`,
`post_max_size=8M`) hacen que una foto de celular de 3-5 MB falle con
"no se pudo subir" y que un envío mayor a 8 MB responda `413`. Requerido en
el servidor (y en el PHP local de desarrollo):

| Directiva             | Valor mínimo |
| --------------------- | ------------ |
| `upload_max_filesize` | `12M`        |
| `post_max_size`       | `16M`        |
| `client_max_body_size` (solo si hay nginx delante) | `16m` |

- `api/public/.user.ini` ya fija esos valores para PHP-FPM/CGI/LSAPI
  (hosting compartido con docroot en `api/public`). Puede tardar hasta
  `user_ini.cache_ttl` (5 min) en aplicarse.
- Si el hosting ignora `.user.ini` (mod_php), fijarlos en cPanel → *MultiPHP
  INI Editor* / *Select PHP Version → Options*.
- En local (`php artisan serve` usa el `php.ini` del CLI), editar el
  `php.ini` que muestra `php --ini`.
- Comprobar con `php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");'`
  o una ruta temporal con `phpinfo()` en el servidor.

Escribir únicamente en `storage/` y `bootstrap/cache/`. No versionar `.env`, `vendor/`, `node_modules/` ni `public/build/`.

## Verificación de entrega

- `php artisan test`
- `vendor/bin/pint --test`
- `php artisan route:list --path=api`
- Revisar que las rutas privadas tengan `auth:sanctum`.
- Comprobar que Resources produzcan camelCase y que los errores mantengan `{ message }`.
