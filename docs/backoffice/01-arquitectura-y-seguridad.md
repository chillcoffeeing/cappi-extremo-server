# Arquitectura y seguridad

## Panel Filament

Instalar Filament en la aplicación Laravel y crear un panel administrativo separado del API público:

```text
/admin
```

En producción se recomienda `admin.cappixtremo.com`. El API continuará usando Bearer tokens y el portal público no compartirá credenciales administrativas.

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
```

Toda acción de Resource debe estar protegida por Policy, incluso si el botón se oculta en Filament.

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
