<?php
require_once __DIR__ . '/markdown_parser.php';

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
