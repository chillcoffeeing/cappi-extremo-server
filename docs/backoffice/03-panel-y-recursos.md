# Panel y recursos Filament

## Navegación

### Operación

- Dashboard.
- Representantes.
- Onboarding.
- Participantes.
- Inscripciones.

### Plan activo

- Plan.
- Días.
- Actividades.
- Staff.
- Avisos.
- Mini market.

### Finanzas

- Pagos pendientes.
- Historial de pagos.
- Órdenes.
- Métodos de pago.
- Reportes.

### Sistema

- Usuarios administrativos.
- Roles y permisos.
- Auditoría.
- Configuración.

## Recursos principales

### `UserResource`

Buscar por nombre, email, teléfono o identificación. Mostrar estado de onboarding, último acceso y contadores de participantes, órdenes y pagos. Usar Relation Managers para el expediente completo.

### `OnboardingDraftResource`

Mostrar representante, paso actual, última actividad, estado, participantes y pago capturado. Permitir seguimiento, reactivación y contacto, pero no edición libre del JSON.

### `ParticipantResource`

Mostrar datos básicos, ficha completa, representante, inscripción y bloques de salud/contactos/autorizaciones. Acciones: solicitar corrección, marcar revisión y exportar ficha.

### `EnrollmentResource`

Mostrar participante, plan, sesión, modalidad, total, descuento, estado, orden y pagos relacionados.

### `PlanResource`

Formularios por tabs: general, precio/cupos, días, staff, avisos, mini market y estado/avance. Acciones: publicar, pausar, iniciar, finalizar, cancelar y previsualizar.

### `PaymentResource`

Bandeja priorizada de pagos pendientes. Mostrar comprobante, referencia, monto, método, representante, orden y fecha. Acciones: aprobar, rechazar y solicitar corrección.

### `OrderResource`

Separar órdenes de inscripción y tienda. Mostrar total, abonado, saldo, estado, representante y pagos. Acciones: ver detalle, cancelar y exportar.

### `PaymentMethodResource`

Administrar nombre, código, instrucciones, datos bancarios, activo y ámbitos de uso.

## Dashboard

Widgets recomendados:

- Pagos pendientes por antigüedad.
- Onboardings incompletos.
- Inscripciones recientes.
- Cupos por plan.
- Saldo pendiente.
- Últimas acciones administrativas.
- Actividades del día actual.
