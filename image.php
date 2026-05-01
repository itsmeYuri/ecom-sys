<?php
ob_start();
require_once __DIR__ . '/includes/functions.php';

$imageId = (int)($_GET['id'] ?? 0);
if ($imageId < 1) {
    ob_end_clean();
    http_response_code(404);
    exit('Image not found');
}

$stmt = db()->prepare('SELECT image_data, image_mime FROM product_images WHERE id = ? LIMIT 1');
$stmt->execute([$imageId]);
$image = $stmt->fetch();

if (!$image || empty($image['image_data'])) {
    ob_end_clean();
    http_response_code(404);
    exit('Image not found');
}

ob_end_clean();
$mime = $image['image_mime'] ?: 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . strlen($image['image_data']));
echo $image['image_data'];
