# Flujos y auditoría

## Expediente del representante

Desde un representante se debe navegar a:

```text
Representante
├── Onboarding drafts
├── Participantes
├── Inscripciones
├── Órdenes
├── Pagos
└── Actividad
```

## Corrección de datos

Soporte puede solicitar correcciones de ficha. La solicitud debe registrar:

- sección afectada
- mensaje
- actor
- fecha
- estado
- resolución

El representante no debe perder datos al recibir una solicitud.

## Onboarding

Estados operativos:

```text
INCOMPLETO
LISTO_PARA_CONFIRMAR
COMPLETADO
ABANDONADO
```

Acciones administrativas:

- Ver último paso.
- Ver última actividad.
- Contactar representante.
- Reactivar seguimiento.
- Marcar abandonado.
- Ver datos capturados.

No existe llave ni invitación; el acceso siempre comienza en `/onboarding`.

## Auditoría mínima

Auditar:

- Login y logout administrativo.
- Creación, edición y publicación de planes.
- Cambios de estado.
- Cambios de precio o cupo.
- Aprobación y rechazo de pagos.
- Cancelación de órdenes.
- Edición de fichas.
- Cambios de permisos.
- Exportaciones.

## Formato de evento

```text
actor_id
actor_role
action
subject_type
subject_id
old_values
new_values
reason
ip
user_agent
created_at
```

## Retención

Definir una política de retención para auditoría y comprobantes antes de producción. Nunca eliminar evidencia financiera por un borrado de usuario; aplicar soft delete o anonimización donde corresponda.
