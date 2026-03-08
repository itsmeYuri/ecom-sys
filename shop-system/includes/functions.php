<?php
require_once __DIR__ . '/db.php';

function ensure_products_sold_column(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = db()->query("SHOW COLUMNS FROM products LIKE 'is_sold'");
        $exists = $stmt->fetch();
        if (!$exists) {
            db()->exec("ALTER TABLE products ADD COLUMN is_sold TINYINT(1) NOT NULL DEFAULT 0 AFTER is_popular");
        }
    } catch (Throwable $e) {
        // Keep app running even if schema migration is blocked.
    }
}

ensure_products_sold_column();

function ensure_product_images_blob_storage(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $cols = db()->query("SHOW COLUMNS FROM product_images")->fetchAll();
        $names = array_map(static fn($c) => $c['Field'], $cols);

        if (!in_array('image_data', $names, true)) {
            db()->exec("ALTER TABLE product_images ADD COLUMN image_data LONGBLOB NULL AFTER product_id");
        }
        if (!in_array('image_mime', $names, true)) {
            db()->exec("ALTER TABLE product_images ADD COLUMN image_mime VARCHAR(100) NULL AFTER image_data");
        }
        if (!in_array('image_name', $names, true)) {
            db()->exec("ALTER TABLE product_images ADD COLUMN image_name VARCHAR(255) NULL AFTER image_mime");
        }
        if (in_array('image_path', $names, true)) {
            $rows = db()->query("SELECT id, image_path FROM product_images WHERE image_data IS NULL AND image_path IS NOT NULL AND image_path <> ''")->fetchAll();
            $update = db()->prepare("UPDATE product_images SET image_data = ?, image_mime = ?, image_name = ?, image_path = NULL WHERE id = ?");

            foreach ($rows as $row) {
                $urlPath = (string)$row['image_path'];
                $cleanPath = str_replace(BASE_URL, '', $urlPath);
                $fullPath = realpath(__DIR__ . '/..' . $cleanPath);
                if (!$fullPath || !is_file($fullPath)) {
                    continue;
                }

                $blob = @file_get_contents($fullPath);
                if ($blob === false) {
                    continue;
                }

                $mime = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: 'application/octet-stream') : 'application/octet-stream';
                $name = basename($fullPath);
                $update->execute([$blob, $mime, $name, (int)$row['id']]);
            }
        }
    } catch (Throwable $e) {
        // Keep app running even if migration cannot execute.
    }
}

ensure_product_images_blob_storage();

function ensure_auth_otp_schema(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $userCols = db()->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->fetch();
        if (!$userCols) {
            db()->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
        }

        $adminEmailCol = db()->query("SHOW COLUMNS FROM admin LIKE 'email'")->fetch();
        if (!$adminEmailCol) {
            db()->exec("ALTER TABLE admin ADD COLUMN email VARCHAR(150) UNIQUE NULL AFTER id");
        }

        // Seed default admin email for OTP if missing.
        db()->exec("UPDATE admin SET email = 'admin@example.com' WHERE username = 'admin' AND (email IS NULL OR email = '')");

        db()->exec("
            CREATE TABLE IF NOT EXISTS otp_codes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('user','admin','employee') NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                purpose ENUM('login','email_verify') NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_otp_lookup (entity_type, entity_id, purpose, consumed_at, expires_at)
            )
        ");

        db()->exec("
            CREATE TABLE IF NOT EXISTS employees (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(120) NOT NULL,
                username VARCHAR(80) NOT NULL UNIQUE,
                email VARCHAR(150) UNIQUE NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Ensure otp enum includes employee on existing databases.
        db()->exec("ALTER TABLE otp_codes MODIFY entity_type ENUM('user','admin','employee') NOT NULL");
    } catch (Throwable $e) {
        // Continue app flow even if schema update fails.
    }
}

ensure_auth_otp_schema();

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/homepage.php'));
        header('Location: ' . BASE_URL . '/login.php?return_to=' . $returnTo);
        exit;
    }
}

function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin']);
}

function is_employee_logged_in(): bool {
    return !empty($_SESSION['employee']);
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/admin/index.php'));
        header('Location: ' . BASE_URL . '/login.php?return_to=' . $returnTo);
        exit;
    }
}

function require_employee(): void {
    if (!is_employee_logged_in()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/employee/index.php'));
        header('Location: ' . BASE_URL . '/login.php?return_to=' . $returnTo);
        exit;
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function cart_items_count(): int {
    if (!is_logged_in()) {
        return 0;
    }
    return array_sum($_SESSION['cart'] ?? []);
}

function get_categories(): array {
    return db()->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();
}

function get_product_main_image(int $productId): ?string {
    $stmt = db()->prepare('SELECT id FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC LIMIT 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return !empty($row['id']) ? (BASE_URL . '/image.php?id=' . (int)$row['id']) : null;
}

function image_url(?int $imageId, string $fallback = ''): string {
    if (!empty($imageId)) {
        return BASE_URL . '/image.php?id=' . (int)$imageId;
    }
    return $fallback;
}

function get_cart_products(): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT p.*, (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_id
            FROM products p
            WHERE p.id IN ($placeholders)";

    $stmt = db()->prepare($sql);
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as &$p) {
        $p['quantity'] = (int)($cart[$p['id']] ?? 1);
        $p['line_total'] = $p['quantity'] * (float)$p['price'];
    }

    return $products;
}

function calculate_cart_totals(array $products): array {
    $subtotal = 0;
    foreach ($products as $p) {
        $subtotal += (float)$p['line_total'];
    }

    $discount = $subtotal * 0.20;
    $delivery = $subtotal > 0 ? 15.00 : 0.00;
    $total = max(0, $subtotal - $discount + $delivery);

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'delivery' => $delivery,
        'total' => $total,
    ];
}

function render_stars(float $rating): string {
    $full = (int)round($rating);
    $full = max(0, min(5, $full));
    return str_repeat('â˜…', $full) . str_repeat('â˜†', 5 - $full);
}

function generate_otp_code(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function create_otp_code(string $entityType, int $entityId, string $purpose, int $ttlMinutes = 10): string {
    $otp = generate_otp_code();
    $hash = password_hash($otp, PASSWORD_DEFAULT);

    $cleanup = db()->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE entity_type = ? AND entity_id = ? AND purpose = ? AND consumed_at IS NULL");
    $cleanup->execute([$entityType, $entityId, $purpose]);

    $stmt = db()->prepare("INSERT INTO otp_codes (entity_type, entity_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
    $stmt->execute([$entityType, $entityId, $purpose, $hash, $ttlMinutes]);

    return $otp;
}

function verify_otp_code(string $entityType, int $entityId, string $purpose, string $otp): bool {
    $stmt = db()->prepare("SELECT * FROM otp_codes WHERE entity_type = ? AND entity_id = ? AND purpose = ? AND consumed_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$entityType, $entityId, $purpose]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    if (!password_verify($otp, $row['code_hash'])) {
        return false;
    }

    $consume = db()->prepare("UPDATE otp_codes SET consumed_at = NOW() WHERE id = ?");
    $consume->execute([(int)$row['id']]);
    return true;
}

function send_otp_notice(?string $email, string $otp, string $purpose): void {
    $subject = 'Your Threap Glailz OTP Code';
    $safePurpose = strtoupper(str_replace('_', ' ', $purpose));
    $html = '<p>Your OTP for <strong>' . e($safePurpose) . '</strong> is:</p>'
        . '<h2 style="letter-spacing:4px;margin:8px 0;">' . e($otp) . '</h2>'
        . '<p>This code expires in 10 minutes.</p>';
    $text = "Your OTP for {$safePurpose} is: {$otp}. This code expires in 10 minutes.";

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sent = send_email_smtp($email, $subject, $html, $text);
        if ($sent) {
            flash('success', 'OTP has been sent to your email.');
            return;
        }
    }

    // Fallback for local dev without SMTP setup.
    flash('warning', 'SMTP not configured. OTP Code (dev): ' . $otp);
}

function send_email_smtp(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $phpMailerPath = __DIR__ . '/../assets/PHPMailer/src';
    $exceptionFile = $phpMailerPath . '/Exception.php';
    $phpmailerFile = $phpMailerPath . '/PHPMailer.php';
    $smtpFile = $phpMailerPath . '/SMTP.php';

    if (!is_file($exceptionFile) || !is_file($phpmailerFile) || !is_file($smtpFile)) {
        return false;
    }

    require_once $exceptionFile;
    require_once $phpmailerFile;
    require_once $smtpFile;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;

        if (strtolower((string)SMTP_ENCRYPTION) === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

