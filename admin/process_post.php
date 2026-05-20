<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Mexico_City');
require_once '../includes/auth.php';
require_once '../includes/image_optimizer.php';
require_once '../includes/markdown_parser.php';
require_once '../includes/indexer.php';
require_once '../includes/seo_generator.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Error de validación de seguridad (CSRF).");
    }

    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $isFeatured = isset($_POST['is_featured']) ? 'true' : 'false';
    $content = trim($_POST['content']);

    $editFile = $_POST['edit_file'] ?? '';
    $author_id = $_SESSION['user_id'] ?? null;
    $author = trim($_POST['author'] ?? '');

    // Permisos de edición y preservación de author_id
    if ($editFile) {
        $checkpath = "../content/posts/{$editFile}.md";
        if (file_exists($checkpath)) {
            $raw = file_get_contents($checkpath);
            $parsed = parseFrontmatter($raw);
            $existing_author_id = $parsed['meta']['author_id'] ?? null;
            
            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') {
                if ($existing_author_id != $_SESSION['user_id']) {
                    die("Acceso denegado: No tienes permisos para editar esta nota.");
                }
            }
            if ($existing_author_id) {
                $author_id = $existing_author_id;
            }
        }
    }

    // Lógica dinámica de autoría
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') {
        $author = $_SESSION['display_name'] ?? $_SESSION['username'];
    } else {
        if (empty($author)) {
            $author = 'Redacción NotiGolfo';
        }
    }
    // Manejo de fecha personalizada
    $inputDate = trim($_POST['custom_date'] ?? '');
    if (!empty($inputDate)) {
        $timestamp = strtotime($inputDate);
        if ($timestamp === false) $timestamp = time();
    } else {
        $timestamp = time();
    }
    
    $year = date('Y', $timestamp);
    $month = date('m', $timestamp);
    $dateIso = date('c', $timestamp);
    $dateStr = date('Y-m-d', $timestamp);
    
    // Rutas cronológicas
    $postsDir = "../content/posts/{$year}/{$month}/";
    $imagesDir = "../content/images/{$year}/{$month}/";
    
    if (!is_dir($postsDir)) mkdir($postsDir, 0755, true);
    if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);

    $editFile = $_POST['edit_file'] ?? '';
    $currentImage = $_POST['current_image'] ?? '';

    // Si es un edit y no se sube imagen nueva, usamos la anterior
    $imageName = $currentImage;

    // Procesar Imagen si se subió una nueva
    $imageUrlInput = trim($_POST['image_url'] ?? '');
    
    if (!empty($imageUrlInput)) {
        $imageName = $imageUrlInput;
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        try {
            $uploadedName = optimizeAndSaveImage($_FILES['image'], $imagesDir);
            if ($uploadedName) {
                // Guardamos el path relativo cronológico en el frontmatter
                $imageName = "{$year}/{$month}/{$uploadedName}";
            } else {
                die("Error al optimizar y guardar la imagen.");
            }
        } catch (Exception $e) {
            die("Error procesando la imagen: " . $e->getMessage());
        }
    } elseif (!$editFile) {
        die("La imagen destacada es obligatoria (sube un archivo o ingresa una URL).");
    }

    // Generar filename base
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    if (strlen($slug) > 50) $slug = substr($slug, 0, 50);

    if ($editFile) {
        // Si editFile ya tiene formato YYYY/MM/archivo, hay que mantenerlo así o moverlo? 
        // Simplificación: si ya tiene slash, lo usamos, si no, lo guardamos en la nueva estructura.
        $filename = $editFile;
        // Si $filename incluye la ruta YYYY/MM/, aseguramos que el path funcione desde ../content/posts/
        $filepath = "../content/posts/{$filename}.md";
        // Asegurar que exista el directorio del edit
        $editDir = dirname($filepath);
        if (!is_dir($editDir)) mkdir($editDir, 0755, true);
    } else {
        $filename = "{$year}/{$month}/" . $dateStr . '-' . uniqid() . '-' . $slug;
        $filepath = "../content/posts/{$filename}.md";
    }

    // Construir YAML Frontmatter
    $markdown = "---\n";
    $markdown .= "title: \"{$title}\"\n";
    $markdown .= "date: \"{$dateIso}\"\n";
    $markdown .= "category: \"{$category}\"\n";
    $markdown .= "author: \"{$author}\"\n";
    $markdown .= "author_id: \"{$author_id}\"\n";
    $markdown .= "featured_image: \"{$imageName}\"\n";
    $markdown .= "featured: {$isFeatured}\n";
    $markdown .= "---\n\n";
    $markdown .= $content;

    // Guardar archivo
    if (file_put_contents($filepath, $markdown) !== false) {
        rebuildPostsIndex();
        generateSeoFiles();
        echo "<script>alert('¡Nota publicada correctamente!'); window.location.href='editor.php';</script>";
    } else {
        echo "Error al guardar el archivo Markdown.";
    }
} else {
    header("Location: editor.php");
    exit;
}
?>
