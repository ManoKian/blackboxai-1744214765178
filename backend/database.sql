-- Database schema for FastOrder system
CREATE DATABASE IF NOT EXISTS fastorder;
USE fastorder;

-- Menu items table
CREATE TABLE menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(50) NOT NULL,
    items JSON NOT NULL,
    status ENUM('Pending Approval', 'In Preparation', 'Ready', 'Delivered') DEFAULT 'Pending Approval',
    payment_method ENUM('cash', 'card') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cash logs table
CREATE TABLE cash_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Sample menu items
INSERT INTO menu (name, price, image_url) VALUES
('Cheeseburger', 8.99, 'https://images.pexels.com/photos/1639557/pexels-photo-1639557.jpeg'),
('Margherita Pizza', 12.50, 'https://images.pexels.com/photos/2147491/pexels-photo-2147491.jpeg'),
('Chicken Sandwich', 7.99, 'https://images.pexels.com/photos/1633525/pexels-photo-1633525.jpeg'),
('French Fries', 3.99, 'https://images.pexels.com/photos/1583884/pexels-photo-1583884.jpeg'),
('Soda', 1.99, 'https://images.pexels.com/photos/50593/coca-cola-cold-drink-soft-drink-coke-50593.jpeg');