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

**Estado (F-006):** implementado como Relation Managers de solo lectura en `UserResource`
(Onboarding drafts, Participantes, Inscripciones, Órdenes, Pagos — la edición de cada ficha vive
en su propio Resource). "Actividad" no se implementó: requeriría instrumentar con
`spatie/laravel-activitylog` las acciones del representante en el portal, que hoy no registran
nada — es trabajo nuevo, no una vista sobre datos existentes.

## Corrección de datos

Soporte puede solicitar correcciones de ficha. La solicitud debe registrar:

- sección afectada
- mensaje
- actor
- fecha
- estado
- resolución

El representante no debe perder datos al recibir una solicitud.

**Estado (F-006):** implementado. Tabla `participant_correction_requests` +
`RequestParticipantCorrection`/`ResolveParticipantCorrection` (Actions) + relation manager en
`ParticipantResource`. La Action de solicitud nunca toca los datos del participante, solo crea el
registro de la solicitud.

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

**Estado (F-006):** implementado en `OnboardingDraftResource`/`EditOnboardingDraft`. "Marcar
abandonado" y "Reactivar seguimiento" son Actions nuevas (ningún flujo del portal ponía
`ABANDONADO` antes); "Contactar representante" es un enlace `mailto:`; "Ver último paso"/"Ver
datos capturados" se muestran de solo lectura (sin edición libre del JSON).

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

**Estado:** infraestructura lista (`spatie/laravel-activitylog`, F-004). Login/logout
administrativo ya se registra (log name `admin.auth`). El resto de la lista se
audita a medida que existan sus Actions (Fase 2 en adelante).

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
