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

## Rechazar pago

La Action `RejectPayment` debe:

- Requerir motivo.
- Cambiar estado a `RECHAZADO`.
- No tocar `orders.paid`.
- Mantener la orden pendiente.
- Registrar revisor y auditoría.
- Permitir un nuevo reporte.

## Órdenes

Una orden de inscripción se crea al completar onboarding. Una orden de tienda se crea en checkout. Ambas usan el mismo flujo de pagos, pero se diferencian mediante `is_registration`.

## Reportes

Filtros por fecha, plan, método, estado, representante y tipo de orden. Exportaciones deben respetar permisos y excluir comprobantes binarios salvo acción explícita.

## Reglas

- Los pagos pendientes no incrementan saldo abonado.
- Un pago aprobado no puede duplicarse.
- Una orden pagada no acepta abonos.
- Reintentos deben ser idempotentes.
- Las correcciones manuales de saldo requieren permiso financiero y motivo.
