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
