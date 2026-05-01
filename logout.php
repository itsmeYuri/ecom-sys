<?php
require_once __DIR__ . '/includes/functions.php';

$timeout = !empty($_GET['timeout']);

if (!empty($_SESSION['admin'])) {
    audit_log('admin', (int)$_SESSION['admin']['id'], $_SESSION['admin']['username'], $timeout ? 'ADMIN_SESSION_TIMEOUT' : 'ADMIN_LOGOUT', $timeout ? 'Session expired' : 'Logged out');
    unset($_SESSION['admin']);
} elseif (!empty($_SESSION['employee'])) {
    audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], $timeout ? 'EMPLOYEE_SESSION_TIMEOUT' : 'EMPLOYEE_LOGOUT', $timeout ? 'Session expired' : 'Logged out');
    unset($_SESSION['employee']);
} elseif (!empty($_SESSION['user'])) {
    audit_log('user', (int)$_SESSION['user']['id'], $_SESSION['user']['email'], $timeout ? 'USER_SESSION_TIMEOUT' : 'USER_LOGOUT', $timeout ? 'Session expired' : 'Logged out');
    unset($_SESSION['user']);
}

session_destroy();
header('Location: ' . BASE_URL . '/login.php' . ($timeout ? '?timeout=1' : ''));
exit;
