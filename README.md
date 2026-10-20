# Cappi Xtremo — API + Admin

Backend de Cappi Xtremo: API REST JSON para el portal de familias y panel administrativo
(Filament) en la misma app Laravel. Repo `chillcoffeeing/cappi-extremo-server`.

| | Producción |
| --- | --- |
| API | `https://api.cappixtremo.com/api` (Bearer, Sanctum token-only) |
| Admin | `https://administrador.cappixtremo.com` (`ADMIN_PANEL_DOMAIN`; sin esa variable vive en `/admin`) |

Qué hace el sistema en conjunto: [../docs/producto.md](../docs/producto.md).

## Stack

PHP 8.3 · Laravel 13 · Sanctum 4 · Filament 4 · spatie/laravel-permission (roles del admin) ·
spatie/laravel-activitylog (auditoría) · MariaDB/MySQL en producción, SQLite en local y tests.

## Estructura

```text
app/Actions/{Admin,Auth,Inscriptions,Onboarding,Orders,Participants,Payments,Plans}
                         reglas de negocio (las usan API, Filament y tests)
app/Http/Controllers/Api  endpoints del portal (+ FormRequests y Resources camelCase)
app/Filament/             panel admin: Resources, Widgets, Support/AdminDeleteActions
app/Policies/             permisos por modelo (guard `admin`)
database/seeders/         PermissionSeeder (prod), PaymentMethodSeeder (solo 1.er despliegue),
                          AdminUserSeeder + DatabaseSeeder (solo local)
scripts/                  despliegue a cPanel (ensayo, respaldo, apply, rollback)
```

Dos guards separados: `admin` (tabla `admin_users`, sesión del panel) y Sanctum para
representantes (`users`). Un representante nunca entra al panel.

## Desarrollo

```powershell
composer install
copy .env.example .env; php artisan key:generate     # SQLite en database/database.sqlite
php artisan migrate --seed                           # roles, admin local, métodos de pago, plan
php artisan serve                                    # http://localhost:8000 (admin en /admin)
php artisan test                                     # SQLite en memoria
vendor/bin/pint
```

Admin local: `admin@cappixtremo.com` / `admin12345` (SUPER_ADMIN, solo desarrollo). Detalle en
[docs/development.md](docs/development.md).

## Desplegar

`.\scripts\deploy.ps1 --check` (ensayo en el servidor contra una copia de la base) y luego
`--apply`. Guía: [scripts/README.md](scripts/README.md) y [../docs/despliegue.md](../docs/despliegue.md).

## Documentación

[docs/README.md](docs/README.md): arquitectura, contrato HTTP, autenticación, backoffice.
