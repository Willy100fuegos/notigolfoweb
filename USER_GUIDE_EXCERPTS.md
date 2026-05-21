# Guía Práctica: Usando el Sistema de Excerpts

## Para Editores y Redactores

### ¿Qué es un Excerpt?
Es un resumen corto (1-2 oraciones) que aparece en la portada de NotiGolfo debajo del título de cada noticia destacada.

### Escenarios de Uso

#### Escenario 1: Dejar que el Sistema Genere el Excerpt Automáticamente
**Pasos:**
1. Abre el editor de notas
2. Escribe el título
3. Selecciona categoría
4. **DEJA VACÍO el campo "Resumen/Excerpt"**
5. Escribe tu contenido normalmente
6. Haz clic en "Publicar"

**Resultado:** El sistema tomará automáticamente los primeros 150 caracteres del contenido, sin etiquetas de markdown, y los mostrará como excerpt.

**Ventaja:** Cero trabajo adicional, funciona en todos los casos

---

#### Escenario 2: Definir un Excerpt Personalizado (Recomendado)
**Pasos:**
1. Abre el editor de notas
2. Escribe el título
3. Selecciona categoría
4. **En el campo "Resumen/Excerpt", escribe 1-2 oraciones** (máximo 150 caracteres)
5. Escribe el contenido
6. Publica la noticia

**Ejemplo de Excerpt Personalizado:**
```
"Autoridades reportan que 50 comercios han cerrado por falta de agua. 
Se espera solución en 48 horas."
```

**Ventaja:** Control total sobre el resumen que ven los lectores

---

#### Escenario 3: Editar una Noticia Existente
**Pasos:**
1. En el panel de admin, busca la noticia en la tabla
2. Haz clic en el ícono de editar (lápiz)
3. El campo "Resumen/Excerpt" mostrará el valor actual (si existe)
4. Modifica si lo deseas
5. Haz clic en "Actualizar Noticia"

---

### Consejos para Escribir Buenos Excerpts

✅ **Bueno:**
- "Crisis de agua afecta comercios en Tuxpan."
- "Gobierno federal destina millones para infraestructura del puerto."

❌ **Evitar:**
- "La noticia..."
- Resúmenes que son más largos que el título
- Palabras truncadas o incompletas

### Longitud Ideal
- **Mínimo:** 30-40 caracteres
- **Ideal:** 80-120 caracteres
- **Máximo:** 150 caracteres (el sistema trunca aquí)

---

## Para Administradores

### Monitorear Excerpts Automáticos
Si deseas ver cuáles notas tienen excerpts personalizados vs automáticos:

1. **Ver archivo de índice:** `content/posts_index.json`
2. **Busca el campo `"excerpt"`:**
   - Si existe y tiene valor: excerpt personalizado
   - Si no existe: se genera automáticamente

### Regenerar Todos los Índices
Si algo falla:
1. Abre cualquier noticia en el editor
2. Haz clic en "Actualizar Noticia" (sin cambiar nada)
3. El sistema reconstruye automáticamente el índice JSON

### Verificar en Portada
1. Abre `http://tudominio/index.php`
2. Verifica que los excerpts debajo de títulos principales sean relevantes
3. Si ves "Noticia sin descripción disponible." hay un problema

---

## Ejemplos de Posts Completos

### Ejemplo 1: Con Excerpt Personalizado
```yaml
---
title: "Tuxpan: Crisis de agua deja sin servicio 50 comercios"
date: "2026-05-20T14:30:00-05:00"
category: "local"
author: "Juan Pérez"
excerpt: "Comerciantes reportan pérdidas por corte de agua que afecta al municipio desde hace 36 horas."
featured_image: "2026/05/tuxpan-crisis.jpg"
featured: true
---

Más de 50 comercios en Tuxpan reportan pérdidas significativas...
```

**Resultado en portada:**
> "Comerciantes reportan pérdidas por corte de agua que afecta al municipio desde hace 36 horas."

---

### Ejemplo 2: Sin Excerpt Personalizado (Auto)
```yaml
---
title: "Inaugura Tampico nuevo puente turístico"
date: "2026-05-20T10:00:00-05:00"
category: "local"
author: "María García"
featured_image: "2026/05/puente-tampico.jpg"
featured: true
---

Se inaugura el nuevo puente turístico que conecta el centro histórico...
```

**Resultado en portada (automático):**
> "Se inaugura el nuevo puente turístico que conecta el centro histórico..."

(Los primeros ~150 caracteres del contenido)

---

## Troubleshooting

### Problema: Veo "Noticia sin descripción disponible."
**Causa:** Post sin contenido, sin excerpt, sin description
**Solución:** 
1. Edita la nota
2. Agrega contenido en el editor
3. Guarda nuevamente

### Problema: El excerpt mostraba texto diferente después de editar
**Causa:** Normal, si cambió el contenido
**Solución:** 
1. Define un excerpt personalizado si quieres que sea fijo
2. O deja vacío para que se actualice automáticamente

### Problema: Los primeros caracteres del excerpt parecen mal truncados
**Causa:** Markdown no se limpió bien
**Solución:**
1. Define un excerpt personalizado sin markdown
2. O comienza el contenido con texto sin etiquetas

---

## Preguntas Frecuentes

**P: ¿Si cambio el excerpt, se actualiza en la portada?**
R: Sí, inmediatamente al guardar la noticia.

**P: ¿Puedo usar HTML en el excerpt?**
R: No recomendado. El sistema sanitiza. Usa texto plano.

**P: ¿Qué pasa si el excerpt es muy corto?**
R: Se muestra completamente (no hay mínimo, pero <30 caracteres no es ideal).

**P: ¿Funciona con posts antiguos?**
R: Sí. Los posts antiguos usan auto-generation. Para actualizarlos, edita y guarda.

**P: ¿Puedo cambiar la longitud máxima?**
R: Sí, en `index.php` cambia el segundo parámetro: `generateExcerpt($mainPost, 200)` para 200 caracteres.

---

## Contacto / Soporte
Para preguntas técnicas, ver [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)

