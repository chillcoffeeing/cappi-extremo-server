# Plan activo y contenido del portal

## Objetivo

El admin controla todo lo que el representante ve en Inicio y en el detalle del plan. Existe un solo plan operativo activo.

## Información general

Editar:

- Nombre.
- Temporada.
- Sede.
- Descripción.
- Portada.
- Fechas.
- Rango de edades.
- Precio y moneda.
- Cupo.
- WhatsApp operativo.

## Días

Desde Filament se debe poder agregar, editar, duplicar, eliminar, ordenar, ocultar y completar días. Cada día tiene fecha, título, descripción, ubicación y estado.

## Actividades

Cada día puede contener actividades con horario, descripción, ubicación, requisitos y estado. El orden administrativo debe ser el orden mostrado en el portal.

## Staff

Administrar nombre, rol, foto, contacto, visibilidad y orden. La asignación específica a días puede agregarse en una segunda iteración.

## Estado operativo

```text
BORRADOR -> PUBLICADO -> EN_CURSO -> FINALIZADO
              |             |
            PAUSADO       CANCELADO
```

Las transiciones deben ejecutarse mediante Actions y registrar motivo cuando sean pausa, cancelación o finalización manual.

## Avance

El portal debe consumir un bloque `avance` desde el API:

```json
{
    "modo": "MANUAL",
    "porcentaje": 60,
    "etiqueta": "Experiencia en curso",
    "diaActual": 2,
    "totalDias": 5
}
```

### Automático

Calcular a partir de días y actividades completadas.

### Manual

Permitir porcentaje, etiqueta, día actual y nota operativa. El cambio manual requiere motivo y permiso `plan_content.manage`.

## Avisos

Crear avisos con severidad `INFO`, `WARNING` o `URGENT`, vigencia y visibilidad. El admin debe ver una vista previa antes de publicar.

## Vista previa

El recurso debe ofrecer una acción de previsualización con:

- Hero.
- Avance.
- Día actual.
- Calendario.
- Actividades.
- Staff.
- Avisos.
- Estado de cupos.

## Contrato del portal

`GET /api/planes/activo` debe devolver información general, días, actividades, avance y avisos publicados. El frontend no debe calcular estado ni porcentaje con datos locales.
