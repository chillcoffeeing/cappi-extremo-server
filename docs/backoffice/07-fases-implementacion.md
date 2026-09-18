# Fases de implementación y QA

## Fase 0: preparación

- Confirmar versión compatible de Filament con Laravel 13 y PHP 8.3.
- Instalar paquete y publicar configuración.
- Crear tabla `admin_users`, guard administrativo separado y proveedor.
- Crear usuario admin local.

**Salida:** login administrativo funcional y acceso denegado para representantes.

**Estado:** implementada (F-003). Ver `progress/impl_F-003.md`.

## Fase 1: seguridad

- Roles y permisos.
- Policies.
- Rate limiting.
- Gestión de sesiones.
- Auditoría base.
- Protección de archivos.

**Salida:** matriz de permisos probada.

**Estado:** implementada (F-004). Ver `progress/impl_F-004.md`. Nota: la
auditoría y las Policies de negocio (planes, pagos, etc.) solo tienen sentido
pleno una vez existan las Actions/Resources correspondientes (Fase 2+/4); en
Fase 1 se deja la infraestructura (roles, permisos, Policies base, guard,
`activity_log`) y se prueba con datos de dominio ya existentes.

## Fase 2: plan activo

- Migraciones de días y actividades.
- `PlanResource`.
- Publicar/pausar/iniciar/finalizar.
- Validación de único plan activo.
- Vista previa.

**Salida:** el admin controla lo que entrega `/api/planes/activo`.

**Estado:** implementada (F-005) salvo la vista previa visual (Hero/Avance/Calendario/etc. con
fidelidad de portal), que se dejó fuera por costo/beneficio en una UI administrativa — se puede
verificar el resultado real leyendo `GET /api/planes/activo` directamente. Ver `progress/impl_F-005.md`.
La normalización de `plans.status` (`ACTIVO` → `PUBLICADO`, ya no es un valor libre sino parte de
la máquina de estados) y el contrato ampliado de `/api/planes/activo` (`dias` estructurado,
`avance`, `avisos`) se hicieron en la misma feature porque el estado operativo del plan y su
contenido son la misma pieza.

## Fase 3: operación del portal

- Representantes.
- Drafts.
- Participantes.
- Inscripciones.
- Solicitudes de corrección.

**Salida:** expediente familiar completo.

**Estado:** implementada (F-006). Ver `progress/impl_F-006.md`. `UserResource` (representantes)
y `ParticipantResource` no tienen `create()`/`delete()` en sus Policies a propósito: esas cuentas
y fichas nacen del portal, no de un formulario admin en blanco. `EnrollmentResource` tampoco tiene
página de creación por el mismo motivo (nace de `RegisterParticipantInscription`), aunque su
Policy sí conserva `create()`/`delete()` para uso futuro (comandos, etc.). "Órdenes" y "pagos" del
árbol de expediente del representante ya estaban cubiertos por `PaymentsRelationManager`/
`OrdersRelationManager`; el nodo "Actividad" del árbol de 06-flujos-y-auditoria.md no se
implementó — instrumentar la actividad del propio representante con `spatie/laravel-activitylog`
es trabajo nuevo, no algo que ya existiera para reutilizar.

## Fase 4: finanzas

- `PaymentResource`.
- `OrderResource`.
- `ProductResource` (catálogo de tienda).
- `ApprovePayment`.
- `RejectPayment`.
- Conciliación de saldos.
- Reportes y exportaciones.

**Salida:** ningún pago aprobado depende de edición manual directa.

**Estado:** implementada (F-007), salvo reportes agregados. Ver `progress/impl_F-007.md`.
`PaymentResource`/`OrderResource` sin páginas de creación (nacen del portal/checkout, no de un
formulario admin); `ApprovePayment`/`RejectPayment`/`CancelOrder` son Actions transaccionales con
`lockForUpdate()`, expuestas como Actions de tabla/página, nunca como edición directa de campos.
`PaymentMethodResource` y `ProductResource` sí tienen CRUD completo (esos catálogos los administra
directamente el admin). Dos pasos documentados de `ApprovePayment` no se implementaron — ver nota
en `05-operacion-financiera.md`. "Reportes y exportaciones" quedó en exportación por registro
(JSON), sin filtros agregados por fecha/plan/método/representante ni exportación masiva — eso es
una feature de reporting aparte, no una consecuencia natural de construir las Actions financieras.

## Fase 5: dashboard

- KPIs.
- Alertas.
- Actividad reciente.
- Pagos por antigüedad.
- Cupos y avance del plan.

**Estado:** implementada (F-008). Ver `progress/impl_F-008.md`. 3 widgets en el Dashboard nativo
de Filament: `KpiOverview` (pagos pendientes + monto, pagos con +3 días de espera como alerta,
representantes, fichas incompletas, cupos y avance del plan operativo — cada stat se muestra solo
si el admin tiene el permiso de vista del dominio), `PagosPorAntiguedad` (buckets 0-2/3-7/+7 días),
`ActividadReciente` (tabla sobre `spatie/laravel-activitylog`, gateada por `audit.view`). No hay
"Alertas" como sección separada: la antigüedad de pagos y las fichas incompletas ya cumplen esa
función coloreadas en rojo/amarillo dentro de `KpiOverview`, en vez de duplicar la misma
información en dos widgets distintos.

## Fase 6: endurecimiento

- Pruebas de autorización.
- Pruebas transaccionales.
- Pruebas de doble clic/reintento.
- Pruebas de concurrencia en pagos.
- Backups y restauración.
- Revisión de archivos y logs.

**Estado:** implementada (F-009), salvo automatización de backups. Ver `progress/impl_F-009.md`.
`PermissionMatrixTest` fija en código la matriz de 5 roles × 22 permisos documentada (no solo el
seeder que la genera). `DoubleSubmitRetryTest` encontró y corrigió un bug real:
`ResolveParticipantCorrection` no tenía guard de estado — un doble clic en "Resolver" pisaba la
primera resolución en vez de rechazarse, a diferencia de todas las demás Actions de la Fase 4 que
sí lo hacían. `PaymentConcurrencyTest` documenta honestamente que PHPUnit no puede reproducir dos
transacciones reales compitiendo (SQLite bloquea el archivo completo, no por fila) y en su lugar
prueba lo que sí importa: que `ApprovePayment` relee el estado fresco dentro del `lockForUpdate()`
en vez de confiar en el objeto que le pasó el llamador. "Backups y restauración" quedó como
procedimiento documentado en `08-despliegue-runbook.md`, no automatizado — depende de un hosting
que todavía no está provisionado. "Revisión de archivos y logs" no encontró gaps reales: los
`.gitignore` anidados de Laravel ya protegen `storage/`, y no hay secretos en texto plano en los
logs.

## Criterios de aceptación

- Un representante no puede acceder al panel. ✅ probado (`AdminPanelAccessTest`).
- Soporte no puede aprobar pagos. ✅ probado (`PermissionMatrixTest`: SOPORTE no tiene `payments.approve`).
- Un pago pendiente no cambia el saldo. ✅ (`GET /pagos/balance` solo suma `status=APROBADO`, F-002).
- Aprobar un pago actualiza orden e inscripción. ⚠️ **parcial**: actualiza la orden (probado); NO
  actualiza la inscripción — falta la relación `enrollments`↔`orders` (ver `05-operacion-financiera.md`).
- Rechazar un pago exige motivo. ✅ probado.
- Solo existe un plan operativo activo. ✅ probado (`PlanLifecycleTest`, `DoubleSubmitRetryTest`).
- El porcentaje y día del plan llegan desde API. ✅ probado (`PlanActiveContentTest`).
- El contenido publicado tiene auditoría. ✅ (`Plan` usa `LogsActivity` sobre `status`/precio/cupos).
- Los datos del portal y del admin coinciden. ✅ por construcción (mismos modelos Eloquent/tablas
  en ambos lados; no hay una copia de datos separada para el admin).

## Comandos de validación

```powershell
php artisan migrate:fresh --env=testing
php artisan test
php artisan route:list
php artisan config:clear
```

Cuando Filament esté instalado, agregar tests de Feature para login admin, Policies, recursos críticos y Actions financieras.
