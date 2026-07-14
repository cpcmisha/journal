# ADR-0003: Formatos abiertos y libertad de salida

**Estado:** Aceptado  
**Fecha:** 2026-07-12

## Contexto

Los usuarios deben poder conservar y trasladar sus notas aunque dejen de usar Misha Journal.

## Decisión

Misha Journal utilizará formatos abiertos y ofrecerá exportación completa.

Markdown será uno de los formatos principales, pero el editor no obligará al usuario a conocerlo.

## Consecuencias

- No se utilizará un formato propietario como única representación.
- Toda evolución del modelo de datos deberá contemplar exportación.
- Las migraciones deberán conservar el contenido existente.
