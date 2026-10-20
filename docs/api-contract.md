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
- CORS: no hay `config/cors.php`, así que rige el default del framework (`HandleCors` con
  `allowed_origins: ['*']` para `api/*`, sin credenciales). El portal no usa cookies (Bearer). Si
  se quiere restringir a `app.cappixtremo.com`, hay que publicar `config/cors.php` (F-063, fe-C-7).

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
  `/admin/participants/{id}`. `seccion` usa las claves de `SectionKey` en el portal
  (`datosBasicos`, `salud`, `contactosEmergencia`, `documentos`); las viejas pueden traer
  `seguroMedico` (F-076: sección eliminada, las pendientes se descartaron).
  Ver shape completo en `portal/docs/business-logic/12-api-contrato.md`. F-061: el admin ya no
  ofrece `documentos` al crear (las viejas se siguen viendo); guardar una sección no resuelve la
  solicitud, la resuelve un admin.

### Participantes: PATCH, foto y wizard (F-061)

- `PATCH /participantes/{id}` (`UpdateParticipantRequest` → `UpdateParticipant`):
  - `emergencyContacts` **reemplaza la lista completa** (antes el merge por índice duplicaba al
    borrar, fe-A-5).
  - `pickupContact: null` quita el encargado; un objeto reemplaza al anterior (fe-A-17).
  - `medicalInsurance` se **ignora** (F-076: el seguro médico se eliminó; sin 422 para un portal viejo).
  - La respuesta mantiene `seguroMedico: {}` vacío por compatibilidad (quitar en la próxima limpieza).
  - `health` mantiene el merge profundo de objeto.
  - **`photoUrl` se ignora** (fe-A-4 / api-A-7): ya no tiene regla, `validated()` lo descarta y
    el resto del PATCH se guarda. Compatibilidad: un portal viejo cacheado que lo manda no recibe
    422. La foto solo cambia por `PUT /participantes/{id}/foto`.
- `PUT /participantes/{id}/foto` y `PUT /representante/foto` borran la foto anterior solo si es
  un archivo propio (`participants/…` o `profiles/…`, sin URL, `..`, `\`, `:` ni `/` inicial;
  `App\Support\PublicPhoto::deleteIfOwned`). Un valor manipulado nunca borra imágenes del
  catálogo ni de otra familia, ni da 500 por traversal.
- `fotoUrl` (participante y representante) = `Storage::disk('public')->url(photo_url)`
  (fe-B-1, sin depender de `FILESYSTEM_DISK`); si la columna tiene una URL absoluta legada se
  devuelve tal cual (guard idempotente, sin migración: en prod hay 0 filas `LIKE 'http%'`).
- `GET /participantes/{id}/wizard` responde **sin wrapper `data`** (`participantId`,
  `datosCompletos`, `completedSteps`, `data` = pasos guardados). El portal lo usa para retomar el
  wizard (fe-A-15).
- Onboarding general: draft persistente, pasos idempotentes y completion.
- Cuenta, plan activo, pagos, órdenes y catálogo de productos.

Pendiente (revisado en F-063 contra `routes/api.php`):

- **Envío de correos.** `POST /auth/forgot-password`, `/reset-password` y `/verify-email` existen,
  pero no hay canal de correo: el enlace de recuperación/verificación no le llega a nadie
  (spec en `docs/notificaciones/`, F-036..F-043).
- Todo lo demás que consume el portal (cuenta, participantes, plan, onboarding, inscripciones,
  pagos, órdenes y catálogo) está implementado; ver la lista de rutas más abajo.

No se deben crear rutas nuevas sin su Action, Request, Resource y test Feature correspondientes.

El refresh rotativo ya está implementado: los tokens opacos se almacenan
hasheados, expiran en 24 horas o 30 días según `remember`, se revocan al usarlo
y el replay del token anterior devuelve `401`.

### Seguridad de cuentas (F-056)

- **Reset de contraseña** (`POST /auth/reset-password`, Action
  `App\Actions\Auth\ResetPortalPassword`): transacción con `lockForUpdate`
  del token; contraseña por Eloquent (cast `hashed` → bcrypt); invalida los
  demás tokens `password` pendientes; borra los tokens Sanctum y revoca los
  refresh tokens del usuario (cierra todas sus sesiones). `forgot-password` ya
  **no** escribe el token en el log (no hay canal de correo todavía: F-036+).
- **Rate limiting** (limiters en `AppServiceProvider`, middleware `throttle:*`
  en `routes/api.php`). Al pasar el límite: `429` con
  `{ "message": "Demasiados intentos. Espera un minuto e inténtalo de nuevo." }`.

  | Ruta | Limiter | Límite |
  | --- | --- | --- |
  | `POST /auth/login` | `portal-login` | 5/min por IP+email (minúsculas) y 60/min por IP |
  | `POST /auth/register` | `portal-register` | 20/min por IP |
  | `POST /auth/forgot-password`, `/reset-password`, `/verify-email` | `portal-password` | 3/min por IP+email/token y 20/min por IP |
  | `POST /auth/refresh` | `portal-refresh` | 120/min por IP |

  Los límites por IP son holgados porque varias familias pueden compartir IP
  (colegio, CGNAT). La IP es `$request->ip()`: no hay `trustProxies`
  configurado, que es lo correcto en cPanel sin CDN (`REMOTE_ADDR` es el
  cliente). Si se pone Cloudflare u otro proxy delante, hay que configurar
  `$middleware->trustProxies(at: [...])` en `bootstrap/app.php` o todas las
  familias contarán como la IP del proxy.
- **Duplicados**: `PUT /representante/contacto` con un `correo` de otra cuenta y
  `PUT /representante/identificacion` con un `documento` de otra cuenta
  responden `422` con `errors.correo` / `errors.documento` (antes 500).
- **`GET /api/user` eliminado**: devolvía el modelo crudo en snake_case, fuera
  del contrato. El portal usa `GET /representante`.

### Públicas

- `POST /auth/login`
- `POST /auth/register`
- `POST /auth/refresh`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `POST /auth/verify-email`

### Requieren `auth:sanctum`

- `POST /auth/logout`
- `GET /representante`, `PUT /representante/contacto`, `PUT /representante/identificacion`,
  `PUT /representante/encargado` y `PUT /representante/foto` (no hay `PUT /representante`)
- `GET /participantes`, `GET|PATCH /participantes/{id}` y `PUT /participantes/{id}/foto`
- `POST /participantes` — alta de hijo (botón "Agregar otro" → modal "Agregar hijo"): body
  `{ name, birthDate (Y-m-d), gender }` (claves en inglés), responde `200 { data: Participante }`.
  Si el representante ya completó el onboarding y hay plan operativo crea la inscripción y una
  orden de inscripción nueva y recalcula el descuento por hermanos (`RegisterParticipantInscription`;
  F-063 fe-C-3 lo documenta, existía desde antes)
- `GET /inscripciones/{participante}/detalle`
- `GET /pagos/balance`
- `GET|POST /pagos`
- `POST /pagos/{payment}/completar-coordinado` — completa el pago coordinado pendiente del
  onboarding (F-051; ver notas más abajo)
- `GET /pagos/{payment}/comprobante` — descarga el comprobante (disco privado `local`); solo el
  representante dueño del pago, `404` para cualquier otro usuario (backoffice F-004); `404` también si el archivo ya no
  está en el disco (F-062 api-A-12; antes `500`)
- `GET|POST /ordenes`
- `POST /ordenes/{id}/enlazar-pago`
- `GET /participantes/{id}/wizard`, `POST /participantes/{id}/wizard/step` y `POST /participantes/{id}/wizard/complete`
- `GET /onboarding/{draftId}`, `POST /onboarding/{draftId}/step` y
  `POST /onboarding/{draftId}/complete` (`draftId` = uuid del usuario). Desde F-063 (fe-A-14) el
  portal usa el `GET` para retomar el onboarding en otro navegador; sin cambios en el API.
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
        "dias": [{ "numeroDia": 1, "fecha": "2026-12-14", "titulo": "Llegada", "descripcion": "", "ubicacion": null, "estado": "PLANIFICADO", "actividades": [] }],
        "avance": { "modo": "AUTO", "porcentaje": 0, "etiqueta": null, "diaActual": null, "totalDias": 0 },
        "avisos": []
    }
}
```

`dias`, `avance` y `avisos` son nuevos (F-005, backoffice Fase 2). **Desde F-063 el Inicio del
portal los consume** (fe-A-9/fe-C-2): el momento del plan sale de `estado` + `fechaInicio`/
`fechaFin`, el día actual de `avance.diaActual` o de `dias[].fecha`, la barra de avance de
`avance.porcentaje`, y `avisos` se muestran arriba del resumen de pagos. Detalle en
`12-api-contrato.md`. `fechaInicio`/`fechaFin` pueden ser `null`.
Mientras el admin no cargue `plan_days` para un plan, `dias` sigue devolviendo
el JSON legado de `plans.days` (`{ date, numeroDia, descripcion }` con HTML; compatibilidad
temporal, ver `api/docs/backoffice/02-datos-y-migraciones.md`); en cuanto existan filas en
`plan_days`, `dias` pasa a la forma estructurada (F-063: solo los días con `is_visible`, ordenados
por `sort_order` y `day_number`; si todos están ocultos, `[]`, sin volver al JSON legado;
`avance.totalDias` sigue contando todos):

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

**Sin plan operativo (F-059, fe-C-4):** si ningún plan está `PUBLICADO` ni
`EN_CURSO` (entre temporadas), `GET /planes/activo` responde **`204 No Content`**
sin cuerpo (no `404`, no `{data:null}`). El portal lo trata como "No hay
inscripciones abiertas en este momento" (wizard con "Continuar" deshabilitado) y
en Inicio no muestra el hero del plan.

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
La respuesta se usa desde el paso `participantes` del onboarding (banner de descuento) y en `pago`; el total se calcula como
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
El portal consulta este endpoint desde el paso "Participantes" (F-024, para mostrar el
descuento por hermanos) y lo reintenta al cambiar de paso; no al montar el wizard.

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
| `seguro-medico`        | **Eliminado (F-076).** Se acepta con `200` y no se guarda nada (compatibilidad con un portal anterior). `POST /wizard/complete` exige solo los 4 pasos de arriba.                                                 |

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
  archivo por `upload_max_filesize` (ver `development.md`).
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
- **Método validado (F-062 api-A-11):** `enlazar-pago` resuelve `metodoId` contra
  `payment_methods` (`code` + `active = true`), igual que `POST /pagos` y `completar-coordinado`.
  Si no existe o está inactivo → `422 "Método de pago no disponible."` (no se crea el pago ni se
  guarda el archivo). `metodoNombre` se sigue aceptando (un portal viejo lo manda), pero se
  ignora: el pago guarda el `name` del catálogo. Los pagos ya guardados no se tocan.
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
- **`POST /ordenes` — pedido de tienda resuelto en el servidor (F-058, api-A-2/api-A-13):**
  body `{ items: [{ productoId, variante, qty }] }`. Action `App\Actions\Orders\CreateStoreOrder`:
  busca el `Product` por `uuid`, toma **nombre y precio del catálogo** (el `precio`/`nombre` del
  cliente se ignoran), exige `in_stock`, valida `variante` ∈ `variants` (recortadas, no vacías) o
  `null` si el producto no tiene variantes, une líneas repetidas (producto + variante) y calcula
  `total = Σ precio × qty` (redondeo a 4 decimales, tope de `decimal(16,4)`). Cada ítem guardado:
  `{ productoId, nombre, variante, qty, precio }` (solo agrega `productoId`; `variante` puede ser
  `null`). Topes en `StoreOrderRequest`: `items` 1..20, `qty` entero 1..99 (también tras unir
  líneas), `productoId` string ≤ 64, `nombre`/`variante` ≤ 255. Errores `422 { message, errors }`
  en español, nunca `500`: producto inexistente → `items.N.productoId` "Uno de los productos del
  carrito ya no está en la tienda. Actualiza la página e intenta de nuevo."; sin stock →
  `items.N.productoId` "«X» no tiene stock en este momento. Quítalo del carrito para continuar.";
  variante inválida → `items.N.variante` "«X» no tiene la opción «V». Actualiza la página…"; falta
  variante → "Elige una opción de «X» antes de crear el pedido."; `qty` > 99 → `items.N.qty`
  "Puedes pedir hasta 99 unidades de cada producto por pedido."; más de 20 líneas → `items`.
  **Compatibilidad (portal cacheado anterior a F-058):** se sigue aceptando el shape viejo
  `{ nombre, variante, qty, precio }`; se resuelve por `name` exacto (si hay varios productos con
  ese nombre, la variante desambigua), `precio` se ignora y se aplican las mismas reglas; si no se
  identifica un único producto → `422 items.N.nombre` "«X» ya no está en la tienda. Actualiza la
  página e intenta de nuevo." / "No pudimos identificar «X» en la tienda…". Las órdenes existentes
  no se tocan ni se re-precian. Tests: `tests/Feature/Api/StoreOrderTest.php`.
- **`GET /tienda/productos` (F-058):** sin cambios de shape; `id` es el uuid que el portal manda
  como `productoId`; `variantes` e `imagenes` pueden ser `[]` (el producto se compra igual).
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

### Inscripciones y saldos (F-059)

Revisión 2026-10-19 (api-A-3, A-4, A-5, B-1, B-2, B-5). Las respuestas solo agregan casos `422`
con `{ message, errors }` en español; ningún shape cambia.

- **El onboarding se completa una sola vez (api-A-3).** La regla depende del estado del
  **representante** (`users.onboarding_status`):
  - Mientras **no** esté `COMPLETADO`, su draft se guarda y se completa como siempre, también un
    draft guardado con el formato viejo (`data.pago` plano, `metodo` por nombre) o uno marcado
    `COMPLETADO` sin que el usuario lo esté.
  - Si **ya** está `COMPLETADO`:
    - `POST /onboarding/{id}/step` y `POST /onboarding/{id}/complete` sobre **su draft
      `COMPLETADO`** → `200` **idempotente**: devuelve el draft sin cambios (`status:
      "COMPLETADO"`), no reabre el draft ni crea participantes, inscripciones, órdenes ni pagos.
      Cubre el doble clic y el reintento tras un error de red.
    - Sobre **cualquier otro draft** (existente o nuevo) → `422 errors.onboarding: ["Tu
      inscripción ya fue completada. Para agregar otro participante usa «Agregar otro» en «Mis participantes»
      del portal."]`.
    - `GET /onboarding/{id}` y los `POST /onboarding/{id}/step|complete` con un `draftId` que no existe **ya no crea** un draft: mismo
      `422 errors.onboarding`. Los drafts existentes se siguen pudiendo leer (`GET` → `200`).
  - Los hijos nuevos de una familia ya inscrita van por `POST /participantes`
    (`RegisterParticipantInscription`: orden nueva + recálculo del descuento hermanos).
- **Locks (api-B-1/B-2).** `CompleteOnboarding` bloquea el `User` y luego el draft
  (`lockForUpdate`) y revisa los estados ya bloqueado: dos `complete` simultáneos quedan en
  serie y el segundo es idempotente. `SaveOnboardingStep` bloquea el draft.
  `RegisterParticipantInscription` bloquea el `User` antes de leer o crear órdenes, y
  `RecalculateInscriptionOrders` lee las órdenes de inscripción con `lockForUpdate` en orden de
  `id`. El orden de locks es siempre **User → orders**, así que dos altas de la misma familia no
  hacen deadlock entre sí. Con `ApprovePayment` (payment → order) tampoco, porque ese camino no
  toma el `User`.
- **Plazas de órdenes CANCELADA (api-A-5).** Para decidir si una familia ya tiene sus plazas
  cubiertas, `RegisterParticipantInscription` solo cuenta las órdenes de inscripción **no**
  `CANCELADA`. `RecalculateInscriptionOrders` no toca las `CANCELADA`: conservan `items`, `total`
  y `status` tal como quedaron al cancelarlas. `CompleteOnboarding` no reutiliza una orden de
  inscripción cancelada. No se re-precia ninguna orden en el despliegue.
- **Mínimo de cuotas en el servidor (api-B-5).** En `complete`, con `pago.modalidad = "cuotas"`,
  `montoAbonar` debe ser ≥ `LinkOrderPayment::MINIMUM_INSTALLMENT` ($20). Si el total es menor,
  el mínimo es el total. Si no → `422 errors.montoAbonar: ["El abono mínimo es $20.00. Vuelve al
  paso Pago y ajusta el monto."]` y no se crea nada.
- **Pasos faltantes (fe-B-5).** El `422 errors.steps` ahora dice "Faltan pasos por guardar (…).
  Revisa los pasos con «Atrás» y vuelve a confirmar." (la clave `steps` no cambia). El portal,
  al recibirlo, vuelve a guardar los pasos que tiene en local y reintenta una vez.
- **`GET /pagos/balance` sin órdenes CANCELADA (api-A-4).** Mismo shape:
  - `totalPagar` = Σ `total` de las órdenes **no** `CANCELADA`. Si la familia no tiene ninguna
    orden de inscripción (ni siquiera cancelada), se usa Σ `enrollments.total_amount` como antes.
  - `totalAbonado` = Σ `amount` de los pagos `APROBADO`, **sin** los vinculados a una orden
    `CANCELADA`. Los reportes sueltos (`POST /pagos`, sin orden) siguen contando.
  - `saldo` = `max(totalPagar − totalAbonado, 0)`.
  Solo cambia la lectura: no se modifican órdenes ni pagos. Tests:
  `tests/Feature/Api/InscriptionIntegrityTest.php`.

Las rutas deben tener nombres explícitos, por ejemplo `pagos.index` y `pagos.store`, para que puedan documentarse y consumirse sin dispersar URLs.

## Receta de implementación

1. Crear la Action con `handle()` y lógica independiente de HTTP.
2. Crear Controller API para orquestar Request -> Action -> Resource.
3. Crear FormRequest y API Resource.
4. Registrar la ruta nombrada en `routes/api.php`.
5. Añadir migración, factory/seeder y test Feature cuando corresponda.

## Compatibilidad con el portal

No cambiar nombres de campos para reflejar nombres de columnas Laravel. Si una respuesta necesita transformar `email` a `correo`, esa conversión vive en el Request/Resource y queda cubierta por un test de contrato.
