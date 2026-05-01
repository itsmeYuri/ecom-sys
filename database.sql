-- ============================================================
-- DAWOUD NORMS – E-Commerce Database (MySQL / MariaDB)
-- Engine: InnoDB | Charset: utf8mb4
-- Import via: phpMyAdmin or mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS shop_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE shop_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS otp_codes;
DROP TABLE IF EXISTS activation_tokens;
DROP TABLE IF EXISTS totp_secrets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admin;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Users ──────────────────────────────────────────────────────────────────
CREATE TABLE users (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name           VARCHAR(120)    NOT NULL,
    email               VARCHAR(150)    DEFAULT NULL,
    phone               VARCHAR(25)     DEFAULT NULL,
    password_hash       VARCHAR(255)    NOT NULL,
    is_verified         TINYINT(1)      NOT NULL DEFAULT 0,
    is_locked           TINYINT(1)      NOT NULL DEFAULT 0,
    locked_at           DATETIME        DEFAULT NULL,
    locked_reason       VARCHAR(255)    DEFAULT NULL,
    password_changed_at DATETIME        DEFAULT NULL,
    totp_enabled        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email  (email),
    UNIQUE KEY uq_users_phone  (phone),
    INDEX idx_users_email      (email),
    INDEX idx_users_phone      (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Admin ──────────────────────────────────────────────────────────────────
CREATE TABLE admin (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    email         VARCHAR(150)    DEFAULT NULL,
    username      VARCHAR(80)     NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    totp_enabled  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username),
    UNIQUE KEY uq_admin_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Employees ──────────────────────────────────────────────────────────────
CREATE TABLE employees (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(120)    NOT NULL,
    username      VARCHAR(80)     NOT NULL,
    email         VARCHAR(150)    DEFAULT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    is_locked     TINYINT(1)      NOT NULL DEFAULT 0,
    locked_at     DATETIME        DEFAULT NULL,
    locked_reason VARCHAR(255)    DEFAULT NULL,
    totp_enabled  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_username (username),
    UNIQUE KEY uq_employees_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── OTP Codes ──────────────────────────────────────────────────────────────
CREATE TABLE otp_codes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('user','admin','employee') NOT NULL,
    entity_id   INT UNSIGNED    NOT NULL,
    purpose     ENUM('login','email_verify','totp_setup') NOT NULL,
    code_hash   VARCHAR(255)    NOT NULL,
    expires_at  DATETIME        NOT NULL,
    consumed_at DATETIME        DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_otp_lookup (entity_type, entity_id, purpose, consumed_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Email Activation Tokens ────────────────────────────────────────────────
CREATE TABLE activation_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token_hash  VARCHAR(255)    NOT NULL,
    expires_at  DATETIME        NOT NULL,
    consumed_at DATETIME        DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_activation_user (user_id),
    CONSTRAINT fk_activation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TOTP Secrets (Google Authenticator) ───────────────────────────────────
CREATE TABLE totp_secrets (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    entity_type ENUM('user','admin','employee') NOT NULL,
    entity_id   INT UNSIGNED    NOT NULL,
    secret      VARCHAR(128)    NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_totp (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Login Attempts ─────────────────────────────────────────────────────────
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type  ENUM('user','admin','employee') NOT NULL,
    identifier   VARCHAR(150)    NOT NULL,
    ip_address   VARCHAR(45)     NOT NULL,
    attempted_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success      TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_attempts_ident (entity_type, identifier, attempted_at),
    INDEX idx_attempts_ip    (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Audit Logs ─────────────────────────────────────────────────────────────
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('user','admin','employee','guest') NOT NULL DEFAULT 'guest',
    entity_id   INT UNSIGNED    DEFAULT NULL,
    username    VARCHAR(150)    DEFAULT NULL,
    action      VARCHAR(120)    NOT NULL,
    detail      TEXT            DEFAULT NULL,
    ip_address  VARCHAR(45)     DEFAULT NULL,
    user_agent  VARCHAR(512)    DEFAULT NULL,
    logged_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_audit_entity (entity_type, entity_id, logged_at),
    INDEX idx_audit_action (action, logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── System Settings ────────────────────────────────────────────────────────
CREATE TABLE system_settings (
    setting_key   VARCHAR(80)  NOT NULL,
    setting_value TEXT         NOT NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categories ─────────────────────────────────────────────────────────────
CREATE TABLE categories (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100)    NOT NULL,
    slug       VARCHAR(120)    NOT NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Products ───────────────────────────────────────────────────────────────
CREATE TABLE products (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED    NOT NULL,
    name        VARCHAR(180)    NOT NULL,
    slug        VARCHAR(200)    NOT NULL,
    description TEXT            NOT NULL,
    price       DECIMAL(10,2)   NOT NULL,
    old_price   DECIMAL(10,2)   DEFAULT NULL,
    rating      DECIMAL(2,1)    NOT NULL DEFAULT 4.5,
    stock       INT UNSIGNED    NOT NULL DEFAULT 100,
    colors      VARCHAR(255)    NOT NULL DEFAULT 'Black,White',
    sizes       VARCHAR(255)    NOT NULL DEFAULT 'S,M,L,XL',
    is_popular  TINYINT(1)      NOT NULL DEFAULT 0,
    is_sold     TINYINT(1)      NOT NULL DEFAULT 0,
    is_sale     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    INDEX idx_products_category (category_id),
    INDEX idx_products_price    (price),
    INDEX idx_products_popular  (is_popular),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product Images ─────────────────────────────────────────────────────────
CREATE TABLE product_images (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED    NOT NULL,
    image_data LONGBLOB        DEFAULT NULL,
    image_mime VARCHAR(100)    DEFAULT NULL,
    image_name VARCHAR(255)    DEFAULT NULL,
    image_path VARCHAR(255)    DEFAULT NULL,
    is_main    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_images_product (product_id),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reviews ────────────────────────────────────────────────────────────────
CREATE TABLE reviews (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    product_id    INT UNSIGNED    NOT NULL,
    user_id       INT UNSIGNED    DEFAULT NULL,
    reviewer_name VARCHAR(120)    NOT NULL,
    rating        TINYINT UNSIGNED NOT NULL,
    comment       TEXT            NOT NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_rating  (rating),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cart ───────────────────────────────────────────────────────────────────
CREATE TABLE cart (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NOT NULL,
    product_id INT UNSIGNED    NOT NULL,
    quantity   INT UNSIGNED    NOT NULL DEFAULT 1,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_product (user_id, product_id),
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Orders ─────────────────────────────────────────────────────────────────
CREATE TABLE orders (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    DEFAULT NULL,
    customer_name    VARCHAR(120)    NOT NULL,
    customer_email   VARCHAR(150)    DEFAULT NULL,
    customer_phone   VARCHAR(25)     DEFAULT NULL,
    subtotal         DECIMAL(10,2)   NOT NULL,
    discount         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    delivery_fee     DECIMAL(10,2)   NOT NULL DEFAULT 15.00,
    total            DECIMAL(10,2)   NOT NULL,
    payment_method   VARCHAR(50)     NOT NULL DEFAULT 'cod',
    payment_status   ENUM('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
    payment_ref      VARCHAR(255)    DEFAULT NULL,
    shipping_address TEXT            DEFAULT NULL,
    status           ENUM('Pending','Paid','Shipped','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_orders_status  (status),
    INDEX idx_orders_user    (user_id),
    INDEX idx_orders_created (created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Order Items ────────────────────────────────────────────────────────────
CREATE TABLE order_items (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED    NOT NULL,
    product_id   INT UNSIGNED    NOT NULL,
    product_name VARCHAR(180)    NOT NULL,
    unit_price   DECIMAL(10,2)   NOT NULL,
    quantity     INT UNSIGNED    NOT NULL,
    line_total   DECIMAL(10,2)   NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════════════════
-- SEED DATA
-- ══════════════════════════════════════════════════════════════════════════

-- ── System Settings ────────────────────────────────────────────────────────
INSERT INTO system_settings (setting_key, setting_value) VALUES
('max_login_attempts',       '3'),
('lockout_duration_minutes', '30'),
('session_timeout_minutes',  '120'),
('password_min_length',      '8'),
('password_require_upper',   '1'),
('password_require_number',  '1'),
('password_require_special', '1'),
('password_expiry_days',     '90'),
('recaptcha_site_key',       ''),
('recaptcha_secret_key',     ''),
('enable_totp',              '1'),
('enable_email_activation',  '1'),
('maintenance_mode',         '0'),
('nav_show_shop',            '1'),
('nav_show_new_in',          '1'),
('nav_show_sale',            '1');

-- ── Admin account  (password: Admin@1234) ─────────────────────────────────
INSERT INTO admin (email, username, password_hash) VALUES
('lolomopubg@gmail.com', 'admin',
 '$2y$12$TWeiq3VYAX0ucymyzEQn2uWlho5nSAClU.GWnD/A0ptqxUlIwGAw6');

-- ── Employee account  (password: Employee@1234) ───────────────────────────
INSERT INTO employees (full_name, username, email, password_hash, is_active) VALUES
('System Employee', 'employee', 'dawoud_fareswaelibrahim@plpasig.edu.ph',
 '$2y$12$xdXEGbt7LJcxSbNG0wcDI..SE3k8K9RVchvUAsLGjmHhtIaTDMRtK', 1);

-- ── Sample user  (password: User@1234) ────────────────────────────────────
INSERT INTO users (full_name, email, phone, password_hash, is_verified, password_changed_at) VALUES
('Sample User', 'user@example.com', NULL,
 '$2y$12$gHnM5jBShL.389lpkcgXQuFPooDCOYcaLbvAO9y3HGSYeShxSnAlG', 1, NOW());

-- ── Categories ─────────────────────────────────────────────────────────────
INSERT INTO categories (name, slug) VALUES
('T-Shirts', 't-shirts'),
('Shirts',   'shirts'),
('Jeans',    'jeans'),
('Shorts',   'shorts'),
('Hoodies',  'hoodies');

-- ── Products ───────────────────────────────────────────────────────────────
INSERT INTO products (category_id, name, slug, description, price, old_price, rating, stock, colors, sizes, is_popular) VALUES
(1, 'Gradient Graphic T-shirt',  'gradient-graphic-tshirt',    'Premium cotton tee with vibrant gradient print and breathable fabric.',      145.00, 165.00, 3.5, 120, 'White,Blue,Pink',    'S,M,L,XL', 1),
(2, 'Polo with Tipping Details', 'polo-with-tipping-details',  'Classic polo shirt with contrast tipping details for a clean casual style.', 180.00, 210.00, 4.6,  90, 'Rose,Black,White',   'M,L,XL',   1),
(1, 'Black Striped T-shirt',     'black-striped-tshirt',       'Soft striped t-shirt with modern fit and lightweight comfort.',              120.00, 150.00, 5.0, 110, 'Black,White',        'S,M,L',    1),
(3, 'Skinny Fit Jeans',          'skinny-fit-jeans',           'Stretch denim jeans with tailored fit and all-day comfort.',                 240.00, 260.00, 3.5,  70, 'Blue,Black',         'M,L,XL',   1),
(2, 'Checkered Shirt',           'checkered-shirt',            'Long-sleeve checkered shirt with soft brushed cotton finish.',               180.00, 210.00, 4.5,  80, 'Red,Blue',           'S,M,L,XL', 1),
(1, 'Sleeve Striped T-shirt',    'sleeve-striped-tshirt',      'Athletic-inspired striped sleeve t-shirt in breathable knit fabric.',        130.00, 160.00, 4.6, 120, 'Orange,Black',       'S,M,L,XL', 1),
(2, 'Vertical Striped Shirt',    'vertical-striped-shirt',     'Button-up shirt with vertical stripes and a clean regular fit.',             212.00, 232.00, 4.0,  65, 'Green,White',        'M,L,XL',   0),
(1, 'Courage Graphic T-shirt',   'courage-graphic-tshirt',     'Streetwear style graphic t-shirt with bold chest print.',                   145.00,   NULL, 4.0, 140, 'Orange,Black',       'S,M,L',    0),
(4, 'Loose Fit Bermuda Shorts',  'loose-fit-bermuda-shorts',   'Relaxed bermuda shorts made for hot weather comfort.',                        80.00,   NULL, 3.0,  95, 'Blue,Gray',          'M,L,XL',   0),
(1, 'One Life Graphic T-shirt',  'one-life-graphic-tshirt',    'Soft premium tee featuring One Life graphic with washed finish.',            260.00, 300.00, 4.7,  45, 'Brown,Black,Gray',   'S,M,L,XL', 1);

-- ── Reviews ────────────────────────────────────────────────────────────────
INSERT INTO reviews (product_id, user_id, reviewer_name, rating, comment) VALUES
(10, 1, 'Samantha D.', 5, 'Very comfortable fit and premium fabric feel. Worth the price.'),
(10, 1, 'Alex M.',     4, 'Good quality shirt. Color matches the photos.'),
(10, 1, 'Olivia R.',   5, 'Exactly what I expected. Great stitching and soft touch.'),
(1,  1, 'Liam K.',     4, 'Print quality is solid and it fits true to size.');
