<?php
require_once __DIR__ . '/markdown_parser.php';

/**
 * Genera un excerpt a partir del contenido del post
 * Se ejecuta en TIEMPO DE INDEXACIÓN para optimizar la lectura
 * 
 * @param string $content Contenido markdown sin frontmatter
 * @param string $excerpt_meta Valor del campo excerpt del frontmatter (si existe)
 * @param string $description_meta Valor del campo description del frontmatter (si existe)
 * @param int $length Longitud máxima en caracteres
 * @return string Excerpt limpio o vacío
 */
function generateExcerptAtIndex($content, $excerpt_meta = '', $description_meta = '', $length = 230) {
    // 1. Preferencia: campo excerpt del frontmatter
    if (!empty($excerpt_meta)) {
        return trim($excerpt_meta);
    }
    
    // 2. Alternativa: campo description del frontmatter
    if (!empty($description_meta)) {
        return trim($description_meta);
    }
    
    // 3. Auto-generar desde contenido
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
        return $excerpt;
    }
    
    // 4. Fallback: vacío
    return '';
}

function rebuildPostsIndex() {
    $dir = __DIR__ . '/../content/posts/';
    $posts = [];
    
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $content = file_get_contents($file->getPathname());
                $parsed = parseFrontmatter($content);
                if (!empty($parsed['meta']['title'])) {
                    $relPath = str_replace([realpath($dir), '.md', '\\'], ['', '', '/'], $file->getRealPath());
                    $parsed['meta']['filename'] = trim($relPath, '/');
                    
                    // OPTIMIZACIÓN: Generar excerpt en tiempo de indexación
                    $excerpt_meta = $parsed['meta']['excerpt'] ?? '';
                    $description_meta = $parsed['meta']['description'] ?? '';
                    $parsed['meta']['excerpt'] = generateExcerptAtIndex(
                        $parsed['content'],
                        $excerpt_meta,
                        $description_meta,
                        230
                    );
                    
                    // NO guardar el contenido completo (ahorra espacio en JSON)
                    // Solo guardamos el excerpt ya procesado
                    
                    $posts[] = $parsed['meta'];
                }
            }
        }
    }
    
    usort($posts, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    file_put_contents(__DIR__ . '/../content/posts_index.json', json_encode($posts, JSON_PRETTY_PRINT));
}

function getPostsIndex() {
    $indexFile = __DIR__ . '/../content/posts_index.json';
    if (!file_exists($indexFile)) {
        rebuildPostsIndex();
    }
    return json_decode(file_get_contents($indexFile), true) ?: [];
}

