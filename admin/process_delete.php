<?php
session_start();
date_default_timezone_set('America/Mexico_City');
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/markdown_parser.php';
require_once '../includes/indexer.php';
require_once '../includes/seo_generator.php';

requireLogin();

$file = $_GET['file'] ?? '';

if (!$file) {
    die("Archivo no especificado.");
}

$filepath = "../content/posts/" . $file . ".md";

if (file_exists($filepath)) {
    // Verificar Permisos
    $raw = file_get_contents($filepath);
    $parsed = parseFrontmatter($raw);
    $author_id = $parsed['meta']['author_id'] ?? null;
    $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    
    if (!$is_admin && $author_id != $_SESSION['user_id']) {
        die("Acceso denegado: No tienes permisos para eliminar esta nota.");
    }
    
    // Eliminar archivo
    unlink($filepath);
    rebuildPostsIndex();
    generateSeoFiles();
    
    // Limpiar estadísticas en Redis
    try {
        $redis = new Redis();
        if ($redis->connect('127.0.0.1', 6379)) {
            $redis->del('visitas:' . $file);
        }
    } catch (Exception $e) {
        // Fallback a stats.json por si no usa Redis
        $statsFile = '../content/stats.json';
        if (file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true);
            if (isset($stats[$file])) {
                unset($stats[$file]);
                file_put_contents($statsFile, json_encode($stats));
            }
        }
    }
    
    header("Location: editor.php?msg=deleted");
} else {
    header("Location: editor.php?msg=notfound");
}
exit;
