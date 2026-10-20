# AGENTS.md — api/ (Laravel + Filament)

Mapa para agentes que trabajan en este paquete. El arnés y las reglas globales están en
`../AGENTS.md` (una feature a la vez, estado en `progress/`, contrato entre paquetes, manual).

## Leer antes de tocar código

| Necesito | Archivo |
| --- | --- |
| Qué es y cómo se corre | `README.md` |
| Límites y flujo de un endpoint | `docs/architecture.md` |
| Contrato HTTP (canónico junto con `../portal/docs/business-logic/12-api-contrato.md`) | `docs/api-contract.md` |
| Tokens, refresh, rate limiting | `docs/authentication.md` |
| Panel admin: roles, recursos, flujos, auditoría | `docs/backoffice/README.md` |
| Comandos, tests, convenciones de código | `docs/development.md` + `docs/laravel-initial-rules.md` |
| Desplegar | `scripts/README.md` + `../docs/despliegue.md` |

## Reglas

- Reglas de negocio en `app/Actions/...`; controllers y Filament solo orquestan.
- Respuestas camelCase y errores `{ "message": "..." }`. Dinero `decimal(16,4)`.
- Cambiar un endpoint = actualizar `docs/api-contract.md` **y** el contrato del portal.
- Toda acción del admin pasa por una Policy (`can(...)`); los permisos nuevos van en
  `PermissionSeeder` y el despliegue debe correrlo (`--seed=PermissionSeeder`).
- Migraciones pensadas para datos reales: aditivas siempre que se pueda (ver
  `../docs/despliegue.md` → Migraciones). El ensayo `scripts/deploy.ps1 --check` lo verifica.
- Tests: `php artisan test` (SQLite) en verde; el ensayo de despliegue los corre también contra
  MySQL, así que nada específico de SQLite (`PRAGMA`…) en los tests.
- Pint: `vendor/bin/pint` sobre los archivos tocados.
