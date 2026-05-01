<?php
ob_start();
require_once __DIR__ . '/includes/functions.php';
$stmt = db()->prepare('SELECT image_data, image_mime FROM hero_settings WHERE id=1 LIMIT 1');
$stmt->execute();
$row = $stmt->fetch();
if (!$row || empty($row['image_data'])) {
    ob_end_clean();
    http_response_code(404);
    exit('No hero image');
}
ob_end_clean();
header('Content-Type: ' . ($row['image_mime'] ?: 'image/jpeg'));
header('Cache-Control: public, max-age=300');
header('Content-Length: ' . strlen($row['image_data']));
echo $row['image_data'];
