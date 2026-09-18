# Panel y recursos Filament

## Navegación — implementado (F-006/F-007/F-011/F-013), difiere del plan original

El plan original agrupaba la navegación en secciones (Operación/Plan activo/
Finanzas/Sistema). Eso no se construyó así: Filament v4 la resuelve con una
lista plana ordenada por `$navigationSort` (sin `navigationGroup`), y solo
existen los 8 Resources listados abajo — no hay páginas separadas de "Roles y
permisos", "Auditoría", "Configuración" ni "Reportes" (roles/permisos se
gestionan por seeder, no por UI; auditoría es un widget del dashboard, no una
página propia; no hay reporting agregado, ver más abajo).

Orden real del sidebar (`$navigationSort`, F-011 — Representantes antes que
Participantes fue un pedido explícito del usuario):

1. Representantes (`UserResource`, 10)
2. Participantes (`ParticipantResource`, 20)
3. Onboarding (`OnboardingDraftResource`, 30)
4. Inscripciones (`EnrollmentResource`, 40)
5. Plan (`PlanResource`, 50)
6. Órdenes (`OrderResource`, 60) — incluye Pagos (ver F-013 más abajo, ya no
   es un nav item separado)
7. Métodos de pago (`PaymentMethodResource`, 70)
8. Productos (`ProductResource`, 80)

## Recursos principales

### `UserResource` — implementado (F-006), solo lectura desde F-014

Buscar por nombre, email, teléfono o identificación. Mostrar estado de onboarding, último acceso y contadores de participantes, órdenes y pagos. Usar Relation Managers para el expediente completo.
Sin crear/eliminar: las cuentas nacen del portal. **F-014:** la ficha completa del representante
también pasó a solo lectura, ruta `/admin/users/{id}/details` (no `/edit`). **F-018:** las filas de
los tabs "Inscripciones" y "Órdenes" son clicables (llevan a `/admin/enrollments/{id}/details` y
`/admin/orders/{id}/details`); el tab "Pagos" tiene las mismas columnas y Actions
(aprobar/rechazar/comprobante) que la tabla de pagos de `/admin/orders`.

### `OnboardingDraftResource` — implementado (F-006), solo lectura desde F-014, datos formateados desde F-016

Mostrar representante, paso actual, última actividad, estado, participantes y pago capturado.
Permitir seguimiento (Marcar abandonado, Reactivar seguimiento), y contacto, pero no edición libre
del JSON. **F-014:** ruta `/admin/onboarding-drafts/{id}/details` (no `/edit`); las 3 Actions de
estado se conservan intactas dentro de la página de solo lectura. **F-016:** el bloque "Datos
capturados" (antes JSON crudo con `json_encode`) ahora se renderiza como una vista legible por
paso del wizard (cuenta, participantes, pago, confirmación...).

### `ParticipantResource` — implementado (F-006), luego endurecido (F-010/F-014/F-015)

Mostrar datos básicos, ficha completa, representante, inscripción (tab "Inscripción", F-015) y
bloques de salud/contactos/autorizaciones formateados (no JSON crudo). Acciones: solicitar
corrección y marcar revisión.
Sin crear/eliminar: las fichas nacen del wizard del portal. **F-014:** la ficha completa —
incluido el tab "Datos básicos" — es de **solo lectura**; el admin ya no edita ningún campo del
participante directamente, solo puede solicitar una corrección (que el representante resuelve
desde el portal, F-010). Ruta `/admin/participants/{id}/details`, no `/edit`. **F-014:** se quitó
"Exportar ficha" (como todas las demás exportaciones del admin, ver `OrderResource` abajo).

### `EnrollmentResource` — implementado (F-006), solo lectura desde F-014

Mostrar participante, plan, sesión, modalidad, total, descuento, estado. "Orden y pagos
relacionados" nunca se mostró: `enrollments` no tiene relación explícita con `orders` (ver
`02-datos-y-migraciones.md`, gap documentado desde antes del backoffice). Sin página de creación:
nace de `RegisterParticipantInscription`, no de un formulario en blanco. **F-014:** pasó a
solo lectura, ruta `/admin/enrollments/{id}/details`.

### `PlanResource`

Formularios por tabs: general, precio/cupos, días, staff, avisos, mini market y estado/avance.
Acciones: publicar, iniciar, pausar, reanudar, finalizar, cancelar y actualizar avance
(`UpdatePlanProgress`). La vista previa visual del plan planteada originalmente **no se
construyó** (bajo valor en una consola admin, decisión F-005). A diferencia de Participants/
Users/Enrollments/OnboardingDrafts, este recurso **no** pasó a solo lectura en F-014: sigue
siendo editable directamente (el estado operativo sí solo cambia por las Actions de arriba, no
escribiendo el campo). **F-017:** el tab "Precio y cupos" agrega la configuración del descuento
por hermanos, ahora por plan (antes era una fila global en `platform_settings`, deprecada — ver
`02-datos-y-migraciones.md`).

### `PaymentResource` — retirado como recurso independiente en F-013

Existió como recurso de navegación propio entre F-007 y F-013. **F-013** lo fusionó dentro de
`OrderResource`: la tabla de pagos (con las mismas Actions — aprobar, rechazar, ver comprobante)
ahora vive como un widget (`PaymentsWidget`) debajo de la tabla de órdenes en `/admin/orders`, sin
navegación ni URL propia. Sigue sin edición manual de campos, solo Actions. "Solicitar
corrección" nunca fue una acción separada: rechazar con motivo ya permite un nuevo reporte.
**F-018:** las mismas 8 columnas y Actions de `PaymentsWidget` se comparten (vía el trait
`HasPaymentsTableColumnsAndActions`) con las otras 2 tablas de pagos del admin — la del expediente
del representante (`UserResource`, tab "Pagos") y la de una orden específica (`OrderResource`,
tab "Pagos") — para que "Pagos" se vea y se gestione igual sin importar desde dónde se entre.
`PaymentsWidget` además ocupa el ancho completo del contenedor (antes quedaba a la mitad, ver
Dashboard más abajo para la causa raíz).

### `OrderResource` — implementado (F-007), fusionado con Pagos y endurecido (F-013)

Separa órdenes de inscripción y tienda. Mostrar total, abonado, saldo, estado, representante.
**F-013:** `/admin/orders` muestra primero la tabla de órdenes y, debajo, la de pagos
(`PaymentsWidget`, ver `PaymentResource` arriba); el detalle de una orden
(`/admin/orders/{id}/details`, no `/edit`) es de solo lectura, con la tabla completa de ítems
(sin truncar) y sin "Guardar cambios"/"Cancelar". **F-014:** se quitó "Exportar" — de hecho se
quitaron TODAS las exportaciones que existían en el admin (participantes, órdenes), a pedido
explícito del usuario; no hay ninguna exportación disponible hasta que se pida una nueva
específica.

### `PaymentMethodResource` — implementado (F-007), salvo "ámbitos de uso"

Administrar nombre, código, instrucciones, datos bancarios, activo y ámbitos de uso.
"Ámbitos de uso" no tiene columna en el schema (`payment_methods` no la contempla) y no se
agregó — no está claro qué significa concretamente sin más contexto de negocio.

### `ProductResource` — implementado (F-007)

Administrar el catálogo de tienda: nombre, categoría, precio, precio anterior, variantes, imágenes y disponibilidad (`in_stock`). Distinto del "Mini market" del plan activo, que es un bloque JSON propio del evento.

## Dashboard — implementado (F-008), recortado a pedido del usuario (F-012)

Dos widgets reales, no la lista original de 7 "recomendados":

- **`KpiOverview`** (stats, primero): pagos pendientes + monto, representantes, fichas de
  participantes incompletas, cupos/avance del plan operativo, y "Pedidos de Tienda" (conteo de
  órdenes `is_registration=false`, F-012). Cada stat está gateada por el permiso de dominio
  correspondiente.
- **`ActividadReciente`** (tabla, último — `$sort` alto a propósito, F-012): lee `activity_log`,
  gateada por `audit.view`. Es el único punto del panel donde se ve auditoría; no existe una
  página "Auditoría" separada.

**F-018:** `ActividadReciente` (y `PaymentsWidget` en `/admin/orders`, ver `PaymentResource` más
arriba) pasaron a ocupar el ancho completo del contenedor. Causa raíz: `Filament\Widgets\Widget`
declara `columnSpan = 1` por defecto y el Dashboard/`ListOrders` usan un grid de 2 columnas, así
que un widget-tabla solo (sin otro al lado) quedaba a la mitad del ancho. `KpiOverview` nunca tuvo
este problema: hereda `columnSpan = 'full'` de `StatsOverviewWidget`.

**F-012 quitó explícitamente**, a pedido del usuario tras probar el panel: el widget "Pagos
pendientes por antigüedad" (buckets 0-2/3-7/+7 días, existía como `PagosPorAntiguedad` entre F-008
y F-012 — la función de alerta de pagos viejos que cumplía queda cubierta por la vista combinada
de Pagos+Órdenes de F-013), el widget de bienvenida de Filament (`AccountWidget`) y el widget de
info/docs de Filament (`FilamentInfoWidget`).

No implementados (fuera de la lista original, nunca se construyeron): "Onboardings incompletos" y
"Inscripciones recientes" como widgets propios (cubiertos parcialmente por los stats de
`KpiOverview`), "Actividades del día actual" (redundante con el detalle de días del plan activo).
