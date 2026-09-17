# Contrato API

El contrato completo y canónico está en [portal/docs/business-logic/12-api-contrato.md](../../portal/docs/business-logic/12-api-contrato.md). Este resumen sirve como guía local al implementar rutas y debe mantenerse sincronizado.

## Convenciones

- Base local: `/api`.
- Autenticación: `Authorization: Bearer <access-token>`.
- JSON público siempre en camelCase.
- Fechas como `YYYY-MM-DD` salvo que el contrato indique fecha y hora.
- Dinero como número JSON respaldado por `decimal(16,4)` en MySQL; no usar `float` para cálculos.
- `200` para lecturas y actualizaciones, `201` para creaciones y `204` para eliminaciones o acciones sin cuerpo.
- Errores: `{ "message": "..." }`, con `422` para validación, `401` para sesión inválida y `404` para recursos inexistentes.
- CORS permite el portal y `localhost:5173` en desarrollo. No usar credenciales de cookie.

## Rutas previstas

## Estado de implementación

Implementado y probado:

- `POST /api/auth/register`, `POST /api/auth/login` y `POST /api/auth/logout`.
  Registro y login devuelven el shape camelCase del portal; logout revoca los
  tokens Sanctum persistidos.
- `GET /api/participantes` (`participants.index`), protegido por `auth:sanctum`.
  Devuelve únicamente los participantes del usuario autenticado con Resource
  camelCase y `datosCompletos`.
- Participantes: detalle, PATCH profundo por sección y wizard individual
  (`GET /wizard`, `POST /wizard/step`, `POST /wizard/complete`).
- Onboarding general: draft persistente, pasos idempotentes y completion.
- Cuenta, plan activo, pagos, órdenes y catálogo de productos.

Pendiente:

- Auth avanzada pendiente: recuperación de contraseña y verificación de email.
- Recuperación/verificación de Auth y seeders de plan, métodos de pago y catálogo.
- Cuenta, plan, inscripciones, pagos, órdenes y catálogo.

La migración `participants` y el modelo `Participant` ya existen. No se deben
crear rutas de los dominios pendientes hasta tener su Action, Request, Resource
y test Feature correspondientes.

El refresh rotativo ya está implementado: los tokens opacos se almacenan
hasheados, expiran en 24 horas o 30 días según `remember`, se revocan al usarlo
y el replay del token anterior devuelve `401`.

### Públicas

- `POST /auth/login`
- `POST /auth/register`
- `POST /auth/refresh`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `POST /auth/verify-email`

### Requieren `auth:sanctum`

- `POST /auth/logout`
- `GET|PUT /representante`
- `GET|PATCH /participantes`
- `GET /planes/activo`
- `GET /inscripciones/{participante}/detalle`
- `GET /pagos/balance`
- `GET|POST /pagos`
- `GET|POST /ordenes`
- `POST /ordenes/{id}/enlazar-pago`
- `GET /participantes/{id}/wizard`, `POST /participantes/{id}/wizard/step` y `POST /participantes/{id}/wizard/complete`
- `GET|POST /onboarding/{draftId}` y `POST /onboarding/{draftId}/complete`
- `GET /config/metodos-pago`
- `GET /tienda/productos`

Las rutas deben tener nombres explícitos, por ejemplo `pagos.index` y `pagos.store`, para que puedan documentarse y consumirse sin dispersar URLs.

## Receta de implementación

1. Crear la Action con `handle()` y lógica independiente de HTTP.
2. Crear Controller API para orquestar Request -> Action -> Resource.
3. Crear FormRequest y API Resource.
4. Registrar la ruta nombrada en `routes/api.php`.
5. Añadir migración, factory/seeder y test Feature cuando corresponda.

## Compatibilidad con el portal

No cambiar nombres de campos para reflejar nombres de columnas Laravel. Si una respuesta necesita transformar `email` a `correo`, esa conversión vive en el Request/Resource y queda cubierta por un test de contrato.
