<?php
session_start();
require_once 'config.php';

requireLogin();

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $format = $_POST['format'];
    $status = $_POST['status'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $store_id = $_POST['store_id'] ?? '';
    $agent_id = $_POST['agent_id'] ?? '';
    
    // Build query based on user role
    $where_conditions = [];
    $params = [];
    
    if ($user_role === 'admin') {
        $where_conditions[] = "o.admin_id = ?";
        $params[] = $user_id;
    } elseif ($user_role === 'agent') {
        $where_conditions[] = "o.assigned_agent_id = ?";
        $params[] = $user_id;
    }
    
    // Add filters
    if ($status && $status !== 'all') {
        $where_conditions[] = "o.status = ?";
        $params[] = $status;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    if ($store_id) {
        $where_conditions[] = "o.store_id = ?";
        $params[] = $store_id;
    }
    
    if ($agent_id) {
        $where_conditions[] = "o.assigned_agent_id = ?";
        $params[] = $agent_id;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get orders
    $sql = "SELECT o.order_id, o.customer_name, o.customer_phone, o.customer_city, 
                   o.product_name, o.product_price, o.status, o.remarks, o.created_at, o.updated_at,
                   s.name as store_name, u.full_name as agent_name, a.full_name as admin_name
            FROM orders o 
            LEFT JOIN stores s ON o.store_id = s.id 
            LEFT JOIN users u ON o.assigned_agent_id = u.id 
            LEFT JOIN users a ON o.admin_id = a.id 
            $where_clause 
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // CSV Export
        $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Order ID', 'Customer Name', 'Customer Phone', 'Customer City',
            'Product Name', 'Product Price', 'Status', 'Store', 'Agent',
            'Admin', 'Remarks', 'Created Date', 'Updated Date'
        ]);
        
        // CSV Data
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['customer_name'],
                $order['customer_phone'],
                $order['customer_city'],
                $order['product_name'],
                $order['product_price'],
                ucfirst($order['status']),
                $order['store_name'],
                $order['agent_name'],
                $order['admin_name'],
                $order['remarks'],
                $order['created_at'],
                $order['updated_at']
            ]);
        }
        
        fclose($output);
        logActivity($user_id, 'orders_exported', "Exported " . count($orders) . " orders to CSV");
        exit();
    } else {
        // Excel/HTML Export
        $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Order ID</th><th>Customer Name</th><th>Customer Phone</th><th>Customer City</th>';
        echo '<th>Product Name</th><th>Product Price</th><th>Status</th><th>Store</th>';
        echo '<th>Agent</th><th>Admin</th><th>Remarks</th><th>Created Date</th><th>Updated Date</th>';
        echo '</tr>';
        
        foreach ($orders as $order) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($order['order_id']) . '</td>';
            echo '<td>' . htmlspecialchars($order['customer_name']) . '</td>';
            echo '<td>' . htmlspecialchars($order['customer_phone']) . '</td>';
            echo '<td>' . htmlspecialchars($order['customer_city']) . '</td>';
            echo '<td>' . htmlspecialchars($order['product_name']) . '</td>';
            echo '<td>' . number_format($order['product_price'], 2) . '</td>';
            echo '<td>' . ucfirst($order['status']) . '</td>';
            echo '<td>' . htmlspecialchars($order['store_name']) . '</td>';
            echo '<td>' . htmlspecialchars($order['agent_name']) . '</td>';
            echo '<td>' . htmlspecialchars($order['admin_name']) . '</td>';
            echo '<td>' . htmlspecialchars($order['remarks']) . '</td>';
            echo '<td>' . $order['created_at'] . '</td>';
            echo '<td>' . $order['updated_at'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        logActivity($user_id, 'orders_exported', "Exported " . count($orders) . " orders to Excel");
        exit();
    }
}

// Get stores for filter (admin only)
$stores = [];
if ($user_role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE admin_id = ? ORDER BY name");
    $stmt->execute([$user_id]);
    $stores = $stmt->fetchAll();
}

// Get agents for filter (admin only)
$agents = [];
if ($user_role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'agent' AND created_by = ? ORDER BY full_name");
    $stmt->execute([$user_id]);
    $agents = $stmt->fetchAll();
}

// Get count of orders for preview
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders o WHERE " . ($user_role === 'admin' ? "o.admin_id = ?" : "o.assigned_agent_id = ?"));
$stmt->execute([$user_id]);
$total_orders = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Orders - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-<?php echo $user_role === 'super_admin' ? 'primary' : ($user_role === 'admin' ? 'success' : 'info'); ?>">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_<?php echo $user_role; ?>.php">
                <i class="fas fa-<?php echo $user_role === 'super_admin' ? 'crown' : ($user_role === 'admin' ? 'user-tie' : 'headset'); ?> me-2"></i>OrderDesk <?php echo ucfirst($user_role); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard_<?php echo $user_role; ?>.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_orders.php">Orders</a>
                    </li>
                    <?php if ($user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_stores.php">Stores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="store_analytics.php">Analytics</a>
                        </li>
                    <?php endif; ?>
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Export Orders</h2>
                        <p class="text-muted">Export your orders to CSV or Excel format</p>
                    </div>
                    <div>
                        <a href="manage_orders.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-download me-2"></i>Export Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="format" class="form-label">Export Format</label>
                                        <select name="format" id="format" class="form-select" required>
                                            <option value="csv">CSV (Comma Separated Values)</option>
                                            <option value="excel">Excel (XLS)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Order Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="all">All Status</option>
                                            <option value="new">New</option>
                                            <option value="called">Called</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="in_transit">In Transit</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_from" class="form-label">Date From</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_to" class="form-label">Date To</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to">
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($user_role === 'admin'): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="store_id" class="form-label">Store</label>
                                            <select name="store_id" id="store_id" class="form-select">
                                                <option value="">All Stores</option>
                                                <?php foreach ($stores as $store): ?>
                                                    <option value="<?php echo $store['id']; ?>">
                                                        <?php echo htmlspecialchars($store['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="agent_id" class="form-label">Agent</label>
                                            <select name="agent_id" id="agent_id" class="form-select">
                                                <option value="">All Agents</option>
                                                <?php foreach ($agents as $agent): ?>
                                                    <option value="<?php echo $agent['id']; ?>">
                                                        <?php echo htmlspecialchars($agent['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid">
                                <button type="submit" name="export" class="btn btn-success btn-lg">
                                    <i class="fas fa-download me-2"></i>Export Orders
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Export Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Export Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Available Data</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Order ID</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Customer Information</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Product Details</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Order Status</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Store & Agent Info</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Remarks & Notes</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Timestamps</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Export Formats</h6>
                                <div class="mb-3">
                                    <strong>CSV Format:</strong>
                                    <ul class="small">
                                        <li>Compatible with Excel, Google Sheets</li>
                                        <li>Smaller file size</li>
                                        <li>UTF-8 encoding</li>
                                    </ul>
                                </div>
                                <div>
                                    <strong>Excel Format:</strong>
                                    <ul class="small">
                                        <li>Native Excel file (.xls)</li>
                                        <li>Formatted table with borders</li>
                                        <li>Ready for immediate use</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Total Available Orders:</strong> <?php echo number_format($total_orders); ?>
                            <br>
                            Apply filters above to export specific subsets of your orders.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        // Set default date range to last 30 days
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
            
            document.getElementById('date_to').value = today.toISOString().split('T')[0];
            document.getElementById('date_from').value = thirtyDaysAgo.toISOString().split('T')[0];
        });
    </script>
</body>
</html>
