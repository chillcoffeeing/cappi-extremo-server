# Visión y alcance

## Propósito

El backoffice permite que operaciones, soporte y finanzas administren el ciclo completo de la familia:

```text
Plan activo -> onboarding público -> participantes -> inscripción -> orden -> pago -> confirmación
```

El admin debe controlar tanto los datos que llegan desde el portal como el contenido que el representante ve en Inicio.

## Alcance MVP

- Login administrativo independiente.
- Roles y permisos.
- Dashboard operativo.
- Representantes y expedientes.
- Onboarding drafts.
- Participantes e inscripciones.
- Plan único activo.
- Días, actividades y avance del plan.
- Órdenes de inscripción y tienda.
- Catálogo de productos de tienda.
- Revisión y aprobación de pagos.
- Métodos de pago.
- Auditoría de acciones sensibles.

## Fuera de alcance inicial

- Llaves o invitaciones de onboarding.
- Catálogo público de planes.
- Galería propia dentro del API: hoy el portal usa Google Drive.
- Facturación fiscal.
- Nómina del staff.
- Automatización avanzada de WhatsApp.
- **Exportaciones operativas** — estaban en el MVP original, pero el usuario pidió quitar
  TODAS las exportaciones que existían tras probar el panel (F-014): no hay ninguna
  disponible hasta que se pida una específica nueva (ver `03-panel-y-recursos.md`).

## Usuarios administrativos

| Rol           | Responsabilidad                                               |
| ------------- | ------------------------------------------------------------- |
| `SUPER_ADMIN` | Configuración completa y gestión de usuarios admin            |
| `OPERACIONES` | Plan, días, actividades, staff, participantes e inscripciones |
| `FINANZAS`    | Pagos, órdenes, saldos y reportes financieros                 |
| `SOPORTE`     | Representantes, onboarding y solicitudes de corrección        |
| `LECTURA`     | Consulta sin modificaciones                                   |

## Principios

- Filament presenta y orquesta; las reglas viven en Actions.
- Ninguna aprobación financiera se resuelve solo en la UI.
- Los estados deben ser explícitos y auditables.
- El dinero aprobado es la única fuente para actualizar saldos.
- El plan activo es único y su publicación debe ser transaccional.

Principio original descartado: "el contenido publicado debe tener vista previa antes de afectar
el portal" — se decidió explícitamente no construir una vista previa visual del plan (F-005, bajo
valor en una consola admin); publicar sigue siendo transaccional y reversible por Actions, pero
sin previsualización visual antes de publicar.
