# Documentación del API (Laravel + Filament)

API REST del portal de familias y panel administrativo de Cappi Xtremo, en la misma app.
Qué hace el sistema en conjunto: [../../docs/producto.md](../../docs/producto.md).

## Índice

| Documento | Contenido |
| --- | --- |
| [architecture.md](architecture.md) | Límites, flujo de un endpoint, dominios, reglas de plataforma |
| [api-contract.md](api-contract.md) | Convenciones HTTP y mapa de endpoints (canónico junto con el del portal) |
| [authentication.md](authentication.md) | Sanctum token-only, refresh rotativo, rate limiting |
| [development.md](development.md) | Puesta en marcha local, tests, subida de archivos |
| [laravel-initial-rules.md](laravel-initial-rules.md) | Marco de trabajo: receta de un endpoint, Actions, tests |
| [backoffice/README.md](backoffice/README.md) | Panel Filament: roles, recursos, plan, finanzas, flujos, auditoría |
| [../scripts/README.md](../scripts/README.md) | Despliegue a cPanel (ver también `../../docs/despliegue.md`) |

## Contrato

El contrato funcional completo vive en
[portal/docs/business-logic/12-api-contrato.md](../../portal/docs/business-logic/12-api-contrato.md)
y su resumen del lado servidor en [api-contract.md](api-contract.md). Un cambio de endpoint
actualiza los dos.

## Estado

Todos los dominios del portal están implementados (auth, onboarding, participantes, plan
activo, inscripciones, pagos, órdenes y tienda) y el panel admin cubre plan, representantes,
participantes, inscripciones, órdenes/pagos, métodos de pago y productos. Lo que falta respecto
del requerimiento original (correo, recordatorios, fecha límite de pago, cupos que bloqueen…)
está listado en `../../docs/producto.md` → Pendiente.
