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

Escribir únicamente en `storage/` y `bootstrap/cache/`. No versionar `.env`, `vendor/`, `node_modules/` ni `public/build/`.

## Verificación de entrega

- `php artisan test`
- `vendor/bin/pint --test`
- `php artisan route:list --path=api`
- Revisar que las rutas privadas tengan `auth:sanctum`.
- Comprobar que Resources produzcan camelCase y que los errores mantengan `{ message }`.
