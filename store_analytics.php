<?php
session_start();
require_once 'config.php';

requireRole('admin');

// Get admin's stores
$stmt = $pdo->prepare("SELECT * FROM stores WHERE admin_id = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$stores = $stmt->fetchAll();

$selected_store = $_GET['store_id'] ?? '';
$date_range = $_GET['date_range'] ?? '30';

// Build date filter
$date_filter = "";
switch ($date_range) {
    case '7':
        $date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30':
        $date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90':
        $date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    default:
        $date_filter = "";
}

// Build store filter
$store_filter = "";
$params = [$_SESSION['user_id']];
if ($selected_store) {
    $store_filter = "AND o.store_id = ?";
    $params[] = $selected_store;
}

// Get analytics data
$analytics = [];

// Total orders
$sql = "SELECT COUNT(*) as total_orders FROM orders o WHERE o.admin_id = ? $store_filter $date_filter";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$analytics['total_orders'] = $stmt->fetch()['total_orders'];

// Orders by status
$sql = "SELECT status, COUNT(*) as count FROM orders o WHERE o.admin_id = ? $store_filter $date_filter GROUP BY status";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$status_data = $stmt->fetchAll();

$analytics['status_breakdown'] = [];
foreach ($status_data as $status) {
    $analytics['status_breakdown'][$status['status']] = $status['count'];
}

// Revenue data
$sql = "SELECT SUM(product_price) as total_revenue, COUNT(*) as delivered_orders FROM orders o WHERE o.admin_id = ? AND o.status = 'delivered' $store_filter $date_filter";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$revenue_data = $stmt->fetch();
$analytics['total_revenue'] = $revenue_data['total_revenue'] ?? 0;
$analytics['delivered_orders'] = $revenue_data['delivered_orders'] ?? 0;

// Success rate
$analytics['success_rate'] = $analytics['total_orders'] > 0 ? 
    round(($analytics['delivered_orders'] / $analytics['total_orders']) * 100, 2) : 0;

// Daily orders for chart
$sql = "SELECT DATE(o.created_at) as order_date, COUNT(*) as count 
        FROM orders o 
        WHERE o.admin_id = ? $store_filter $date_filter 
        GROUP BY DATE(o.created_at) 
        ORDER BY order_date DESC 
        LIMIT 30";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daily_orders = $stmt->fetchAll();

// Top products
$sql = "SELECT product_name, COUNT(*) as count, SUM(product_price) as revenue 
        FROM orders o 
        WHERE o.admin_id = ? $store_filter $date_filter 
        GROUP BY product_name 
        ORDER BY count DESC 
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$top_products = $stmt->fetchAll();

// Agent performance
$sql = "SELECT u.full_name, 
               COUNT(o.id) as total_orders,
               COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as delivered_orders,
               COUNT(CASE WHEN o.status = 'failed' THEN 1 END) as failed_orders
        FROM users u 
        LEFT JOIN orders o ON u.id = o.assigned_agent_id AND o.admin_id = ? $store_filter $date_filter
        WHERE u.role = 'agent' AND u.created_by = ?
        GROUP BY u.id, u.full_name
        ORDER BY total_orders DESC";
$stmt = $pdo->prepare([$_SESSION['user_id'], $_SESSION['user_id']]);
$stmt->execute();
$agent_performance = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Analytics - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
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
                        <a class="nav-link" href="dashboard_admin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_stores.php">Stores</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="store_analytics.php">Analytics</a>
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
                <h2>Store Analytics</h2>
                <p class="text-muted">Analyze your store performance and order statistics</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="store_id" class="form-label">Store</label>
                                <select name="store_id" id="store_id" class="form-select">
                                    <option value="">All Stores</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>" 
                                                <?php echo $selected_store == $store['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($store['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="date_range" class="form-label">Date Range</label>
                                <select name="date_range" id="date_range" class="form-select">
                                    <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                                    <option value="all" <?php echo $date_range === 'all' ? 'selected' : ''; ?>>All Time</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-bag fa-2x text-primary mb-2"></i>
                        <h4><?php echo $analytics['total_orders']; ?></h4>
                        <p class="text-muted">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4><?php echo $analytics['delivered_orders']; ?></h4>
                        <p class="text-muted">Delivered Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x text-info mb-2"></i>
                        <h4>$<?php echo number_format($analytics['total_revenue'], 2); ?></h4>
                        <p class="text-muted">Total Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                        <h4><?php echo $analytics['success_rate']; ?>%</h4>
                        <p class="text-muted">Success Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Status Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Order Status Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Daily Orders Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>Daily Orders
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Top Products -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-star me-2"></i>Top Products
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td><?php echo $product['count']; ?></td>
                                            <td>$<?php echo number_format($product['revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Performance Summary -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>Agent Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($agent_performance as $agent): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong>
                                    <span class="badge bg-primary"><?php echo $agent['total_orders']; ?></span>
                                </div>
                                <div class="progress mt-1">
                                    <?php 
                                    $success_rate = $agent['total_orders'] > 0 ? 
                                        ($agent['delivered_orders'] / $agent['total_orders']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $success_rate; ?>%">
                                        <?php echo round($success_rate, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $agent['delivered_orders']; ?> delivered, 
                                    <?php echo $agent['failed_orders']; ?> failed
                                </small>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-3">
                            <a href="agent_performance.php" class="btn btn-sm btn-outline-primary">
                                View Details <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/script.js"></script>
    <script>
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($analytics['status_breakdown'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($analytics['status_breakdown'])); ?>,
                    backgroundColor: [
                        '#007bff', '#6c757d', '#ffc107', '#fd7e14', '#28a745', '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Daily Orders Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($daily_orders, 'order_date'))); ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode(array_reverse(array_column($daily_orders, 'count'))); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
