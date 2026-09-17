# Arquitectura del servidor

## Responsabilidad

Laravel expone una API REST JSON para el portal React. El frontend conserva TanStack Router/Query y sustituye sus mocks por llamadas a los servicios HTTP sin cambiar los tipos públicos.

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
- Participantes, contactos, autorizaciones y fichas individuales.
- Plan activo e inscripciones.
- Pagos y órdenes de tienda.
- Catálogo.

La Galería del portal no pertenece al API por ahora: el sidebar abre un enlace
externo de Google Drive y la URL concreta del plan queda pendiente de
configuración. No crear un endpoint de galería hasta definir esa integración.

## Persistencia inicial

Las tablas se crearán únicamente con migraciones. El dinero usa `decimal(16,4)`. Las relaciones principales pertenecen al usuario autenticado. Los seeders deben reproducir los mocks del portal para permitir una migración incremental mock -> API.

La primera tabla de dominio es `participants`: guarda la ficha individual, el
estado `data_completed` que controla el gate del wizard y los bloques JSON de
salud, contactos de emergencia, encargado de retiro, seguro y autorizaciones.
No se persisten documentos subidos de participantes. Los comprobantes quedan
reservados al dominio de pagos.

El primer endpoint implementado es `GET /api/participantes`, protegido por
Sanctum y aislado por `user_id`. Su respuesta se construye con
`App\Http\Resources\ParticipantResource`; los controllers no exponen columnas
internas directamente.

## Reglas de plataforma

- API stateless con Bearer tokens; no depender de sesiones, cookies ni CSRF para `/api`.
- Respuestas públicas en camelCase, incluso si las columnas internas usan snake_case.
- Errores JSON con forma `{ "message": "..." }`.
- `QUEUE_CONNECTION=sync` en hosting compartido salvo que un cron de workers esté documentado.
- No introducir DDD, repositorios, gateways o adaptadores sin una necesidad concreta.
