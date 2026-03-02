CREATE DATABASE IF NOT EXISTS shop_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shop_system;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admin;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) UNIQUE NULL,
    phone VARCHAR(25) UNIQUE NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_phone (phone)
);

CREATE TABLE admin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    old_price DECIMAL(10,2) NULL,
    rating DECIMAL(2,1) DEFAULT 4.5,
    stock INT UNSIGNED DEFAULT 100,
    colors VARCHAR(255) DEFAULT 'Black,White',
    sizes VARCHAR(255) DEFAULT 'S,M,L,XL',
    is_popular TINYINT(1) DEFAULT 0,
    is_sold TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_products_category (category_id),
    INDEX idx_products_price (price),
    INDEX idx_products_popular (is_popular)
);

CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_main TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_images_product (product_id)
);

CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    reviewer_name VARCHAR(120) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_rating (rating)
);

CREATE TABLE cart (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_product (user_id, product_id)
);

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(150) NULL,
    customer_phone VARCHAR(25) NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 15,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Paid', 'Shipped', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at)
);

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(180) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order_items_order (order_id)
);

INSERT INTO admin (username, password_hash) VALUES
('admin', '$2y$10$Us5MxKC1tKtxA7zM8Bi/IOG55k3BktRVbFuKXlAdCGXg/mIQW0j8C');

INSERT INTO categories (name, slug) VALUES
('T-Shirts', 't-shirts'),
('Shirts', 'shirts'),
('Jeans', 'jeans'),
('Shorts', 'shorts'),
('Hoodies', 'hoodies');

INSERT INTO users (full_name, email, phone, password_hash) VALUES
('Sample User', 'user@example.com', NULL, '$2y$10$BnuTapX2wtO1LE0IPEjgvOVxlbYNLn9cJIu6If/fWMxjLsBP2OQQq');

INSERT INTO products (category_id, name, slug, description, price, old_price, rating, stock, colors, sizes, is_popular) VALUES
(1, 'Gradient Graphic T-shirt', 'gradient-graphic-tshirt', 'Premium cotton tee with vibrant gradient print and breathable fabric.', 145.00, 165.00, 3.5, 120, 'White,Blue,Pink', 'S,M,L,XL', 1),
(2, 'Polo with Tipping Details', 'polo-with-tipping-details', 'Classic polo shirt with contrast tipping details for a clean casual style.', 180.00, 210.00, 4.6, 90, 'Rose,Black,White', 'M,L,XL', 1),
(1, 'Black Striped T-shirt', 'black-striped-tshirt', 'Soft striped t-shirt with modern fit and lightweight comfort.', 120.00, 150.00, 5.0, 110, 'Black,White', 'S,M,L', 1),
(3, 'Skinny Fit Jeans', 'skinny-fit-jeans', 'Stretch denim jeans with tailored fit and all-day comfort.', 240.00, 260.00, 3.5, 70, 'Blue,Black', 'M,L,XL', 1),
(2, 'Checkered Shirt', 'checkered-shirt', 'Long-sleeve checkered shirt with soft brushed cotton finish.', 180.00, 210.00, 4.5, 80, 'Red,Blue', 'S,M,L,XL', 1),
(1, 'Sleeve Striped T-shirt', 'sleeve-striped-tshirt', 'Athletic-inspired striped sleeve t-shirt in breathable knit fabric.', 130.00, 160.00, 4.6, 120, 'Orange,Black', 'S,M,L,XL', 1),
(2, 'Vertical Striped Shirt', 'vertical-striped-shirt', 'Button-up shirt with vertical stripes and a clean regular fit.', 212.00, 232.00, 4.0, 65, 'Green,White', 'M,L,XL', 0),
(1, 'Courage Graphic T-shirt', 'courage-graphic-tshirt', 'Streetwear style graphic t-shirt with bold chest print.', 145.00, NULL, 4.0, 140, 'Orange,Black', 'S,M,L', 0),
(4, 'Loose Fit Bermuda Shorts', 'loose-fit-bermuda-shorts', 'Relaxed bermuda shorts made for hot weather comfort.', 80.00, NULL, 3.0, 95, 'Blue,Gray', 'M,L,XL', 0),
(1, 'One Life Graphic T-shirt', 'one-life-graphic-tshirt', 'Soft premium tee featuring One Life graphic with washed finish.', 260.00, 300.00, 4.7, 45, 'Brown,Black,Gray', 'S,M,L,XL', 1);

INSERT INTO product_images (product_id, image_path, is_main) VALUES
(1, '/shop-system/assets/images/model1.png', 1),
(1, '/shop-system/assets/images/model.png', 0),
(2, '/shop-system/assets/images/model.png', 1),
(2, '/shop-system/assets/images/model1.png', 0),
(3, '/shop-system/assets/images/model1.png', 1),
(4, '/shop-system/assets/images/model.png', 1),
(5, '/shop-system/assets/images/model1.png', 1),
(6, '/shop-system/assets/images/model.png', 1),
(7, '/shop-system/assets/images/model1.png', 1),
(8, '/shop-system/assets/images/model.png', 1),
(9, '/shop-system/assets/images/model1.png', 1),
(10, '/shop-system/assets/images/model.png', 1);

INSERT INTO reviews (product_id, user_id, reviewer_name, rating, comment) VALUES
(10, 1, 'Samantha D.', 5, 'Very comfortable fit and premium fabric feel. Worth the price.'),
(10, 1, 'Alex M.', 4, 'Good quality shirt. Color matches the photos.'),
(10, 1, 'Olivia R.', 5, 'Exactly what I expected. Great stitching and soft touch.'),
(1, 1, 'Liam K.', 4, 'Print quality is solid and it fits true to size.');
