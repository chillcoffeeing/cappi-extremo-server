# Datos y migraciones

## Situación actual

Ya existen modelos para `User`, `OnboardingDraft`, `Participant`, `Plan`, `Enrollment`, `Order` y `Payment`. El plan aún guarda actividades y staff en JSON; el backoffice requiere estructura para administrar días, actividades y progreso.

## Tablas a agregar o ajustar

### `admin_users` o roles administrativos

Decidir entre un guard/modelo administrativo separado o roles en `users`. La opción recomendada es separar acceso administrativo del representante.

### `plan_days`

```text
id
plan_id
day_number
date
title
description
location
status
cover_url
sort_order
is_visible
created_at
updated_at
```

Estados: `PLANIFICADO`, `EN_CURSO`, `COMPLETADO`, `CANCELADO`.

### `plan_day_activities`

```text
id
plan_day_id
title
description
starts_at
ends_at
location
status
sort_order
requirements
created_at
updated_at
```

### `plan_announcements`

```text
id
plan_id
title
body
severity
starts_at
ends_at
is_visible
created_by
created_at
updated_at
```

### Progreso del plan

Agregar a `plans`:

```text
progress_mode       AUTO|MANUAL
progress_percent    decimal/integer
current_day_id      nullable
progress_label      nullable
progress_note       nullable
progress_updated_by nullable
progress_updated_at nullable
```

### Pagos

Agregar a `payments`:

```text
reviewed_by
reviewed_at
rejection_reason
```

Revisar `payments.order_id`: debe ser una relación consistente con `orders`, preferiblemente foreign key a `orders.id`, manteniendo `order_code` como identificador público.

### Inscripciones

Agregar relación explícita con la orden de inscripción si el negocio la requiere. Una inscripción no debe quedar desvinculada de su cargo financiero.

### Auditoría

Crear tabla propia o integrar `spatie/laravel-activitylog`.

## Reglas de integridad

- Solo un plan puede estar `PUBLICADO`, `EN_CURSO` o `PAUSADO`.
- Una publicación nueva debe bloquear o reemplazar transaccionalmente el plan activo anterior.
- Una orden no se marca pagada por un reporte pendiente.
- Un pago aprobado no puede superar el saldo permitido.
- Una inscripción activa no se duplica para el mismo participante y sesión.
- Cambiar el precio del plan no modifica órdenes existentes.
- El borrado de planes con inscripciones debe estar bloqueado; usar estados.

## Migración de contenido existente

1. Leer `plans.activities`.
2. Crear días con orden estable.
3. Crear actividades por día.
4. Copiar `plans.staff` a la estructura administrativa elegida.
5. Mantener compatibilidad temporal en `PlanResource`.
6. Retirar JSON solo después de migrar y validar el portal.
