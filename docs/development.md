# Desarrollo

## Requisitos

PHP 8.3+ (con `pdo_sqlite` para local y tests) y Composer. Node no hace falta para el API.

## Puesta en marcha

Desde `api/`:

```powershell
composer install
copy .env.example .env; php artisan key:generate
php artisan migrate --seed      # SQLite: database/database.sqlite
php artisan serve               # API en http://localhost:8000/api, admin en /admin
```

`db:seed` (solo local) crea los roles y permisos, el admin `admin@cappixtremo.com` /
`admin12345` (SUPER_ADMIN), los métodos de pago de ejemplo y el plan decembrino publicado. No
crea representantes: se registran desde el portal (`../portal`, `pnpm dev`).

Sin `ADMIN_PANEL_DOMAIN` en el `.env` el panel vive en `/admin`; en producción vive en la raíz
de `administrador.cappixtremo.com`.

## Verificación

```powershell
php artisan test                 # SQLite en memoria (phpunit.xml)
vendor/bin/pint --test           # o vendor/bin/pint para corregir
php artisan route:list --path=api
..\init.ps1                      # arnés completo: tasklist + build del portal + tests del api
```

- Cada endpoint: éxito, `422` de validación y `401` si es privado. `RefreshDatabase` + factories.
- Los tests deben pasar también en MySQL/MariaDB: el ensayo de despliegue (`scripts/deploy.ps1
  --check`) los corre contra una copia de producción. Usar el Schema builder
  (`Schema::getForeignKeys`, `getColumns`…), nunca SQL de un motor (`PRAGMA`).
- Resources en camelCase y errores `{ "message": "..." }`.

## Subida de archivos

El API acepta comprobantes de hasta **10 MB** y fotos de hasta **5 MB**
(`app/Support/UploadRules.php`). PHP necesita `upload_max_filesize=12M` y `post_max_size=16M`:

- producción: `public/.user.ini` (puede tardar 5 min en aplicarse; si el hosting lo ignora,
  cPanel → *Select PHP Version → Options*);
- local: el `php.ini` que muestra `php --ini`.

Los archivos van a `storage/app/private` (comprobantes, privados) y `storage/app/public`
(fotos, imágenes de plan y productos; `php artisan storage:link`).

## Colas y correo

`QUEUE_CONNECTION=sync`: no hay workers en el hosting compartido, todo se hace dentro del
request. No hay correo saliente configurado (ver `../../docs/producto.md` → Integraciones).

## No versionar

`.env`, `vendor/`, `database/database.sqlite`, `storage/app/*`, `public/storage`, `composer.phar`.
