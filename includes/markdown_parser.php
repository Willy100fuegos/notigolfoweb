<?php
function parseMarkdown($text) {
    // Headings
    $text = preg_replace('/^### (.*)/m', '<h3 class="text-xl font-bold mt-6 mb-3">$1</h3>', $text);
    $text = preg_replace('/^## (.*)/m', '<h2 class="text-2xl font-bold mt-8 mb-4">$1</h2>', $text);
    $text = preg_replace('/^# (.*)/m', '<h1 class="text-3xl font-bold mt-10 mb-6">$1</h1>', $text);
    
    // Bold & Italic
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\_(.*?)\_/', '<em>$1</em>', $text);
    
    // Imágenes ![alt](url)
    $text = preg_replace('/\!\[(.*?)\]\((.*?)\)/', '<div class="my-8"><img src="$2" alt="$1" class="w-full rounded-xl shadow-lg border border-gray-100 object-cover"></div>', $text);

    // Lists
    $text = preg_replace('/^\- (.*)/m', '<li class="ml-4 list-disc text-gray-700">$1</li>', $text);
    
    $lines = explode("\n", $text);
    $parsed = '';
    $inList = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Auto-Embed YouTube (Extracción limpia del ID)
        if (preg_match('/^(https?:\/\/(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})(?:[&\?]\S*)?)$/i', $line, $matches)) {
            if ($inList) { $parsed .= "</ul>\n"; $inList = false; }
            $parsed .= '<div class="aspect-video w-full my-6 shadow-md rounded-xl overflow-hidden"><iframe class="w-full h-full" src="https://www.youtube.com/embed/' . $matches[2] . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' . "\n";
            continue;
        }

        // Auto-Embed Facebook (Expandido: share, reels, videos, watch)
        // Usamos una regex más permisiva para capturar la URL completa de FB
        if (preg_match('/^(https?:\/\/(?:www\.)?facebook\.com\/(?:[^\/]+\/videos\/|video\.php\?v=|watch\/\?v=|share\/v\/|reels\/)[a-zA-Z0-9_-]+\/?(?:\?\S*)?)$/i', $line, $matches) || preg_match('/^(https?:\/\/(?:www\.)?fb\.watch\/[a-zA-Z0-9_-]+\/?(?:\?\S*)?)$/i', $line, $matches)) {
            if ($inList) { $parsed .= "</ul>\n"; $inList = false; }
            $fbUrl = urlencode($matches[1]);
            $parsed .= '<div class="aspect-video w-full my-6 shadow-md rounded-xl overflow-hidden"><iframe class="w-full h-full" src="https://www.facebook.com/plugins/video.php?href=' . $fbUrl . '&show_text=false" frameborder="0" allowfullscreen allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe></div>' . "\n";
            continue;
        }

        // Auto-Embed Twitter/X (Tarjeta Estilizada)
        if (preg_match('/^(https?:\/\/(?:www\.)?(?:twitter|x)\.com\/[a-zA-Z0-9_]+\/status\/[0-9]+(?:\?\S*)?)$/i', $line, $matches)) {
            if ($inList) { $parsed .= "</ul>\n"; $inList = false; }
            $parsed .= '<div class="my-6 border border-gray-200 rounded-xl p-6 bg-gray-50 shadow-sm flex flex-col items-center justify-center text-center">
                <i class="fa-brands fa-x-twitter text-3xl mb-3 text-gray-800"></i>
                <p class="text-gray-600 mb-4 font-medium">Ver la publicación original en X</p>
                <a href="' . htmlspecialchars($matches[1]) . '" target="_blank" class="bg-gray-900 text-white px-6 py-2 rounded-full hover:bg-gray-700 transition font-bold text-sm">Abrir enlace</a>
            </div>' . "\n";
            continue;
        }
        
        // Bloques de HTML Raw (<iframe, <h, <div, <img)
        if (strpos($line, '<h') === 0 || strpos($line, '<div') === 0 || strpos($line, '<img') === 0 || strpos($line, '<iframe') === 0) {
            if ($inList) { $parsed .= "</ul>\n"; $inList = false; }
            $parsed .= $line . "\n";
        } elseif (strpos($line, '<li') === 0) {
            if (!$inList) {
                $parsed .= "<ul class=\"mb-4\">\n";
                $inList = true;
            }
            $parsed .= $line . "\n";
        } else {
            if ($inList) {
                $parsed .= "</ul>\n";
                $inList = false;
            }
            // Tratar URLs solas que no fueron embebidas como links clickeables
            if (filter_var($line, FILTER_VALIDATE_URL)) {
                $parsed .= "<p class=\"mb-4 leading-relaxed\"><a href=\"$line\" target=\"_blank\" class=\"text-blue-600 hover:underline\">$line</a></p>\n";
            } else {
                $parsed .= "<p class=\"mb-4 leading-relaxed\">$line</p>\n";
            }
        }
    }
    
    if ($inList) {
        $parsed .= "</ul>\n";
    }
    
    return $parsed;
}

function parseFrontmatter($content) {
    $result = [
        'meta' => [],
        'content' => $content
    ];
    
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
        $yaml = $matches[1];
        $result['content'] = $matches[2];
        
        $lines = explode("\n", $yaml);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value, " \"'");
                $result['meta'][$key] = $value;
            }
        }
    }
    
    return $result;
}

/**
 * DEPRECADA: Genera un excerpt (resumen) dinámico de una noticia
 * 
 * ⚠️ NOTA: Esta función se manteniene solo por compatibilidad.
 * Los excerpts ahora se generan en TIEMPO DE INDEXACIÓN en indexer.php
 * para optimizar performance en tiempo de lectura.
 * 
 * @param array $post - Datos del post con 'excerpt', 'description', 'content'
 * @param int $length - Longitud máxima del excerpt (120-150 caracteres recomendado)
 * @return string - Excerpt limpio o texto por defecto
 */
function generateExcerpt($post, $length = 130) {
    // 1. Preferencia: campo excerpt del frontmatter
    if (!empty($post['excerpt'])) {
        return htmlspecialchars(trim($post['excerpt']));
    }
    
    // 2. Alternativa: campo description del frontmatter
    if (!empty($post['description'])) {
        return htmlspecialchars(trim($post['description']));
    }
    
    // 3. Fallback: primeros caracteres del contenido
    $content = $post['content'] ?? '';
    if (!empty($content)) {
        // Limpiar etiquetas markdown/html
        $cleanText = strip_tags($content);
        // Remover saltos de línea y espacios extras
        $cleanText = preg_replace('/\s+/', ' ', trim($cleanText));
        
        if (strlen($cleanText) > $length) {
            $excerpt = substr($cleanText, 0, $length);
            // Truncar en la última palabra completa
            $lastSpace = strrpos($excerpt, ' ');
            if ($lastSpace > 0) {
                $excerpt = substr($excerpt, 0, $lastSpace);
            }
            $excerpt .= '...';
        } else {
            $excerpt = $cleanText;
        }
        return htmlspecialchars($excerpt);
    }
    
    // 4. Fallback final: texto genérico
    return htmlspecialchars('Noticia sin descripción disponible.');
}
?>
