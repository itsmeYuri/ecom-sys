<?php
require_once __DIR__ . '/../config.php';
unset($_SESSION['admin']);
$returnTo = urlencode(BASE_URL . '/admin/index.php');
header('Location: ' . BASE_URL . '/login.php?return_to=' . $returnTo, true, 302);
exit;
