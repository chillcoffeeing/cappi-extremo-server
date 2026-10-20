# Arquitectura del servidor

## Responsabilidad

Laravel expone una API REST JSON para el portal React (`../portal`, SPA aparte) y, en la misma
app, el panel administrativo Filament (guard `admin`, ver `backoffice/`). Las dos superficies
usan los mismos modelos y las mismas Actions.

El servidor debe seguir las convenciones de Laravel. La única convención adicional es `Actions` para encapsular casos de uso.

## Flujo de un endpoint

```text
Request HTTP -> FormRequest -> Controller -> Action -> Model
                                      |
                                      +-> API Resource -> JSON
```

### Ubicación de cada responsabilidad

| Responsabilidad                 | Ubicación                         |
| ------------------------------- | --------------------------------- |
| Caso de uso y reglas de negocio | `app/Actions/...`                 |
| Entrada HTTP y orquestación     | `app/Http/Controllers/Api/...`    |
| Validación y autorización       | `app/Http/Requests/...`           |
| Forma de la respuesta           | `app/Http/Resources/...`          |
| Persistencia y relaciones       | `app/Models/...` y migraciones    |
| Exposición HTTP                 | `routes/api.php`                  |
| Integración externa             | Services o Actions, según el caso |

Los controllers no contienen reglas de negocio. Una Action no recibe `Request`, no devuelve `response()` y puede reutilizarse desde comandos, seeders o jobs.

## Dominios

- Auth y cuenta del representante.
- Onboarding.
- Participantes, contactos y fichas individuales.
- Plan activo e inscripciones.
- Pagos y órdenes (inscripción y tienda).
- Catálogo de productos y métodos de pago.
- Administración (Filament): plan y su contenido, expedientes, finanzas, borrado controlado
  (`app/Actions/Admin`).

La Galería del portal no pertenece al API: el sidebar abre un enlace externo de Google Drive.
No hay correo saliente ni tareas programadas (`routes/console.php` no agenda nada).

## Persistencia

Tablas solo vía migraciones. Dinero en `decimal(16,4)`. Identificadores públicos `uuid`
(`HasPublicUuid`); las relaciones usan `*_uuid`. Los datos de cada representante se filtran por
el usuario autenticado en cada consulta.

- `participants` guarda la ficha (bloques JSON de salud, contactos de emergencia, encargado de
  retiro y seguro) y `data_completed`, que controla el asistente del portal.
- Los hijos de un representante se borran en cascada con él (F-053); órdenes y pagos quedan con
  `user_uuid = NULL` y una copia en `representative_snapshot`.
- Comprobantes en `storage/app/private`; fotos e imágenes en `storage/app/public`.
- Auditoría con `spatie/laravel-activitylog` (`activity_log`) en planes, órdenes y pagos.

## Reglas de plataforma

- API stateless con Bearer tokens; no depender de sesiones, cookies ni CSRF para `/api`.
- Respuestas públicas en camelCase, incluso si las columnas internas usan snake_case.
- Errores JSON con forma `{ "message": "..." }`.
- `QUEUE_CONNECTION=sync` en hosting compartido salvo que un cron de workers esté documentado.
- No introducir DDD, repositorios, gateways o adaptadores sin una necesidad concreta.
