<?php
require_once __DIR__ . '/includes/functions.php';

$imageId = (int)($_GET['id'] ?? 0);
if ($imageId < 1) {
    http_response_code(404);
    exit('Image not found');
}

$stmt = db()->prepare('SELECT image_data, image_mime FROM product_images WHERE id = ? LIMIT 1');
$stmt->execute([$imageId]);
$image = $stmt->fetch();

if (!$image || empty($image['image_data'])) {
    http_response_code(404);
    exit('Image not found');
}

$mime = $image['image_mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800');
echo $image['image_data'];
