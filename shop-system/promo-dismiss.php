<?php
require_once __DIR__ . '/config.php';
$_SESSION['promo_dismissed'] = true;
http_response_code(204);
