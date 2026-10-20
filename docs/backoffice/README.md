# Backoffice administrativo

Documentación de construcción y operación del backoffice Filament para Cappi Xtremo.

## Objetivo

El backoffice es la superficie operativa para controlar el contenido del plan activo, representantes, participantes, inscripciones, órdenes y pagos. El portal de representantes consume la información publicada por este panel, pero las reglas de negocio permanecen en Actions y servicios de Laravel.

El sistema usa un único plan operativo a la vez. El onboarding es público mediante `/onboarding`; no existen llaves ni invitaciones de registro.

## Documentos

1. [Visión y alcance](00-vision-y-alcance.md)
2. [Arquitectura y seguridad](01-arquitectura-y-seguridad.md)
3. [Datos y migraciones](02-datos-y-migraciones.md)
4. [Panel y recursos Filament](03-panel-y-recursos.md)
5. [Plan activo y contenido](04-plan-activo-y-contenido.md)
6. [Operación financiera](05-operacion-financiera.md)
7. [Flujos y auditoría](06-flujos-y-auditoria.md)
8. [Fases de implementación y QA](07-fases-implementacion.md) (registro histórico F-003..F-009)

Despliegue, respaldos y restauración: [../../../docs/despliegue.md](../../../docs/despliegue.md).

## Orden recomendado

1. Seguridad, roles y permisos.
2. Modelo de contenido del plan.
3. Pagos y órdenes.
4. Expedientes de representantes y participantes.
5. Dashboard operativo.
6. Auditoría, reportes y despliegue.

## Estado

Implementado: roles y permisos (5 roles, Policies, auditoría), plan activo con días, actividades,
avisos y ciclo de vida, expedientes de representantes, onboarding, participantes (solo lectura +
solicitudes de corrección), inscripciones, órdenes con pagos (aprobar, rechazar, comprobantes),
métodos de pago, productos, dashboard y borrado controlado con doble confirmación. El detalle
vigente de cada recurso está en `03-panel-y-recursos.md`. Pendiente: gestión de usuarios admin
desde el panel (F-055), reportes/exportaciones, vista previa del plan.
