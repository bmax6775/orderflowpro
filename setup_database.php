<?php
// Database setup script for OrderDesk
require_once 'config.php';

try {
    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            phone TEXT,
            role TEXT CHECK(role IN ('super_admin', 'admin', 'agent')) NOT NULL,
            status TEXT CHECK(status IN ('pending', 'active', 'inactive', 'suspended')) DEFAULT 'pending',
            plan_id INTEGER,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pricing_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            max_agents INTEGER NOT NULL,
            max_orders INTEGER NOT NULL,
            features TEXT,
            is_custom BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            admin_id INTEGER NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT UNIQUE NOT NULL,
            customer_name TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_city TEXT,
            product_name TEXT NOT NULL,
            product_price DECIMAL(10,2) NOT NULL,
            status TEXT CHECK(status IN ('new', 'called', 'confirmed', 'in_transit', 'delivered', 'failed')) DEFAULT 'new',
            assigned_agent_id INTEGER,
            admin_id INTEGER NOT NULL,
            store_id INTEGER,
            remarks TEXT,
            screenshot_path TEXT,
            call_count INTEGER DEFAULT 0,
            last_call_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pending_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            phone TEXT,
            role TEXT CHECK(role IN ('admin', 'agent')) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            plan_id INTEGER NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            due_date DATE NOT NULL,
            status TEXT CHECK(status IN ('pending', 'paid', 'overdue')) DEFAULT 'pending',
            payment_method TEXT,
            payment_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            plan_id INTEGER NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status TEXT CHECK(status IN ('pending', 'confirmed', 'rejected')) DEFAULT 'pending',
            payment_method TEXT,
            payment_details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_status_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            old_status TEXT,
            new_status TEXT,
            changed_by INTEGER NOT NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create screenshots table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS screenshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            filename TEXT NOT NULL,
            original_filename TEXT,
            file_size INTEGER,
            uploaded_by INTEGER,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ");

    // Insert default pricing plans
    $pdo->exec("
        INSERT OR IGNORE INTO pricing_plans (id, name, price, max_agents, max_orders, features) VALUES
        (1, 'Basic', 29.00, 3, 500, 'Basic Analytics,Email Support,CSV Export'),
        (2, 'Professional', 59.00, 10, 2000, 'Advanced Analytics,Priority Support,All Export Formats'),
        (3, 'Enterprise', 99.00, -1, -1, 'Premium Analytics,24/7 Support,Custom Features'),
        (4, 'Custom', 0.00, -1, -1, 'Fully Customizable,White Label,API Access')
    ");

    // Insert demo users
    $pdo->exec("
        INSERT OR IGNORE INTO users (id, username, email, password, full_name, phone, role, status, plan_id, created_by) VALUES
        (1, 'superadmin', 'superadmin@orderdesk.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'System Administrator', '+1-555-0000', 'super_admin', 'active', 3, 1),
        (2, 'demoadmin', 'demoadmin@orderdesk.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Demo Admin User', '+1-555-0001', 'admin', 'active', 2, 1),
        (3, 'demoagent', 'demoagent@orderdesk.com', '" . password_hash('password', PASSWORD_DEFAULT) . "', 'Demo Agent User', '+1-555-0002', 'agent', 'active', 1, 2)
    ");
    
    // Update existing admin user with proper plan
    $pdo->exec("UPDATE users SET plan_id = 2 WHERE id = 2 AND plan_id IS NULL");

    // Insert demo store
    $pdo->exec("
        INSERT OR IGNORE INTO stores (id, name, admin_id, description) VALUES
        (1, 'Demo Store', 2, 'Demo store for testing purposes')
    ");

    // Insert demo orders
    $orders = [
        ['ORD-DEMO-001', 'John Smith', '+1-555-0101', 'New York', 'iPhone 14 Pro Max 256GB', 1199.99],
        ['ORD-DEMO-002', 'Sarah Johnson', '+1-555-0102', 'Los Angeles', 'Samsung Galaxy S23 Ultra', 1199.99],
        ['ORD-DEMO-003', 'Michael Brown', '+1-555-0103', 'Chicago', 'MacBook Air M2 13-inch', 1199.00],
        ['ORD-DEMO-004', 'Emily Davis', '+1-555-0104', 'Houston', 'iPad Pro 12.9-inch', 1099.00],
        ['ORD-DEMO-005', 'David Wilson', '+1-555-0105', 'Phoenix', 'Apple Watch Series 9', 399.00]
    ];

    foreach ($orders as $order) {
        $pdo->exec("
            INSERT OR IGNORE INTO orders (order_id, customer_name, customer_phone, customer_city, product_name, product_price, admin_id, store_id, assigned_agent_id, status) VALUES
            ('{$order[0]}', '{$order[1]}', '{$order[2]}', '{$order[3]}', '{$order[4]}', {$order[5]}, 2, 1, 3, 'new')
        ");
    }

    echo "Database setup completed successfully!";

} catch(PDOException $e) {
    echo "Error setting up database: " . $e->getMessage();
}
?>