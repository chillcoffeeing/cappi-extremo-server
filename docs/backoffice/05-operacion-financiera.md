# Operación financiera

## Flujo

```text
Pago reportado
  -> PENDIENTE_VERIFICACION
  -> revisión administrativa
     -> APROBADO
     -> RECHAZADO con motivo
```

## Aprobar pago

La Action `ApprovePayment` debe ejecutarse dentro de una transacción:

1. Bloquear el pago y la orden.
2. Verificar que siga pendiente.
3. Verificar que el monto no exceda el saldo.
4. Cambiar el pago a `APROBADO`.
5. Incrementar `orders.paid`.
6. Cambiar orden a `PAGADA` o mantener `PENDIENTE_PAGO`.
7. Actualizar inscripción relacionada.
8. Guardar revisor y fecha.
9. Registrar auditoría.
10. Notificar al representante.

**Estado (F-007):** implementados los pasos 1-6, 8 y 9 (auditoría vía `spatie/laravel-activitylog`
en el modelo `Payment`). **No implementados:**
- Paso 7 ("actualizar inscripción relacionada") — `enrollments` no tiene relación explícita con
  `orders` (gap documentado desde antes de esta feature en `02-datos-y-migraciones.md`). Adivinar
  qué inscripción corresponde por heurística (mismo usuario) podría marcar como pagada una
  inscripción equivocada si el representante tiene más de una orden de inscripción — es peor que
  no tocarla.
- Paso 10 ("notificar al representante") — no existe ningún canal de notificaciones en la app
  (sin tabla `notifications`, sin mailer transaccional configurado). Es un proyecto aparte, no un
  detalle de `ApprovePayment`.

## Rechazar pago

La Action `RejectPayment` debe:

- Requerir motivo.
- Cambiar estado a `RECHAZADO`.
- No tocar `orders.paid`.
- Mantener la orden pendiente.
- Registrar revisor y auditoría.
- Permitir un nuevo reporte.

**Estado (F-007):** implementado completo. No hay una Action separada de "solicitar corrección"
para pagos — rechazar con motivo ya cumple esa función ("permitir un nuevo reporte" es
exactamente lo que hace un rechazo).

## Órdenes

Una orden de inscripción se crea al completar onboarding. Una orden de tienda se crea en checkout. Ambas usan el mismo flujo de pagos, pero se diferencian mediante `is_registration`.

## Reportes

Filtros por fecha, plan, método, estado, representante y tipo de orden. Exportaciones deben respetar permisos y excluir comprobantes binarios salvo acción explícita.

**Estado (F-007):** no implementado como reporte agregado. **Actualización F-013/F-014:**
la acción "Exportar" por registro individual que tenía `OrderResource` se quitó (igual que
todas las demás exportaciones del admin); no hay filtros combinados ni exportación masiva
ni exportación por registro. Es trabajo de reporting aparte, a reintroducir solo cuando se
pida una exportación específica nueva.

## Reglas

- Los pagos pendientes no incrementan saldo abonado.
- Un pago aprobado no puede duplicarse.
- Una orden pagada no acepta abonos.
- Reintentos deben ser idempotentes.
- Las correcciones manuales de saldo requieren permiso financiero y motivo.

## Coordinación del primer pago por WhatsApp (F-023)

Un `PaymentMethod` puede ser `DIRECTO` (se reporta en la app, comportamiento por defecto) o
`COORDINADO_REMOTO` (el primer pago de la inscripción se coordina fuera de la app, ej. WhatsApp,
en vez de reportarse con referencia y comprobante). `type` describe ese comportamiento, no el
proveedor: Zelle, Binance, Efectivo o cualquier método nuevo que el admin cree pueden ser
cualquiera de los dos, editable en cualquier momento desde `/admin/payment-methods` sin tocar
código.

- Al completar el onboarding con un método `COORDINADO_REMOTO`, `CompleteOnboarding` igual crea el
  `Payment` de la orden de inscripción — `PENDIENTE_VERIFICACION`, sin `reference` ni comprobante.
  Es la fila que registra que el primer pago quedó pendiente de coordinar; no hay datos de
  transacción reales en ningún lado (admin/API/portal) hasta que soporte lo gestione y un admin lo
  apruebe/rechace por los canales normales de `ApprovePayment`/`RejectPayment`.
- El método `COORDINADO_REMOTO` configura 4 textos (`data.whatsapp.mensajeOnboarding`,
  `mensajeDashboard`, `linkTexto`, `link`) desde `PaymentMethodForm`. Si `link` queda vacío, se
  resuelve en `PaymentController::methods()` con el WhatsApp operativo del plan activo
  (`Plan.whatsapp`).
- `GET /pagos/balance` expone `primerPagoCoordinado` (ver `api/docs/api-contract.md`) leyendo el
  método del `Payment` de la PRIMERA orden de inscripción de la familia (`orders.is_registration =
  true`, la más antigua): si es `COORDINADO_REMOTO` y sigue `PENDIENTE_VERIFICACION`, dispara el
  banner rojo claro de `/portal` y el de la pantalla de éxito del onboarding. Aprobar ese pago (o
  reemplazarlo por uno con datos reales) apaga el banner.

## Descuento por hermanos (F-017)

Configurable **por plan**, no como regla global: cada `Plan` tiene sus propias
columnas `sibling_discount_enabled`/`sibling_discount_min_participants`/
`sibling_discount_amount`, editables desde el tab "Precio y cupos" de
`PlanResource`. El pricing de una inscripción (`CalculateInscriptionTotal`) y
el recálculo retroactivo al crecer la familia (`RecalculateInscriptionOrders`)
leen el descuento del plan operativo vigente
(`Plan::operative()->latest('starts_at')->first()`), nunca de una fila global
compartida. La antigua fila única `platform_settings` queda deprecada (ver
`02-datos-y-migraciones.md`).
