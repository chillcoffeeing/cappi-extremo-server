# Despliegue y runbook

## Entornos

- Local: SQLite o MySQL de desarrollo.
- Testing: base aislada y archivos temporales.
- Producción: MySQL en Namecheap/cPanel.

El admin se recomienda en `admin.cappixtremo.com`, apuntando al mismo Laravel pero con panel separado.

## Despliegue

1. Activar modo mantenimiento si aplica.
2. Instalar dependencias Composer.
3. Ejecutar migraciones con `--force`.
4. Ejecutar `config:cache`, `route:cache` y `view:cache`.
5. Ejecutar seeders administrativos controlados.
6. Verificar enlace de storage.
7. Verificar acceso a `/admin`.
8. Revisar logs.
9. Desactivar mantenimiento.

## Variables necesarias

```text
APP_ENV
APP_KEY
APP_URL
DB_CONNECTION
DB_HOST
DB_DATABASE
DB_USERNAME
DB_PASSWORD
FILESYSTEM_DISK
QUEUE_CONNECTION
```

No almacenar credenciales administrativas en el repositorio.

## Checklist posterior

- Login admin.
- Permisos por rol.
- Dashboard carga KPIs.
- Comprobante visible solo con permiso.
- API de plan activo devuelve contenido publicado.
- Portal refleja días, avance y avisos.
- Aprobación de pago actualiza orden.
- Rechazo conserva saldo.
- Auditoría registra actor y cambios.
- Backups recientes disponibles.

## Incidentes financieros

Ante un saldo incorrecto:

1. No editar directamente la base en producción.
2. Identificar orden y pagos asociados.
3. Revisar auditoría.
4. Ejecutar Action o comando de reconciliación.
5. Registrar motivo y responsable.
6. Confirmar balance del portal.

## Rollback

Las migraciones destructivas no se ejecutan automáticamente en producción. Toda migración de contenido debe incluir respaldo, script reversible y validación de conteos antes y después.

## Backups y restauración

**Qué respaldar:**

- Base de datos (MySQL en producción) — incluye toda la operación: representantes, planes,
  inscripciones, pagos, auditoría (`activity_log`).
- `storage/app/private` — comprobantes de pago (disco `local`, privado desde F-004/F-007).
- `storage/app/public` — imágenes de plan, productos y perfiles (disco público, no sensible).
- `.env` de producción — **no** como backup versionado junto al código; guardar aparte y cifrado
  (contiene `APP_KEY`, credenciales de BD).

**Frecuencia y retención (mínimo recomendado para hosting compartido):**

- Dump de base de datos diario (`mysqldump` vía cron), retención de al menos 14 días.
- `storage/app/private` y `storage/app/public` en el mismo ciclo del dump o con backup del panel
  de hosting (cPanel suele ofrecer backups de archivos separados de los de BD).
- Backups fuera del propio servidor (descarga a otro almacenamiento) — un backup que vive en el
  mismo disco que falla no protege de nada.

**Restauración (runbook):**

1. Activar modo mantenimiento.
2. Restaurar el dump de BD más reciente anterior al incidente.
3. Restaurar `storage/app/private` y `storage/app/public` del mismo punto en el tiempo que el dump
   (un desfase entre BD y archivos deja registros de `payments.receipt_path` apuntando a archivos
   que no existen).
4. Ejecutar `php artisan migrate --force` si el código desplegado es más nuevo que el backup.
5. Verificar el checklist posterior de este documento (login admin, permisos, dashboard, etc.).
6. Comparar conteos clave (pagos aprobados, órdenes pagadas) antes/después de restaurar.
7. Desactivar mantenimiento y registrar el incidente (causa, ventana de datos perdida si la hubo).

**No implementado:** automatización de backups (script/cron) — depende del hosting final
(Namecheap/cPanel según `01-arquitectura-y-seguridad.md`), que no está aprovisionado todavía. Este
runbook documenta el procedimiento; ejecutarlo es tarea de despliegue, no de esta fase de
desarrollo.

## Revisión de archivos y logs (Fase 6)

Verificado en esta pasada:

- Los `.gitignore` anidados de Laravel (`storage/app/public/.gitignore`,
  `storage/app/private/.gitignore`, `storage/logs/.gitignore`) ya excluyen todo su contenido
  (`*` + `!.gitignore`) — comprobantes y logs no se versionan aunque existan físicamente en disco.
  No se encontró ningún gap de `.gitignore` real.
- `storage/logs/laravel.log` no contiene contraseñas ni tokens en texto plano — el único valor
  parecido a un secreto que aparece en las consultas SQL logueadas es `token_hash`, que por
  definición ya es un hash, no el token real.
- Los comprobantes de pago viven en el disco `local` (privado) desde F-004/F-007; no hay URLs
  públicas permanentes hacia ellos (ver `01-arquitectura-y-seguridad.md`).
- `LogsActivity` en `Plan`/`Payment`/`Order` solo registra campos operativos (`status`, montos,
  `price`, `capacity`) — ningún modelo audita datos sensibles de ficha (salud, identificación) ni
  credenciales.
- `AdminUser` oculta `password`/`remember_token` vía `#[Hidden]`; no se serializa en ninguna
  respuesta ni en el JSON de actividad.
