# Misha Journal Blueprint

**Estado:** Borrador  
**Versión:** 0.1  
**Última actualización:** 2026-07-12

## 1. Propósito

Misha Journal es un espacio privado para escribir, recordar y conectar ideas, completamente integrado en Nextcloud.

Su objetivo principal es ayudar al usuario a escribir con regularidad, registrar pequeñas notas de trabajo o de su vida personal y recuperar esa información cuando vuelva a necesitarla.

Misha Journal no pretende reemplazar Obsidian, Logseq, Notion, Evernote ni otras plataformas complejas de gestión del conocimiento.

Busca resolver una necesidad más sencilla:

> Permitir que un usuario de Nextcloud escriba, recuerde y relacione ideas sin abandonar su propio servidor.

## 2. Primer usuario

El fundador es el primer usuario de Misha Journal.

El proyecto nace para resolver una necesidad real de uso diario:

- escribir un diario personal;
- registrar pequeñas notas;
- guardar ideas;
- organizar información mediante etiquetas;
- crear recordatorios;
- relacionar páginas y entradas;
- recuperar información meses o años después.

El proyecto será útil aunque solo lo utilice su fundador.

La adopción por parte de otros usuarios será bienvenida, pero no condicionará su continuidad.

## 3. Misión

Ayudar a cualquier usuario de Nextcloud a escribir, recordar y conectar ideas sin depender de plataformas externas.

## 4. Visión

Convertir Misha Journal en el espacio personal de escritura más sencillo, privado y útil del ecosistema Nextcloud.

## 5. Lema

> Write today. Remember forever.

En español:

> Escribe hoy. Recuerda siempre.

## 6. Pilares del producto

### 6.1 Escribir

Abrir la aplicación, escribir y continuar con el día.

La aplicación debe reducir al mínimo las interrupciones y decisiones innecesarias.

### 6.2 Recordar

La información debe poder recuperarse fácilmente mediante:

- búsqueda;
- etiquetas;
- fechas;
- favoritos;
- recordatorios;
- recuerdos históricos.

### 6.3 Conectar

Las ideas pueden relacionarse mediante:

- enlaces entre páginas;
- referencias a entradas;
- etiquetas;
- backlinks;
- archivos relacionados.

### 6.4 Mantener el hábito

Misha Journal debe ayudar a desarrollar el hábito de escribir sin generar ansiedad.

Los recordatorios deberán ser amables, configurables y opcionales.

### 6.5 Libertad

El usuario siempre podrá exportar toda su información y abandonar la aplicación sin perder sus datos.

## 7. Qué es Misha Journal

Misha Journal combina:

- diario personal;
- notas rápidas;
- páginas permanentes;
- recordatorios;
- etiquetas;
- favoritos;
- búsqueda;
- relaciones entre ideas.

## 8. Qué no es Misha Journal

Misha Journal no será:

- una copia de Obsidian;
- una copia de Logseq;
- una suite colaborativa como Notion;
- una plataforma externa;
- un producto condicionado a una suscripción;
- una herramienta que bloquee los datos del usuario.

## 9. Experiencia de escritura

La escritura es más importante que el formato.

La aplicación ofrecerá dos modos:

### Editor visual

Pensado para usuarios que prefieren una experiencia similar a Word, Google Docs o Notion.

Debe permitir:

- títulos;
- negrita;
- cursiva;
- listas;
- listas de tareas;
- enlaces;
- tablas;
- imágenes;
- emojis;
- citas;
- bloques destacados.

### Editor Markdown

Pensado para usuarios que prefieren trabajar directamente con Markdown.

Ambos modos deberán representar el mismo contenido.

## 10. Navegación prevista

La estructura principal será:

- Inicio
- Hoy
- Diario
- Páginas
- Etiquetas
- Favoritos
- Recordatorios
- Buscar
- Configuración

La navegación deberá mantenerse sencilla incluso cuando aumenten las funciones.

## 11. Formatos abiertos

Misha Journal priorizará formatos abiertos y ampliamente soportados.

Los usuarios deberán poder exportar su información como mínimo en:

- Markdown;
- HTML;
- PDF;
- JSON estructurado.

En el futuro se evaluará el almacenamiento directo o sincronizado mediante archivos Markdown dentro de Nextcloud.

## 12. Privacidad

Misha Journal funcionará dentro de la instancia Nextcloud del usuario.

Por defecto:

- no requerirá cuentas externas;
- no mostrará publicidad;
- no enviará telemetría;
- no venderá datos;
- no dependerá de servicios externos.

## 13. Inteligencia artificial

La IA será opcional y nunca formará parte obligatoria del núcleo.

Podrán existir proveedores configurables como:

- Ollama;
- OpenAI;
- Anthropic;
- servicios compatibles;
- Misha API.

La aplicación principal deberá ser plenamente funcional sin IA.

## 14. Modelo comunitario

Misha Journal será gratuito y de código abierto bajo licencia AGPL.

El desarrollo podrá recibir apoyo mediante:

- donaciones;
- patrocinios;
- contribuciones;
- desarrollo patrocinado de mejoras compatibles con el proyecto.

No se limitarán funciones esenciales mediante una edición Premium.

## 15. Regla de validación

Toda nueva función deberá mejorar al menos una de estas experiencias:

1. escribir;
2. recordar;
3. conectar;
4. mantener el hábito.

Si no mejora ninguna, probablemente no pertenece al proyecto.

## 16. Regla de los 30 días

Una función grande no deberá implementarse únicamente porque suena interesante.

Debe cumplir al menos una condición:

- haber sido necesaria durante varias semanas de uso real;
- haber sido solicitada por usuarios;
- resolver un bloqueo claro del flujo principal;
- ser necesaria para mantener compatibilidad, seguridad o accesibilidad.

## 17. Roadmap funcional

### 0.1 — Genesis

- compatibilidad con Nextcloud 34;
- entradas diarias;
- Markdown;
- calendario;
- exportación PDF;
- exportación Markdown;
- primera identidad del proyecto.

### 0.2 — Writing

- editor visual;
- modo Markdown;
- emojis;
- listas de tareas;
- tablas;
- experiencia de escritura mejorada.

### 0.3 — Remember

- búsqueda;
- etiquetas;
- favoritos;
- recordatorios;
- hábitos de escritura.

### 0.4 — Connect

- páginas permanentes;
- enlaces entre páginas;
- backlinks;
- relaciones entre ideas.

### 0.5 — Timeline

- recuerdos históricos;
- “hace un año”;
- actividad de escritura;
- calendario enriquecido.

### 1.0 — Memory

Primera versión estable del espacio privado para escribir, recordar y conectar ideas dentro de Nextcloud.
