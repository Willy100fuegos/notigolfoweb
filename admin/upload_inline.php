<?php
require_once '../includes/auth.php';
require_once '../includes/image_optimizer.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $year = date('Y');
    $month = date('m');
    $imagesDir = "../content/images/{$year}/{$month}/";
    
    if (!is_dir($imagesDir)) {
        mkdir($imagesDir, 0755, true);
    }

    try {
        $uploadedName = optimizeAndSaveImage($_FILES['file'], $imagesDir);
        if ($uploadedName) {
            $imageUrl = "/content/images/{$year}/{$month}/{$uploadedName}";
            echo json_encode(['success' => true, 'url' => $imageUrl]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al optimizar imagen.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
}
