# Implementación de Excerpts Dinámicos - NotiGolfo

## Resumen de cambios
Se ha implementado un sistema de excerpts dinámicos que **reemplaza completamente** el texto genérico de relleno en la página principal con resúmenes reales y relevantes de cada noticia.

---

## 1. Función Generadora de Excerpts
**Archivo:** `includes/markdown_parser.php`

Se agregó la función `generateExcerpt()` que implementa una jerarquía de prioridades:

```php
function generateExcerpt($post, $length = 130)
```

**Lógica:**
1. **Primera opción:** Si existe campo `excerpt` en el frontmatter → usa ese valor
2. **Segunda opción:** Si existe campo `description` en el frontmatter → usa ese valor
3. **Tercera opción (Fallback automático):** 
   - Toma los primeros N caracteres del contenido (`$post['content']`)
   - Elimina etiquetas HTML/Markdown con `strip_tags()`
   - Limpia espacios y saltos de línea
   - Trunca en la última palabra completa
   - Añade "..." al final
4. **Fallback final:** Texto genérico "Noticia sin descripción disponible."

**Parámetros:**
- `$post`: Array con datos de la noticia (título, contenido, etc.)
- `$length`: Longitud máxima en caracteres (default: 130)

---

## 2. Actualización del Indexer
**Archivo:** `includes/indexer.php`

**Cambio:** Se agregó línea para incluir el contenido del markdown en el índice JSON:
```php
$parsed['meta']['content'] = $parsed['content'];
```

**Razón:** El contenido se necesita para generar excerpts automáticos cuando no existe campo explícito.

**Impacto:** `content/posts_index.json` ahora incluye el contenido completo de cada nota.

---

## 3. Actualización de index.php
**Ubicación:** Sección de notas principales (feat. posts carousel)

**Cambio:**
```php
// ANTES:
<p class="text-gray-600 line-clamp-3">
    Esta es una breve descripción o extracto de la noticia. Haz clic para leer el contenido completo de este acontecimiento que marca tendencia en la región y el mundo.
</p>

// DESPUÉS:
<p class="text-gray-600 line-clamp-3">
    <?= generateExcerpt($mainPost, 150) ?>
</p>
```

**Nota:** Se usa `line-clamp-3` que trunca visualmente a 3 líneas en CSS, complementando el truncamiento de PHP.

---

## 4. Actualización de process_post.php
**Archivo:** `admin/process_post.php`

**Cambios:**

a) **Capturación del campo excerpt** (línea ~14):
```php
$excerpt = trim($_POST['excerpt'] ?? '');  // Nuevo
```

b) **Inclusión en el frontmatter YAML**:
```php
// Si se proporcionó un excerpt explícito, lo guarda en el markdown
if (!empty($excerpt)) {
    $markdown .= "excerpt: \"{$excerpt}\"\n";
}
```

**Ventaja:** Los editores pueden opcionalmente definir un resumen personalizado en el formulario de edición.

---

## Uso en el CMS

### Para editores/redactores:

**Opción 1: Excerpt Explícito (Recomendado)**
1. En el formulario de edición de notas, buscar campo **"Resumen/Excerpt"**
2. Escribir un resumen de 1-2 oraciones (120-150 caracteres máximo)
3. Guardar la nota

**Opción 2: Automático (Sin hacer nada)**
- Si no se define excerpt, el sistema toma automáticamente los primeros 150 caracteres del contenido
- Se limpia de etiquetas markdown/html automáticamente

### Jerarquía de visualización en la portada:

```
┌─────────────────────────────────────┐
│ ¿Existe 'excerpt' en frontmatter?   │
│         SÍ → Usa ese valor          │
└─────────────────────────────────────┘
                  │ NO
                  ▼
┌─────────────────────────────────────┐
│ ¿Existe 'description' en frontmatter?│
│         SÍ → Usa ese valor          │
└─────────────────────────────────────┘
                  │ NO
                  ▼
┌─────────────────────────────────────┐
│  Genera automáticamente del contenido│
│  (Primeros 150 caracteres limpios)  │
└─────────────────────────────────────┘
```

---

## Ejemplo de Frontmatter Completo

```yaml
---
title: "Tensión en Tuxpan: Crisis de agua afecta comercios locales"
date: "2026-05-20T14:30:00-05:00"
category: "local"
author: "Juan Pérez"
author_id: "1"
featured_image: "2026/05/tuxpan-crisis.jpg"
featured: true
excerpt: "Comerciantes de Tuxpan reportan pérdidas significativas por falta de agua potable en el municipio. Autoridades municipales prometen solución en 48 horas."
---

[Contenido del artículo aquí...]
```

---

## Beneficios

✅ **Dinámico:** Cada noticia muestra su propio resumen, no texto genérico  
✅ **Flexible:** Soporta 3 modos (explícito, fallback automático, genérico)  
✅ **SEO-friendly:** Los excerpts son indexables y relevantes  
✅ **Limpio:** Elimina automáticamente markdown/HTML  
✅ **Responsive:** Se ajusta a diferentes longitudes de pantalla  
✅ **Sin cambios en edición:** Funciona con posts existentes instantáneamente  

---

## Mantenimiento

### Si quieres regenerar todos los índices:
```bash
# Acceder al editor de notas e ir a cualquier noticia
# El sistema automáticamente reconstruye el índice al guardar
```

### Ajustar longitud del excerpt:
En `index.php`, cambiar el segundo parámetro:
```php
<?= generateExcerpt($mainPost, 120) ?>  // 120 caracteres
<?= generateExcerpt($mainPost, 200) ?>  // 200 caracteres
```

---

## Notas técnicas

- La función `generateExcerpt()` está en `includes/markdown_parser.php`
- El contenido se guarda en `content/posts_index.json` (puede crecer en tamaño)
- Los excerpts se sanitizan con `htmlspecialchars()` por seguridad
- Compatible con todas las versiones de PHP 7.2+

