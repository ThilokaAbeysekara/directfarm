-- DirectFarm LK - Full Database Schema
-- Run this in phpMyAdmin to set up the database

CREATE DATABASE IF NOT EXISTS directfarm_lk;
USE directfarm_lk;

-- USERS TABLE (farmers + consumers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('farmer', 'consumer', 'admin') NOT NULL DEFAULT 'consumer',
    phone VARCHAR(20),
    district VARCHAR(50),
    address TEXT,
    profile_pic VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PRODUCTS TABLE
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    category ENUM('vegetables','fruits','grains','spices','other') NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    stock DECIMAL(10,2) DEFAULT 0,
    district VARCHAR(50),
    image VARCHAR(255),
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ORDERS TABLE
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumer_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_address TEXT NOT NULL,
    payment_method ENUM('card','bank_transfer','cash_on_delivery') DEFAULT 'cash_on_delivery',
    status ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consumer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ORDER ITEMS TABLE
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    farmer_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (farmer_id) REFERENCES users(id)
);

-- CART TABLE
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consumer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (consumer_id, product_id)
);

-- REVIEWS TABLE
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    consumer_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- MESSAGES TABLE (farmer-consumer chat)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    product_id INT,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- CONTACT MESSAGES TABLE
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- MARKET INSIGHTS (price data)
CREATE TABLE market_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    avg_price DECIMAL(10,2),
    min_price DECIMAL(10,2),
    max_price DECIMAL(10,2),
    recorded_date DATE NOT NULL,
    district VARCHAR(50)
);

-- Insert sample admin user (password: admin123)
INSERT INTO users (name, email, password, role, is_verified) VALUES
('Admin', 'admin@directfarmlk.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Insert sample farmers (password: password)
INSERT INTO users (name, email, password, role, phone, district, is_verified) VALUES
('Sunil Perera', 'sunil@farm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '0771234567', 'Nuwara Eliya', 1),
('K. Silva', 'ksilva@farm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '0772345678', 'Matale', 1),
('S. Senavirathne', 'ssena@farm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '0773456789', 'Badulla', 1);

-- Insert sample consumer (password: password)
INSERT INTO users (name, email, password, role, phone, district) VALUES
('Kamal Perera', 'kamal@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'consumer', '0774567890', 'Colombo');

-- Insert sample products
INSERT INTO products (farmer_id, name, category, description, price, unit, stock, district, image) VALUES
(2, 'Fresh Carrots', 'vegetables', 'Fresh organic carrots sourced from Nuwara Eliya. Rich in vitamins, perfect for cooking and salads.', 150.00, 'kg', 100, 'Nuwara Eliya', 'carrot.jpg'),
(3, 'Fresh Mangoes', 'fruits', 'Sweet, juicy mangoes directly from Matale farms. Perfect for eating fresh or making juice.', 120.00, 'kg', 80, 'Matale', 'mangoes.jpg'),
(4, 'Organic Cabbage', 'vegetables', 'Fresh green cabbage from Badulla. Crisp and nutritious, ideal for salads and stir-fries.', 200.00, 'kg', 60, 'Badulla', 'cabbage.jpeg'),
(2, 'Sweet Corn', 'vegetables', 'Fresh sweet corn from Rathnapura farms. Tender and delicious.', 200.00, 'kg', 50, 'Rathnapura', 'corn.jpg'),
(3, 'Basmathi Rice', 'grains', 'Premium quality Basmathi rice from Polonnaruwa. Long grain, aromatic rice.', 220.00, 'kg', 200, 'Polonnaruwa', 'rice.jpeg'),
(4, 'Cinnamon', 'spices', 'Pure Ceylon cinnamon from Galle. World-renowned quality, perfect for cooking and baking.', 2000.00, 'kg', 30, 'Galle', 'cinnamom.jpeg');

-- Insert sample market prices
INSERT INTO market_prices (product_name, category, avg_price, min_price, max_price, recorded_date, district) VALUES
('Carrot', 'vegetables', 148, 120, 175, CURDATE(), 'Nuwara Eliya'),
('Tomato', 'vegetables', 95, 75, 120, CURDATE(), 'Kandy'),
('Cabbage', 'vegetables', 95, 70, 120, CURDATE(), 'Badulla'),
('Potato', 'vegetables', 165, 140, 190, CURDATE(), 'Nuwara Eliya'),
('Mango', 'fruits', 115, 90, 140, CURDATE(), 'Matale'),
('Banana', 'fruits', 85, 70, 100, CURDATE(), 'Colombo'),
('Pineapple', 'fruits', 200, 175, 225, CURDATE(), 'Gampaha'),
('Rice (Basmathi)', 'grains', 220, 200, 250, CURDATE(), 'Polonnaruwa'),
('Rice (Samba)', 'grains', 130, 115, 145, CURDATE(), 'Anuradhapura'),
('Cinnamon', 'spices', 1950, 1800, 2100, CURDATE(), 'Galle'),
('Pepper', 'spices', 1500, 1350, 1650, CURDATE(), 'Kandy'),
('Carrot', 'vegetables', 135, 115, 160, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Nuwara Eliya'),
('Tomato', 'vegetables', 110, 90, 130, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Kandy'),
('Mango', 'fruits', 105, 85, 125, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Matale'),
('Carrot', 'vegetables', 120, 100, 145, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Nuwara Eliya'),
('Tomato', 'vegetables', 125, 105, 145, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Kandy'),
('Mango', 'fruits', 95, 75, 115, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Matale');

-- Insert sample reviews
INSERT INTO reviews (product_id, consumer_id, rating, comment) VALUES
(1, 5, 5, 'Very fresh and clean. Fast delivery!'),
(1, 5, 4, 'Good quality carrots. Worth the price.'),
(3, 5, 5, 'Excellent cabbage, very fresh!');
