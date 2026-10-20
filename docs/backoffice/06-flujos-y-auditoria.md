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

**Regla vigente (F-053, decisión del usuario 2026-10-19):** se conserva la evidencia financiera y
todo lo demás se borra físicamente. Reemplaza la regla anterior ("soft delete o anonimización").

- Solo **SUPER_ADMIN** borra registros (permiso `records.delete`, que no tiene ningún otro rol).
- **Se conservan siempre:** órdenes, pagos, comprobantes (disco `local`) y `activity_log`. Órdenes y
  pagos **no se borran nunca** (`OrderPolicy`/`PaymentPolicy` no tienen `delete()`).
- **Borrar un representante** (`App\Actions\Admin\DeleteRepresentative`): sus órdenes y pagos
  quedan con `user_uuid = NULL` y guardan `representative_snapshot` (JSON `name`, `lastName`,
  `email`, `identification`, `deletedAt`); cada pago sigue unido a su orden (`order_uuid` intacto).
  Se borran participantes (con inscripciones y solicitudes de corrección), borradores de
  onboarding, refresh tokens, portal auth tokens, tokens Sanctum, la foto de perfil y las fotos de
  participantes (estas, después del commit). No cambia ningún estado: un pago
  `PENDIENTE_VERIFICACION` se aprueba o rechaza después y una orden con saldo se cancela a mano
  (mientras tanto sigue contando en los KPIs). `impact()` da los conteos para el modal de
  confirmación.
- **Borrar un participante** (`DeleteParticipant`): inscripción, correcciones y foto; luego
  `RecalculateInscriptionOrders` re-precia las órdenes de inscripción del representante (sin
  cambiar cantidades: si la familia baja del mínimo, la orden pierde el descuento hermanos).
- **Borrar una inscripción** (`DeleteEnrollment`): solo la inscripción.
- Borradores de onboarding y solicitudes de corrección: borrado directo, también con
  `records.delete`.
- **Backoffice (F-054):** los botones "Eliminar" llaman a estas Actions (nunca al `delete()` por
  defecto de Filament) y solo los ve SUPER_ADMIN. Las órdenes y pagos sin representante se
  muestran como "Representante eliminado" con el snapshot, y hay filtro "Sin representante".
- No se agrega log del borrado. El borrado es irreversible: el respaldo es el `mysqldump` previo
  al despliegue (`docs/update-cpanel.md`).
- Planes: sin cambios, un plan con inscripciones sigue sin poder borrarse (`PlanPolicy::delete`).
