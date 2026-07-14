# RFC-0001: Editor visual y Markdown

**Estado:** En evaluación  
**Fecha:** 2026-07-12

## Problema

El editor actual está basado en SimpleMDE y obliga al usuario a trabajar principalmente con Markdown.

Misha Journal debe atender tanto a usuarios visuales como a usuarios que prefieren Markdown.

## Objetivo

Proporcionar dos modos de edición sobre el mismo contenido:

- modo visual;
- modo Markdown.

## Requisitos

El editor deberá soportar:

- títulos;
- negrita y cursiva;
- listas;
- listas de tareas;
- enlaces;
- citas;
- tablas;
- emojis;
- imágenes;
- bloques destacados;
- atajos de teclado;
- accesibilidad;
- modo claro y oscuro;
- integración con Vue y Nextcloud.

## Alternativas por evaluar

### Tiptap

Ventajas:

- experiencia visual moderna;
- arquitectura extensible;
- comunidad activa;
- buena integración con Vue.

Riesgos:

- Markdown requiere conversión o extensiones;
- posible complejidad de sincronización entre modos.

### Milkdown

Ventajas:

- Markdown como núcleo;
- extensible;
- experiencia visual moderna.

Riesgos:

- integración más compleja;
- ecosistema más pequeño.

### Toast UI Editor

Ventajas:

- editor visual y Markdown integrados;
- funciones disponibles desde el inicio.

Riesgos:

- integración visual con Nextcloud;
- personalización y tamaño del paquete.

## Criterios de decisión

- mantenimiento activo;
- licencia compatible;
- accesibilidad;
- integración con Vue;
- conversión fiable a Markdown;
- tablas y checklists;
- compatibilidad con Nextcloud;
- tamaño y rendimiento;
- experiencia móvil.

## Decisión

Pendiente de una prueba técnica de las tres alternativas.
