<?php
session_start();
require_once 'config.php';

requireRole('admin');

// Get admin's plan info from user table
$stmt = $pdo->prepare("SELECT u.*, pp.name as plan_name FROM users u LEFT JOIN pricing_plans pp ON u.plan_id = pp.id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_info = $stmt->fetch();

// For demo purposes, create a mock active payment record if user has a plan
$active_payment = null;
if ($admin_info['plan_id']) {
    $active_payment = [
        'plan_name' => $admin_info['plan_name'],
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 'paid'
    ];
}

// Get admin's stores
$stmt = $pdo->prepare("SELECT * FROM stores WHERE admin_id = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$stores = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE admin_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_orders = $stmt->fetch()['total_orders'];

$stmt = $pdo->prepare("SELECT COUNT(*) as new_orders FROM orders WHERE admin_id = ? AND status = 'new'");
$stmt->execute([$_SESSION['user_id']]);
$new_orders = $stmt->fetch()['new_orders'];

$stmt = $pdo->prepare("SELECT COUNT(*) as delivered_orders FROM orders WHERE admin_id = ? AND status = 'delivered'");
$stmt->execute([$_SESSION['user_id']]);
$delivered_orders = $stmt->fetch()['delivered_orders'];

$stmt = $pdo->prepare("SELECT COUNT(*) as agents FROM users WHERE role = 'agent' AND created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_agents = $stmt->fetch()['agents'];

// Get recent orders
$stmt = $pdo->prepare("SELECT o.*, s.name as store_name, u.full_name as agent_name FROM orders o LEFT JOIN stores s ON o.store_id = s.id LEFT JOIN users u ON o.assigned_agent_id = u.id WHERE o.admin_id = ? ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_admin.php">
                <i class="fas fa-user-tie me-2"></i>OrderDesk Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_admin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_orders.php">Upload Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_stores.php">Stores</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="store_analytics.php">Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agent_performance.php">Agent Performance</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-outline-light btn-sm" onclick="toggleDarkMode()">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2>Admin Dashboard</h2>
                <p class="text-muted">Manage your orders, stores, and agents</p>
                
                <!-- Plan Status -->
                <?php if ($active_payment): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Active Plan:</strong> <?php echo htmlspecialchars($active_payment['plan_name']); ?>
                        <span class="ms-3">
                            <strong>Next Payment:</strong> <?php echo formatDateTime($active_payment['due_date']); ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Plan Status:</strong> No active plan assigned. Contact Super Admin for plan assignment.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-shopping-bag fa-2x text-primary mb-2"></i>
                        <h4 class="card-title"><?php echo $total_orders; ?></h4>
                        <p class="card-text">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-2x text-warning mb-2"></i>
                        <h4 class="card-title"><?php echo $new_orders; ?></h4>
                        <p class="card-text">New Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="card-title"><?php echo $delivered_orders; ?></h4>
                        <p class="card-text">Delivered</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-headset fa-2x text-info mb-2"></i>
                        <h4 class="card-title"><?php echo $total_agents; ?></h4>
                        <p class="card-text">Agents</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>Recent Orders
                        </h5>
                        <a href="manage_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-muted">No orders found. <a href="upload_orders.php">Upload your first order</a>.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Status</th>
                                            <th>Agent</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <a href="view_order.php?id=<?php echo $order['id']; ?>">
                                                        <?php echo htmlspecialchars($order['order_id']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                                <td><?php echo getStatusBadge($order['status']); ?></td>
                                                <td><?php echo htmlspecialchars($order['agent_name'] ?? 'Unassigned'); ?></td>
                                                <td><?php echo formatDateTime($order['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="upload_orders.php" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Orders
                            </a>
                            <a href="manage_orders.php" class="btn btn-success">
                                <i class="fas fa-list me-2"></i>Manage Orders
                            </a>
                            <a href="manage_stores.php" class="btn btn-info">
                                <i class="fas fa-store me-2"></i>Manage Stores
                            </a>
                            <a href="store_analytics.php" class="btn btn-warning">
                                <i class="fas fa-chart-bar me-2"></i>View Analytics
                            </a>
                            <a href="export_orders.php" class="btn btn-secondary">
                                <i class="fas fa-download me-2"></i>Export Orders
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stores -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-store me-2"></i>Your Stores
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stores)): ?>
                            <p class="text-muted">No stores found. <a href="manage_stores.php">Create your first store</a>.</p>
                        <?php else: ?>
                            <?php foreach ($stores as $store): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($store['name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($store['description']); ?></small>
                                    </div>
                                    <a href="store_analytics.php?store_id=<?php echo $store['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
