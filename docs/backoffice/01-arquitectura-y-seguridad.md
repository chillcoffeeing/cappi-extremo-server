# Arquitectura y seguridad

## Panel Filament

Instalar Filament en la aplicación Laravel y crear un panel administrativo separado del API público:

```text
/admin
```

En producción el panel vive en la raíz de `administrador.cappixtremo.com` (`ADMIN_PANEL_DOMAIN`;
sin esa variable, en `/admin` sobre cualquier host). El API sigue usando Bearer tokens y el
portal no comparte credenciales administrativas.

## Autenticación

- Guard administrativo independiente.
- Login administrativo separado del login de representantes.
- Contraseñas con hash de Laravel.
- Sesión administrativa con expiración.
- Revocación de sesiones al desactivar un admin.
- Rate limiting para login.
- MFA como mejora prioritaria para `SUPER_ADMIN` y `FINANZAS`.
- No exponer comprobantes mediante URLs públicas permanentes. **Hecho (F-004)** en
  el lado API/portal: `comprobantes` ahora vive en disco `local` (privado) y se
  sirve solo al representante dueño vía `GET /api/pagos/{payment}/comprobante`
  (Sanctum). El acceso administrativo con permiso financiero se resuelve en
  Fase 4, cuando exista `PaymentResource`.

## Autorización

Usar Policies y permisos explícitos, preferiblemente con `spatie/laravel-permission`.

Permisos mínimos:

```text
admin.access
users.view
users.edit
onboarding.view
onboarding.manage
plans.view
plans.manage
plan_content.manage
participants.view
participants.edit
enrollments.view
enrollments.manage
orders.view
orders.manage
payments.view
payments.approve
payments.reject
payment_methods.manage
products.manage
reports.export
audit.view
admin_users.manage
records.delete
corrections.manage
payments.receipts
```

`records.delete` (F-053): borrado físico de representantes, participantes, inscripciones,
borradores de onboarding y solicitudes de corrección. Solo SUPER_ADMIN. Ver
`06-flujos-y-auditoria.md` → Retención.

`corrections.manage` (F-062, api-A-6): solicitar y resolver solicitudes de corrección de ficha
sin poder editar participantes. `ParticipantCorrectionRequestPolicy::create/update` acepta
`participants.edit` **o** `corrections.manage`.

`payments.receipts` (F-062, api-A-9): descargar comprobantes de pago en el panel
(`PaymentPolicy::downloadReceipt`). `payments.view` solo deja ver la tabla.

### Matriz de roles (fuente: `PermissionSeeder`, fijada en `PermissionMatrixTest`)

| Permiso | SUPER_ADMIN | OPERACIONES | FINANZAS | SOPORTE | LECTURA |
| --- | :-: | :-: | :-: | :-: | :-: |
| `admin.access` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `users.view` | ✓ | | | ✓ | ✓ |
| `users.edit` | ✓ | | | ✓ | |
| `onboarding.view` | ✓ | ✓ | | ✓ | ✓ |
| `onboarding.manage` | ✓ | ✓ | | ✓ | |
| `plans.view` | ✓ | ✓ | | | ✓ |
| `plans.manage`, `plan_content.manage` | ✓ | ✓ | | | |
| `participants.view` | ✓ | ✓ | | ✓ | ✓ |
| `participants.edit` | ✓ | ✓ | | | |
| `corrections.manage` (F-062) | ✓ | ✓ | | ✓ | |
| `enrollments.view` | ✓ | ✓ | | | ✓ |
| `enrollments.manage` | ✓ | ✓ | | | |
| `orders.view` | ✓ | | ✓ | | ✓ |
| `orders.manage` | ✓ | | ✓ | | |
| `payments.view` | ✓ | | ✓ | | ✓ |
| `payments.approve`, `payments.reject` | ✓ | | ✓ | | |
| `payments.receipts` (F-062) | ✓ | | ✓ | | |
| `payment_methods.manage`, `products.manage`, `reports.export` | ✓ | | ✓ | | |
| `audit.view` | ✓ | | | | ✓ |
| `admin_users.manage`, `records.delete` | ✓ | | | | |

Acciones del panel que dependen de la matriz (F-062):

- **Solicitar corrección / Resolver** (ficha del participante): `participants.edit` o
  `corrections.manage` → SUPER_ADMIN, OPERACIONES, SOPORTE. LECTURA ve las solicitudes pero no
  ve ni puede ejecutar las acciones (tampoco llamándolas por Livewire).
- **Comprobante** (tablas de pagos): `payments.receipts` → SUPER_ADMIN, FINANZAS. LECTURA ve
  la tabla de pagos pero no descarga el archivo. Si el archivo falta en el disco, el panel avisa
  en vez de bajar un archivo vacío.

Cuando se agregan permisos, el despliegue corre `php artisan db:seed --class=PermissionSeeder
--force` y `php artisan permission:cache-reset` (el seeder solo sincroniza permisos de roles; no
cambia el rol de ningún admin).

Toda acción de Resource debe estar protegida por Policy, incluso si el botón se oculta en Filament.
En las acciones de Filament se usa `->authorize(...)` (no solo `->visible(...)`): Filament rechaza
entonces también la llamada directa por Livewire.

## Separación de datos

- Un representante solo ve sus datos desde el portal.
- Un administrador ve datos según sus permisos.
- Soporte no puede aprobar pagos.
- Lectura no puede ejecutar mutations.
- Los comprobantes solo son accesibles a usuarios con permiso financiero.

## Acciones de negocio

Filament debe invocar Actions como:

```text
PublishPlan
PausePlan
UpdatePlanProgress
ApprovePayment
RejectPayment
CancelOrder
RequestParticipantCorrection
CompleteOnboarding
```

Las Actions deben ser reutilizables desde API, Filament, comandos y jobs; no deben recibir objetos HTTP ni devolver Responses.

## Auditoría

Registrar actor, acción, modelo, valores anteriores, valores nuevos, IP, user agent y fecha. Las acciones financieras, cambios de estado y publicaciones de contenido nunca deben quedar sin auditoría.

`Order` audita `status`, `paid`, `total` e `items` (F-062, api-A-14: el recálculo de
inscripciones y el borrado de participantes cambian `total`/`items`). Solo aplica a cambios
posteriores al despliegue; el `activity_log` existente no se reescribe.
