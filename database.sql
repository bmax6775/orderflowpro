-- OrderDesk Database Schema

CREATE DATABASE IF NOT EXISTS orderdesk;
USE orderdesk;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('super_admin', 'admin', 'agent') NOT NULL,
    status ENUM('pending', 'active', 'inactive', 'suspended') DEFAULT 'pending',
    plan_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- Pricing plans table
CREATE TABLE pricing_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    max_agents INT NOT NULL,
    max_orders INT NOT NULL,
    features TEXT,
    is_custom BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default pricing plans
INSERT INTO pricing_plans (name, price, max_agents, max_orders, features) VALUES
('Basic', 29.00, 3, 500, 'Basic Analytics,Email Support,CSV Export'),
('Professional', 59.00, 10, 2000, 'Advanced Analytics,Priority Support,All Export Formats'),
('Enterprise', 99.00, -1, -1, 'Premium Analytics,24/7 Support,Custom Features'),
('Custom', 0.00, -1, -1, 'Fully Customizable,White Label,API Access');

-- Stores table
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    admin_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100) NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_city VARCHAR(50),
    product_name VARCHAR(200) NOT NULL,
    product_price DECIMAL(10,2),
    status ENUM('new', 'called', 'confirmed', 'in_transit', 'delivered', 'failed') DEFAULT 'new',
    assigned_agent_id INT,
    store_id INT,
    admin_id INT NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_assigned_agent (assigned_agent_id),
    INDEX idx_store (store_id)
);

-- Order status history table
CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('new', 'called', 'confirmed', 'in_transit', 'delivered', 'failed') NOT NULL,
    changed_by INT NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Screenshots table
CREATE TABLE screenshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Payment records table
CREATE TABLE payment_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    plan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_date DATE,
    due_date DATE,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    invoice_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES pricing_plans(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_status (status)
);

-- Audit logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
);

-- Insert default Super Admin
INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES
('superadmin', 'superadmin@orderdesk.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'super_admin', 'active', NOW());

-- Insert demo admin and agent (approved by default for demo)
INSERT INTO users (username, email, password, full_name, role, status, created_by, created_at) VALUES
('demoadmin', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo Admin', 'admin', 'active', 1, NOW()),
('demoagent', 'agent@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo Agent', 'agent', 'active', 2, NOW());

-- Insert demo store
INSERT INTO stores (name, admin_id, description) VALUES
('Demo Store', 2, 'Demo store for testing purposes');

-- Insert demo orders
INSERT INTO orders (order_id, customer_name, customer_phone, customer_city, product_name, product_price, status, store_id, admin_id) VALUES
('ORD-001', 'John Doe', '+1234567890', 'New York', 'iPhone 14 Pro', 999.99, 'new', 1, 2),
('ORD-002', 'Jane Smith', '+1234567891', 'Los Angeles', 'Samsung Galaxy S23', 799.99, 'called', 1, 2),
('ORD-003', 'Bob Johnson', '+1234567892', 'Chicago', 'iPad Air', 599.99, 'confirmed', 1, 2),
('ORD-004', 'Alice Brown', '+1234567893', 'Houston', 'MacBook Pro', 1299.99, 'in_transit', 1, 2),
('ORD-005', 'Charlie Wilson', '+1234567894', 'Phoenix', 'AirPods Pro', 249.99, 'delivered', 1, 2);

-- Update demo admin with a plan
UPDATE users SET plan_id = 2 WHERE id = 2;

-- Insert demo payment record
INSERT INTO payment_records (admin_id, plan_id, amount, payment_date, due_date, status, invoice_number) VALUES
(2, 2, 59.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'paid', 'INV-2025-001');

-- Insert some audit logs
INSERT INTO audit_logs (user_id, action, details) VALUES
(1, 'login', 'Super admin logged in'),
(2, 'login', 'Demo admin logged in'),
(3, 'login', 'Demo agent logged in'),
(2, 'order_created', 'Created order ORD-001'),
(2, 'order_status_changed', 'Changed order ORD-002 status to called');
