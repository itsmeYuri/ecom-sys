<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
flash('warning', 'Item/system management is assigned to employee role.');
header('Location: ' . BASE_URL . '/admin/accounts.php');
exit;
