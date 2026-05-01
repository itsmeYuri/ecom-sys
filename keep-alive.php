<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf();
if (is_logged_in() || is_admin_logged_in() || is_employee_logged_in()) {
    $_SESSION['last_activity'] = time();
    http_response_code(204);
}
