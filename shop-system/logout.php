<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['user']);
header('Location: ' . BASE_URL . '/login.php');
exit;
