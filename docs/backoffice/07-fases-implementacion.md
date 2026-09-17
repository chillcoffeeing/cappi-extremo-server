# Fases de implementación y QA

## Fase 0: preparación

- Confirmar versión compatible de Filament con Laravel 13 y PHP 8.3.
- Instalar paquete y publicar configuración.
- Crear panel, guard y proveedor.
- Crear usuario admin local.
- Definir políticas de acceso.

**Salida:** login administrativo funcional y acceso denegado para representantes.

## Fase 1: seguridad

- Roles y permisos.
- Policies.
- Rate limiting.
- Gestión de sesiones.
- Auditoría base.
- Protección de archivos.

**Salida:** matriz de permisos probada.

## Fase 2: plan activo

- Migraciones de días y actividades.
- `PlanResource`.
- Publicar/pausar/iniciar/finalizar.
- Validación de único plan activo.
- Vista previa.

**Salida:** el admin controla lo que entrega `/api/planes/activo`.

## Fase 3: operación del portal

- Representantes.
- Drafts.
- Participantes.
- Inscripciones.
- Solicitudes de corrección.

**Salida:** expediente familiar completo.

## Fase 4: finanzas

- `PaymentResource`.
- `OrderResource`.
- `ApprovePayment`.
- `RejectPayment`.
- Conciliación de saldos.
- Reportes y exportaciones.

**Salida:** ningún pago aprobado depende de edición manual directa.

## Fase 5: dashboard

- KPIs.
- Alertas.
- Actividad reciente.
- Pagos por antigüedad.
- Cupos y avance del plan.

## Fase 6: endurecimiento

- Pruebas de autorización.
- Pruebas transaccionales.
- Pruebas de doble clic/reintento.
- Pruebas de concurrencia en pagos.
- Backups y restauración.
- Revisión de archivos y logs.

## Criterios de aceptación

- Un representante no puede acceder al panel.
- Soporte no puede aprobar pagos.
- Un pago pendiente no cambia el saldo.
- Aprobar un pago actualiza orden e inscripción.
- Rechazar un pago exige motivo.
- Solo existe un plan operativo activo.
- El porcentaje y día del plan llegan desde API.
- El contenido publicado tiene auditoría.
- Los datos del portal y del admin coinciden.

## Comandos de validación

```powershell
php artisan migrate:fresh --env=testing
php artisan test
php artisan route:list
php artisan config:clear
```

Cuando Filament esté instalado, agregar tests de Feature para login admin, Policies, recursos críticos y Actions financieras.
