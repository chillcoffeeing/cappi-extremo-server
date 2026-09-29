# Contrato API

El contrato completo y canónico está en [portal/docs/business-logic/12-api-contrato.md](../../portal/docs/business-logic/12-api-contrato.md). Este resumen sirve como guía local al implementar rutas y debe mantenerse sincronizado.

## Convenciones

- Base local: `/api`.
- Autenticación: `Authorization: Bearer <access-token>`.
- JSON público siempre en camelCase.
- Fechas como `YYYY-MM-DD` salvo que el contrato indique fecha y hora.
- Dinero como número JSON respaldado por `decimal(16,4)` en MySQL; no usar `float` para cálculos.
- `200` para lecturas y actualizaciones, `201` para creaciones y `204` para eliminaciones o acciones sin cuerpo.
- Errores: `{ "message": "..." }`, con `422` para validación, `401` para sesión inválida y `404` para recursos inexistentes. La validación por campo de un paso del wizard devuelve además `errors: { "data.<campo>": [ "..."] }` (shape Laravel, claves prefijadas con `data.`).
- CORS permite el portal y `localhost:5173` en desarrollo. No usar credenciales de cookie.

## Rutas previstas

## Estado de implementación

Implementado y probado:

- `POST /api/auth/register`, `POST /api/auth/login` y `POST /api/auth/logout`.
  Registro y login devuelven el shape camelCase del portal; logout revoca los
  tokens Sanctum persistidos.
- `GET /api/participantes` (`participants.index`), protegido por `auth:sanctum`.
  Devuelve únicamente los participantes del usuario autenticado con Resource
  camelCase y `datosCompletos`.
- Participantes: detalle, PATCH profundo por sección y wizard individual
  (`GET /wizard`, `POST /wizard/step`, `POST /wizard/complete`).
- `GET /participantes` y `GET /participantes/{id}` incluyen `solicitudesCorreccion`
  (backoffice F-010): lista de `{ id, seccion, mensaje, estado, resolucion, fecha }` creada desde
  `/admin/participants/{id}`. `seccion` usa las mismas 5 claves que `SectionKey` en el portal
  (`datosBasicos`, `salud`, `contactosEmergencia`, `seguroMedico`, `documentos`).
  Ver shape completo en `portal/docs/business-logic/12-api-contrato.md`.
- Onboarding general: draft persistente, pasos idempotentes y completion.
- Cuenta, plan activo, pagos, órdenes y catálogo de productos.

Pendiente:

- Auth avanzada pendiente: recuperación de contraseña y verificación de email.
- Recuperación/verificación de Auth y seeders de plan, métodos de pago y catálogo.
- Cuenta, plan, inscripciones, pagos, órdenes y catálogo.

La migración `participants` y el modelo `Participant` ya existen. No se deben
crear rutas de los dominios pendientes hasta tener su Action, Request, Resource
y test Feature correspondientes.

El refresh rotativo ya está implementado: los tokens opacos se almacenan
hasheados, expiran en 24 horas o 30 días según `remember`, se revocan al usarlo
y el replay del token anterior devuelve `401`.

### Públicas

- `POST /auth/login`
- `POST /auth/register`
- `POST /auth/refresh`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `POST /auth/verify-email`

### Requieren `auth:sanctum`

- `POST /auth/logout`
- `GET|PUT /representante`
- `GET|PATCH /participantes`
- `GET /inscripciones/{participante}/detalle`
- `GET /pagos/balance`
- `GET|POST /pagos`
- `POST /pagos/{payment}/completar-coordinado` — completa el pago coordinado pendiente del
  onboarding (F-051; ver notas más abajo)
- `GET /pagos/{payment}/comprobante` — descarga el comprobante (disco privado `local`); solo el
  representante dueño del pago, `404` para cualquier otro usuario (backoffice F-004)
- `GET|POST /ordenes`
- `POST /ordenes/{id}/enlazar-pago`
- `GET /participantes/{id}/wizard`, `POST /participantes/{id}/wizard/step` y `POST /participantes/{id}/wizard/complete`
- `GET|POST /onboarding/{draftId}` y `POST /onboarding/{draftId}/complete`
- `GET /config/metodos-pago`
- `GET /tienda/productos`

### Públicos: plan activo y reglas de precio

- `GET /planes/activo`
- `GET /config/plan-price`

`GET /planes/activo` devuelve el plan actual sin precio. "Activo" = `status` en
`PUBLICADO` o `EN_CURSO` (ver `Plan::scopeOperative()`, Fase 2 del backoffice;
`ACTIVO` era el valor libre previo, ya migrado). Su shape es:

```json
{
    "data": {
        "id": "uuid",
        "nombre": "PLAN VACACIONAL - EDICIÓN NAVIDAD",
        "sede": "Finca la Esperanza - Guanare",
        "temporada": "Edición Navidad",
        "estado": "PUBLICADO",
        "portadaUrl": null,
        "fechaInicio": "2026-12-14",
        "fechaFin": "2026-12-18",
        "fechaTexto": "Diciembre 14 al 18",
        "duracionTexto": "4 días, llegada y salida diaria",
        "horario": "8AM - 5 PM",
        "cupoTotal": 100,
        "cuposDisponible": 100,
        "edadMin": 4,
        "edadMax": 15,
        "descripcion": "",
        "dias": [{ "date": "2026-12-14", "numeroDia": 1, "descripcion": "" }],
        "avance": { "modo": "AUTO", "porcentaje": 0, "etiqueta": null, "diaActual": null, "totalDias": 0 },
        "avisos": []
    }
}
```

`dias`, `avance` y `avisos` son nuevos (F-005, backoffice Fase 2). El portal
**no los consume todavía** — `12-api-contrato.md` lo marca como pendiente.
Mientras el admin no cargue `plan_days` para un plan, `dias` sigue devolviendo
el JSON legado de `plans.days` (compatibilidad temporal, ver
`api/docs/backoffice/02-datos-y-migraciones.md`); en cuanto existan filas en
`plan_days`, `dias` pasa a la forma estructurada:

```json
"dias": [
    {
        "numeroDia": 1,
        "fecha": "2026-12-14",
        "titulo": "Llegada",
        "descripcion": "",
        "ubicacion": null,
        "estado": "PLANIFICADO",
        "actividades": [
            { "titulo": "Piscina", "descripcion": "", "inicio": "10:00", "fin": "11:00", "ubicacion": null, "estado": "PLANIFICADA" }
        ]
    }
]
```

`avisos` solo incluye los que están `is_visible=true` y dentro de su ventana
`starts_at`/`ends_at` (o sin ventana definida).

Los registros de dominio exponen un UUID público. Las relaciones canónicas usan
únicamente UUID (`user_uuid`, `participant_uuid`, `plan_uuid` y `order_uuid`).
Las columnas enteras antiguas no forman parte del esquema final; la migración las
transforma a relaciones UUID y luego las elimina.

La migración `2026_09_17_150000_remove_legacy_identifiers` elimina esas columnas
de la base existente: `user_id`, `participant_id`, `plan_id`, `order_id`,
`order_code`, `draft_id`, `session_id`, `access_token_id` y `slug`. Es una
migración destructiva e irreversible; debe existir un backup antes de aplicarla.

### `GET /config/plan-price` — reglas de pago del onboarding

Devuelve el costo base por participante y la regla vigente de descuento familiar.
La respuesta se usa en el paso `pago` del onboarding; el total se calcula como
`participantes * precio - descuentoPorParticipante * participantes` cuando se
alcanza el mínimo configurado.

**Response 200:**

```json
{
    "precio": 300,
    "moneda": "USD",
    "descuentoHermano": {
        "activo": true,
        "montoPorParticipante": 20,
        "minimoParticipantes": 2
    }
}
```

La configuración (`activo`/`montoPorParticipante`/`minimoParticipantes`) es **por
plan** (F-017): vive en `plans.sibling_discount_enabled/min_participants/amount`
y se edita desde el tab "Precio y cupos" del recurso Plan en el panel Filament.
`descuentoHermano` sale siempre del plan operativo actual
(`Plan::operative()->latest('starts_at')->first()`); si no hay plan operativo,
la respuesta trae `precio: null` y `descuentoHermano` con valores neutros
(`activo: false`, montos en 0). El backend usa el precio persistido del plan
como fuente de verdad al completar el onboarding.
El portal consulta este endpoint estrictamente al montar el paso 4 (`pago`), no al
montar el wizard.

La tabla `platform_settings` (fila única, previa a F-017) queda **deprecada**:
ya no se lee en ningún flujo en tiempo de ejecución. Se conserva sin borrar
como respaldo histórico del valor que tenían los planes existentes antes de
esta migración (ver `2026_09_18_131730_migrate_platform_settings_to_plans`),
no se usa como default al crear planes nuevos (los defaults de columna de
`plans` ya replican los defaults históricos de `platform_settings`).

### `POST /onboarding/{draftId}/step` con `stepId: "pago"` — catálogo real de métodos (F-026)

Bug corregido: el paso "Pago" del wizard de onboarding usaba un catálogo de métodos hardcodeado en
el portal (`Zelle`/`Transferencia`/`Bolívares`/`Efectivo` con datos bancarios de ejemplo fijos),
desconectado de `PaymentMethodResource` del admin. Ahora consume `GET /config/metodos-pago` (el
mismo endpoint que ya usaba `/portal/pagos`, ver `PaymentController::methods()`).

`data.pago.metodo` pasa a guardar el **`code`** del método elegido (ej. `"met_zelle"`), no su
nombre visible. `CompleteOnboarding::handle()` busca el `PaymentMethod` activo por ese `code`
(mismo patrón que `PaymentController::store()` con `metodoId`) para poblar `method_code` y
`method_name` del `Payment` creado; ya no existe el `match` hardcodeado nombre→`methodCode`. Si el
`code` recibido no calza con ningún `PaymentMethod` activo (draft viejo persistido antes de F-026,
o método desactivado por el admin entre que se guardó el paso y se completó el onboarding), cae al
primer método activo disponible en vez de fallar la confirmación del onboarding.

### `PaymentMethod.type` pasa a describir comportamiento, no proveedor (F-023)

`type` (columna `payment_methods.type`, string libre, sin constraint de enum a nivel de BD) deja
de ser el catálogo cerrado de proveedores `ZELLE|EFECTIVO|TRANSFERENCIA_BS|OTRO` y pasa a un enum
de 2 valores de **comportamiento**: `DIRECTO` (se reporta el pago en la app con referencia y
comprobante, comportamiento previo a esta feature) o `COORDINADO_REMOTO` (el primer pago se
coordina fuera de la app, ej. WhatsApp). El proveedor real (Zelle, Binance, Efectivo o cualquier
otro que el admin cree desde `/admin/payment-methods`) sigue viviendo en `code`/`name`, catálogo
abierto sin límite fijo — confirmado antes de esta feature que `type` no se usaba en ninguna regla
de negocio, solo se mostraba en el form/tabla del admin, así que repropositarlo no tuvo blast
radius oculto.

- `PaymentMethodForm.php`: el `Select::make('type')` pasa a esas 2 opciones (`->live()` para
  reactividad). Cuando `type = DIRECTO` se muestran "Instrucciones" y el Repeater "Datos
  bancarios" (`data.detalle`); cuando `type = COORDINADO_REMOTO` se muestran 4 campos nuevos bajo
  `data.whatsapp.*`: `mensajeOnboarding`, `mensajeDashboard`, `linkTexto` y `link` (opcional).
- `PaymentController::methods()`: si `type = COORDINADO_REMOTO` y `data.whatsapp.link` viene
  vacío, el servidor lo resuelve con el WhatsApp operativo del plan activo (`Plan::operative()
  ->latest('starts_at')->first()->whatsapp`, columna `plans.whatsapp` ya existente desde antes de
  esta feature y hasta ahora sin consumidor) como `https://wa.me/{numero}`.
- `CompleteOnboarding::handle()`: el bloque que crea el `Payment` de la orden de inscripción ya no
  exige `referencia` no vacía cuando el `PaymentMethod` resuelto es `COORDINADO_REMOTO` — crea el
  `Payment` igual (`status: PENDIENTE_VERIFICACION`, `reference: null`, `receipt_name: 'Pago
  coordinado por WhatsApp'`, sin `receipt_path`). Un método `DIRECTO` exige `referencia` no vacía
  (tras `trim`) y el archivo `comprobante` en el `complete` (F-035 + F-052; ver sección F-035 más
  abajo). Hasta F-052 la referencia solo se exigía en el cliente.
- `GET /pagos/balance` gana el campo `primerPagoCoordinado` (`null` salvo que la primera orden de
  inscripción de la familia, `orders.is_registration = true` más antigua, tenga el **pago
  placeholder del onboarding**: `PENDIENTE_VERIFICACION`, sin comprobante (`receipt_path` null) y
  sin referencia, con un método `COORDINADO_REMOTO`; F-052 B-1: un pago reportado desde el portal
  con un método `COORDINADO_REMOTO` siempre trae comprobante y referencia, así que no activa el
  banner) — nueva Action
  `App\Actions\Payments\ResolvePendingCoordinatedPayment`, reutilizada tal cual desde el portal
  para el banner de `/portal` y el de la pantalla de éxito del onboarding (ambos consultan el
  mismo balance real; no hay un endpoint separado para el banner de éxito).
  **F-051:** `primerPagoCoordinado` incluye además `ordenId` (uuid de esa orden de inscripción),
  `pagoId` (uuid del Payment placeholder) y `monto` (number, monto declarado en el onboarding).
- `PaymentMethodSeeder.php`: Zelle/Binance/Efectivo quedan en `DIRECTO` (el admin puede pasar
  cualquiera a `COORDINADO_REMOTO` desde el panel); Transferencia Bs queda `active=false` en vez
  de eliminarse — el código ya no depende de su nombre.

### `POST /participantes/{id}/wizard/step` — validación por paso (F-001)

El body es `{ "stepId": "…", "data": { … } }`. El backend valida el contenido de `data` ANTES de persistir (Action `WizardStepValidator`, reglas idénticas al schema zod del portal):

| stepId                 | Reglas                                                                                                                                                                                                           |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `datos-basicos`        | TODOS requeridos: `nombre` (string ≤255), `fechaNacimiento` (`date_format:Y-m-d`), `genero` (`in:MASCULINO,FEMENINO,OTRO,PREFIERO_NO_DECIR`), `cedula`, `tallaCamisa` (≤20), `pesoKg` (numeric)                  |
| `salud`                | Todos opcionales/null: `tipoSangre`, `alergias`, `condicionesMedicas`, `medicamentos`, `discapacidades`, `infoAdicional` (string) y `requiereAcompanante` (boolean). NADA requerido.                             |
| `contactos-emergencia` | `contactosEmergencia` requerido array `min:1`; `contactosEmergencia.*.nombre` / `*.telefono` / `*.parentesco` requeridos; `parentesco` `in:Madre,Padre,Tutor,Abuelo/a,Hermano/a,Otro,Padrino,Tío,Participante`   |
| `encargado-retiro`     | `encargadoRetiro` opcional (`null` o vacío = válido; se normaliza a `null`). Si se llena, `encargadoRetiro.nombre`/`telefono`/`relacion` requeridos y `relacion` con la misma lista `in:`. `documento` opcional. |
| `seguro-medico`        | Todo opcional/null: `aseguradora`, `poliza`, `telefonoEmergencias` (string) y `noTiene` (boolean, `true` ⇒ sin seguro). NADA requerido.                                                                          |

Errores de validación → `422`:

```json
{
    "message": "El nombre es requerido",
    "errors": {
        "data.nombre": ["El nombre es requerido"],
        "data.genero": ["Selecciona un género"]
    }
}
```

Los mensajes son en español (`WizardStepValidator`). El guardado es idempotente: re-guardar el mismo paso sobrescribe `wizard_steps[<stepId>]`.

### Migración `participants.gender` NOT NULL (F-001)

`2026_09_17_000001_make_gender_required_on_participants_table`: backfill de nulos a `PREFIERO_NO_DECIR` y `gender` pasa a `nullable(false)`. `CompleteOnboarding`, factories y seeders garantizan `gender` siempre presente.

### Subida de archivos: formatos, fotos y comprobante del onboarding (F-035)

Fuente única de formatos/límites: `app/Support/UploadRules.php` (espejo en el portal:
`portal/src/lib/uploads.ts`).

| Campo | Endpoints | Formatos | Máx. |
| --- | --- | --- | --- |
| `comprobante` | `POST /pagos`, `POST /ordenes/{id}/enlazar-pago`, `POST /onboarding/{draftId}/complete` | `pdf, jpg, jpeg, png, webp, gif, bmp` | 10 MB |
| `foto` | `PUT /representante/foto`, `PUT /participantes/{id}/foto` | `jpg, jpeg, png, webp, gif, bmp` (regla `image`) | 5 MB |

- HEIC/HEIF **no** se aceptan (Chrome/Filament no los muestran); AVIF se omite porque su
  detección depende del libmagic del hosting.
- Errores `422` por campo en español, p. ej.
  `errors.comprobante: ["El comprobante debe ser PDF, JPG, PNG, WEBP, GIF o BMP."]`,
  `errors.foto: ["La foto no puede pesar más de 5 MB."]`, `*.uploaded` cuando PHP rechaza el
  archivo por `upload_max_filesize` (ver `development-and-deployment.md`).
- **Fotos por method spoofing:** PHP no parsea `multipart/form-data` en un `PUT` real, así que el
  portal envía `POST` multipart con `_method=PUT` a las mismas rutas `PUT` (Laravel lo enruta
  igual). Un `PUT` multipart directo llega sin archivo y responde `422 errors.foto`.
- **`POST /ordenes/{id}/enlazar-pago` — referencia obligatoria (F-049, revierte F-035):**
  `referencia` es `required|string|max:255`; vacía o solo espacios (TrimStrings +
  ConvertEmptyStringsToNull) → `422 errors.referencia: ["Ingresa el número de referencia del pago."]`.
  Aplica a métodos `DIRECTO` y `COORDINADO_REMOTO`, igual que el onboarding `DIRECTO`. El
  `idempotency_hash` es siempre `sha256(orden + referencia + monto normalizado a 2 decimales)`: un
  duplicado exacto responde `422 "Este pago ya fue reportado. Evita duplicados."`.
- **Duplicados y pagos rechazados (F-052 A-1):** en `enlazar-pago` y en `POST /pagos` el chequeo
  de duplicado ignora los pagos `RECHAZADO`: tras un rechazo el representante puede reportar de
  nuevo la misma transferencia (misma referencia y mismo monto) → `200`/`201`. Un pago
  `PENDIENTE_VERIFICACION` o `APROBADO` idéntico sigue respondiendo `422 "Este pago ya fue
  reportado. Evita duplicados."`. `RejectPayment` reescribe el `idempotency_hash` del pago
  rechazado (queda libre el índice único); los rechazados antiguos que aún conserven el hash se
  liberan al llegar el nuevo reporte (`Payment::releaseRejectedHash`).
- **Monto con máximo 2 decimales (F-052 A-7):** `monto` en `enlazar-pago` y `POST /pagos` valida
  `decimal:0,2` → `422 errors.monto: ["El monto admite como máximo 2 decimales."]`. El hash usa el
  monto normalizado (`20`, `20.0` y `20.00` son el mismo pago).
- **Orden cancelada (F-052 A-2):** `enlazar-pago` sobre una orden `CANCELADA` →
  `422 "Esta orden está cancelada."`. `ApprovePayment` se niega a aprobar un pago de una orden
  cancelada (Filament muestra "No se puede aprobar un pago de una orden cancelada. Recházalo.") y
  la orden sigue `CANCELADA`.
- **Concurrencia (F-052 A-8):** la validación y la creación del pago ocurren en
  `App\Actions\Payments\LinkOrderPayment` dentro de `DB::transaction` con la orden bloqueada
  (`lockForUpdate`). Si dos envíos idénticos simultáneos chocan con el índice único, el segundo
  responde `422 "Este pago ya fue reportado. Evita duplicados."` (no `500`). Orden inexistente o
  ajena → `404`.
- **`POST /ordenes/{id}/enlazar-pago` — saldo reportable (F-047):** el monto se valida contra el
  **saldo reportable** = `total - paid - Σ(pagos PENDIENTE_VERIFICACION de la orden)`
  (`Order::reportableBalance()`), no contra el saldo contable, para que la suma de reportes en
  revisión nunca supere el saldo. Saldo contable 0 → `422 "Esta orden ya está pagada; no tiene
  saldo pendiente."`; saldo reportable 0 → `422 "Esta orden ya tiene pagos en revisión que cubren
  el saldo pendiente. Espera a que se verifiquen."`; monto mayor con pagos en revisión →
  `422 "El monto no puede superar el saldo por reportar ($250.00): la orden tiene $50.00 en pagos en
  revisión."` (sin pagos en revisión el mensaje sigue siendo `"El monto no puede superar el saldo
  pendiente."`). Los pagos `RECHAZADO` no descuentan. El portal aplica la misma regla
  (`EnlazarPagoForm`, vía `usePendientePorOrden`).
- **`POST /ordenes/{id}/enlazar-pago` — `abonado` (F-048):** enlazar/reportar un pago crea un
  `Payment` en `PENDIENTE_VERIFICACION` y responde la orden **sin cambiar `abonado` (`paid`) ni
  `estado`**. `abonado` solo aumenta cuando el admin aprueba el pago (`App\Actions\Payments\ApprovePayment`:
  `paid += amount`, `status` = `PAGADA` si `paid >= total`, sino `PENDIENTE_PAGO`); rechazar no lo toca.
- **Montos en mensajes (F-048, F-052 A-4):** formato único compartido con el portal
  (`portal/src/lib/money.ts` → `formatMonto`): 2 decimales fijos, punto decimal y coma de miles
  (`250.5` → `$250.50`, `1250.5` → `$1,250.50`; `App\Support\Money::format`). Incluye el mínimo:
  `422 "El abono mínimo es $20.00."`.
- **`POST /pagos/{payment}/completar-coordinado` (F-051):** botón "Reportar acá" del banner
  `primerPagoCoordinado` de `/portal`. **No crea un Payment nuevo:** completa el placeholder del
  onboarding (mismo registro) — Action `App\Actions\Payments\CompleteCoordinatedPayment`,
  reutiliza las reglas de `LinkOrderPayment` (`assertCanReport`, `reportHash`). Body
  `multipart/form-data` igual que enlazar-pago (`CompleteCoordinatedPaymentRequest` extiende
  `LinkOrderPaymentRequest`): `monto` (`decimal:0,2`), `esCompleto`, `metodoId`, `metodoNombre`
  (opcional; el nombre se toma del método en BD), `referencia` y `comprobante` obligatorios.
  Respuesta `200 { data: OrdenTienda }` (la orden de inscripción, sin cambios en `abonado`).
  El pago se actualiza con método (cualquier método **activo**), monto, referencia, comprobante,
  `paid_at` = hoy e `idempotency_hash` = el de un reporte normal (orden + referencia + monto), así
  que un enlazar-pago posterior idéntico es duplicado; conserva `concept` y `order_uuid` y sigue
  `PENDIENTE_VERIFICACION` (flujo normal aprobar/rechazar del admin). El banner se apaga solo
  (`receipt_path`/`reference` ya no son null). Transacción con el pago y la orden bloqueados.
  Errores: pago inexistente o de otro usuario → `404`; pago ya completado (con comprobante o
  referencia; también el doble envío) → `422 "Este pago ya fue reportado. Evita duplicados."`;
  pago aprobado/rechazado → `422 "Este pago ya fue verificado; no se puede modificar."`; pago que
  no es de una orden de inscripción o cuyo método no es `COORDINADO_REMOTO` →
  `422 "Este pago no es un pago coordinado pendiente de tu inscripción."`; método inactivo →
  `422 "Método de pago no disponible."`; orden cancelada, abono mínimo, duplicado y errores de
  campo como en enlazar-pago. **Saldo reportable sin contar ese mismo pago:**
  `total - paid - (Σ pendientes - monto del placeholder)`; si el placeholder era de modalidad
  completo, se puede reportar hasta el saldo completo.
- **`POST /onboarding/{draftId}/complete`** acepta `multipart/form-data` con `comprobante`
  (opcional a nivel de FormRequest). `CompleteOnboarding::handle($draft, $receipt)` lo exige cuando
  hay monto a reportar y el método resuelto **no** es `COORDINADO_REMOTO` (método DIRECTO):
  sin archivo → `422 errors.comprobante: ["Adjunta el comprobante de tu primer pago para completar
  la inscripción."]` y el onboarding NO se completa. **F-052 (A-3):** en el mismo caso la
  `referencia` guardada en el paso `pago` es obligatoria (vacía o solo espacios →
  `422 errors.referencia: ["Ingresa el número de referencia del pago."]`, ambos errores a la vez). Se guarda en el disco privado `local`
  (`comprobantes/`) y llena `receipt_path`/`receipt_name` del `Payment`, así que es descargable por
  `GET /pagos/{payment}/comprobante` y visible en Filament. `COORDINADO_REMOTO` sigue sin
  comprobante. Si la transacción falla, el archivo guardado se borra.

Las rutas deben tener nombres explícitos, por ejemplo `pagos.index` y `pagos.store`, para que puedan documentarse y consumirse sin dispersar URLs.

## Receta de implementación

1. Crear la Action con `handle()` y lógica independiente de HTTP.
2. Crear Controller API para orquestar Request -> Action -> Resource.
3. Crear FormRequest y API Resource.
4. Registrar la ruta nombrada en `routes/api.php`.
5. Añadir migración, factory/seeder y test Feature cuando corresponda.

## Compatibilidad con el portal

No cambiar nombres de campos para reflejar nombres de columnas Laravel. Si una respuesta necesita transformar `email` a `correo`, esa conversión vive en el Request/Resource y queda cubierta por un test de contrato.
