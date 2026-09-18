# Datos y migraciones

## Situación actual

Ya existen modelos para `User`, `OnboardingDraft`, `Participant`, `Plan`, `Enrollment`, `Order`, `Payment`, `Product`, `PlatformSetting`, `AdminUser`, `PlanDay`, `PlanDayActivity` y `PlanAnnouncement`. El plan sigue guardando `staff`/`mini_market` en JSON (sin cambios en Fase 2); `activities` y `days` (JSON legado) quedan en compatibilidad temporal junto a las tablas estructuradas nuevas — ver "Migración de contenido existente" más abajo. `PlatformSetting` **(deprecada desde F-017)** era una fila única de configuración de negocio que solo controlaba el descuento por hermanos (`sibling_discount_enabled/min_participants/amount`); ese descuento ahora se configura por plan (columnas homónimas en `plans`, editables desde el tab "Precio y cupos" del recurso Plan) y `platform_settings` ya no se lee en ningún flujo en tiempo de ejecución — se conserva solo como respaldo histórico del valor que tenían los planes ya existentes antes de la migración de datos que copió su valor a cada uno.

## Tablas a agregar o ajustar

### `admin_users`

Tabla y guard administrativo separados de `users`. Los administradores no comparten tabla con los representantes del portal: evita mezclar el guard de Sanctum del portal con el guard de Filament y hace que "un representante no puede acceder al panel" se cumpla por construcción, sin lógica de exclusión por rol.

### `plan_days` — implementada (F-005)

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

### `plan_day_activities` — implementada (F-005)

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

### `plan_announcements` — implementada (F-005)

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

### Progreso del plan — implementado (F-005)

Agregado a `plans` (más `paused_from_status` y `status_reason`, no previstos originalmente pero
necesarios para que `ResumePlan` sepa a dónde volver y para registrar motivos de pausa/cancelación/
finalización sin pisar `progress_note`):

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

- Solo un plan puede estar `PUBLICADO`, `EN_CURSO` o `PAUSADO`. **Implementado (F-005)**:
  `PublishPlan` lo verifica con `lockForUpdate()` dentro de una transacción.
- Una publicación nueva debe bloquear o reemplazar transaccionalmente el plan activo anterior.
  **Decisión F-005: bloquear**, no reemplazar — `PublishPlan` lanza `PlanTransitionException` si
  ya hay un plan operativo; el admin debe finalizar/cancelar el anterior primero. Reemplazo
  automático se consideró más propenso a sorpresas (cambiar el estado de un plan que el admin no
  estaba mirando).
- Una orden no se marca pagada por un reporte pendiente.
- Un pago aprobado no puede superar el saldo permitido.
- Una inscripción activa no se duplica para el mismo participante y sesión.
- Cambiar el precio del plan no modifica órdenes existentes.
- El borrado de planes con inscripciones debe estar bloqueado; usar estados. **Implementado
  (F-005)** en `PlanPolicy::delete()`.

## Migración de contenido existente

1. Leer `plans.activities`.
2. Crear días con orden estable.
3. Crear actividades por día.
4. Copiar `plans.staff` a la estructura administrativa elegida.
5. Mantener compatibilidad temporal en `PlanResource`.
6. Retirar JSON solo después de migrar y validar el portal.

**Estado (F-005):** se implementó el punto 5 (compatibilidad temporal) y el contrato del API
(`App\Http\Resources\PlanResource` — el JSON público, no el `PlanResource` de Filament) ya sabe
devolver la forma estructurada cuando existe. Los puntos 1-4 (script de migración one-off de datos
existentes de `plans.activities`/`plans.staff` hacia `plan_days`/`plan_day_activities`) **no se
ejecutaron**: no hay datos de producción reales que migrar todavía (el único plan sembrado usa
`DatabaseSeeder`, que se puede recrear con el schema nuevo directamente). Si se llega a producción
con contenido real en el JSON legado antes de cargarlo en el backoffice, hay que escribir ese
script antes de retirar el JSON.
