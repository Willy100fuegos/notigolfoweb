<?php
/**
 * Optimizador de imágenes para NotiGolfo.
 * Redimensiona a 600px de ancho (manteniendo proporción) y convierte a WebP.
 * 
 * @param array $fileArray El array $_FILES['image']
 * @param string $uploadDir Directorio de destino
 * @return string|false Nombre del archivo generado o false si hubo error
 */
function optimizeAndSaveImage($fileArray, $uploadDir = '../content/images/') {
    // Validar errores de subida
    if (!isset($fileArray['error']) || is_array($fileArray['error']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validar MIME type (usamos getimagesize porque fileinfo parece no estar activo en el servidor)
    $imageInfo = @getimagesize($fileArray['tmp_name']);
    if (!$imageInfo || !isset($imageInfo['mime'])) {
        return false;
    }
    $mimeType = $imageInfo['mime'];
    
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        return false;
    }

    // Obtener dimensiones originales
    list($width, $height) = getimagesize($fileArray['tmp_name']);
    if (!$width || !$height) {
        return false;
    }

    // Cargar imagen según su tipo
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($fileArray['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($fileArray['tmp_name']);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($fileArray['tmp_name']);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($fileArray['tmp_name']);
            break;
        default:
            return false;
    }

    if (!$image) return false;

    // Redimensionar a 600px de ancho (manteniendo proporción)
    $newWidth = 600;
    $newHeight = (int)($height * ($newWidth / $width));

    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preservar transparencia para PNG/GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Asegurar que el directorio exista
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generar nombre de archivo único
    $filename = uniqid('img_') . '.webp';
    $destination = rtrim($uploadDir, '/') . '/' . $filename;

    // Guardar como WebP con calidad del 80%
    // Esto generalmente arroja un peso entre 80kb y 250kb para una imagen de 600px de ancho
    $quality = 80;
    imagewebp($resizedImage, $destination, $quality);

    // Limpiar memoria
    imagedestroy($image);
    imagedestroy($resizedImage);

    return $filename;
}
?>
