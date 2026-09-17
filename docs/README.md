# Documentación del servidor Laravel

API backend del portal Familias Cappi Xtremo.

## Fuente de verdad

- Contrato funcional completo: [portal/docs/business-logic/12-api-contrato.md](../../portal/docs/business-logic/12-api-contrato.md)
- Plan de implementación: [portal/docs/business-logic/11-plan-laravel.md](../../portal/docs/business-logic/11-plan-laravel.md)
- Reglas de implementación: [laravel-initial-rules.md](../../laravel-initial-rules.md)

Los documentos de esta carpeta contienen únicamente las decisiones y procedimientos que afectan al servidor. Si el contrato cambia, actualizar primero el documento canónico del portal y después reflejar el cambio aquí.

## Índice

- [Arquitectura](architecture.md): límites de la aplicación y estructura Laravel.
- [Contrato API](api-contract.md): convenciones HTTP y mapa de endpoints.
- [Autenticación](authentication.md): Sanctum token-only y refresh rotativo.
- [Desarrollo y despliegue](development-and-deployment.md): comandos, pruebas y Namecheap.
- [Backoffice](backoffice/README.md): construcción, seguridad, recursos Filament, operación financiera, contenido del plan y despliegue administrativo.

## Estado actual

La aplicación está en Laravel 13. La infraestructura Sanctum, autenticación
base y el primer endpoint de participantes ya están implementados; quedan los
dominios y el refresh rotativo descritos abajo.

## Primer slice implementado

La infraestructura API ya está habilitada con Laravel Sanctum token-only. El
endpoint `GET /api/participantes` está protegido por `auth:sanctum`, filtra por
el usuario autenticado y cuenta con prueba Feature de acceso no autorizado y
aislamiento entre usuarios.

Auth base implementada: registro, login, refresh rotativo y logout. Quedan
recuperación de contraseña y verificación de email antes de conectar todos los
flujos de Auth del portal a producción.
