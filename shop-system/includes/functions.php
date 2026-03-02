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

function require_admin(): void {
    if (!is_admin_logged_in()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/admin/index.php'));
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
    return array_sum($_SESSION['cart'] ?? []);
}

function get_categories(): array {
    return db()->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();
}

function get_product_main_image(int $productId): ?string {
    $stmt = db()->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC LIMIT 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return $row['image_path'] ?? null;
}

function get_cart_products(): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT p.*, (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_path
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

