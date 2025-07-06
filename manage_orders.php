<?php
session_start();
require_once 'config.php';

requireLogin();

// Build query based on user role
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$where_clause = "";
$params = [];

if ($role === 'admin') {
    $where_clause = "WHERE o.admin_id = ?";
    $params[] = $user_id;
} elseif ($role === 'agent') {
    $where_clause = "WHERE o.assigned_agent_id = ?";
    $params[] = $user_id;
}

// Add filters
$filter_status = $_GET['status'] ?? '';
$filter_store = $_GET['store_id'] ?? '';
$filter_agent = $_GET['agent_id'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if ($filter_status) {
    $where_clause .= ($where_clause ? " AND " : "WHERE ") . "o.status = ?";
    $params[] = $filter_status;
}

if ($filter_store) {
    $where_clause .= ($where_clause ? " AND " : "WHERE ") . "o.store_id = ?";
    $params[] = $filter_store;
}

if ($filter_agent) {
    $where_clause .= ($where_clause ? " AND " : "WHERE ") . "o.assigned_agent_id = ?";
    $params[] = $filter_agent;
}

if ($search) {
    $where_clause .= ($where_clause ? " AND " : "WHERE ") . "(o.order_id LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_from) {
    $where_clause .= ($where_clause ? " AND " : "WHERE ") . "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_clause .= ($where_clause ? " AND " : "WHERE ") . "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

// Get orders
$sql = "SELECT o.*, s.name as store_name, u.full_name as agent_name 
        FROM orders o 
        LEFT JOIN stores s ON o.store_id = s.id 
        LEFT JOIN users u ON o.assigned_agent_id = u.id 
        $where_clause 
        ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get stores for filter (admin only)
$stores = [];
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE admin_id = ? ORDER BY name");
    $stmt->execute([$user_id]);
    $stores = $stmt->fetchAll();
}

// Get agents for filter (admin only)
$agents = [];
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'agent' AND created_by = ? ORDER BY full_name");
    $stmt->execute([$user_id]);
    $agents = $stmt->fetchAll();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $remarks = $_POST['remarks'];
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, remarks = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$new_status, $remarks, $order_id]);
    
    // Log status change
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, new_status, changed_by, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $new_status, $user_id, $remarks]);
    
    logActivity($user_id, 'order_status_changed', "Changed order ID $order_id status to $new_status");
    
    $success = "Order status updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-<?php echo $role === 'super_admin' ? 'primary' : ($role === 'admin' ? 'success' : 'info'); ?>">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_<?php echo $role; ?>.php">
                <i class="fas fa-<?php echo $role === 'super_admin' ? 'crown' : ($role === 'admin' ? 'user-tie' : 'headset'); ?> me-2"></i>OrderDesk <?php echo ucfirst($role); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard_<?php echo $role; ?>.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_orders.php">Orders</a>
                    </li>
                    <?php if ($role === 'admin'): ?>
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
                        <h2><?php echo $role === 'agent' ? 'My Orders' : 'Manage Orders'; ?></h2>
                        <p class="text-muted">View and manage your orders</p>
                    </div>
                    <?php if ($role === 'admin'): ?>
                        <div>
                            <a href="upload_orders.php" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Orders
                            </a>
                            <a href="export_orders.php" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Export Orders
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="called" <?php echo $filter_status === 'called' ? 'selected' : ''; ?>>Called</option>
                                    <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="in_transit" <?php echo $filter_status === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                                    <option value="delivered" <?php echo $filter_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                            
                            <?php if ($role === 'admin'): ?>
                                <div class="col-md-3">
                                    <label for="store_id" class="form-label">Store</label>
                                    <select name="store_id" id="store_id" class="form-select">
                                        <option value="">All Stores</option>
                                        <?php foreach ($stores as $store): ?>
                                            <option value="<?php echo $store['id']; ?>" <?php echo $filter_store == $store['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($store['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="agent_id" class="form-label">Agent</label>
                                    <select name="agent_id" id="agent_id" class="form-select">
                                        <option value="">All Agents</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?php echo $agent['id']; ?>" <?php echo $filter_agent == $agent['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($agent['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Order ID, Customer Name, Phone" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Apply Filters
                                </button>
                                <a href="manage_orders.php" class="btn btn-secondary">
                                    <i class="fas fa-refresh me-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-shopping-bag me-2"></i>Orders
                            <span class="badge bg-primary"><?php echo count($orders); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No orders found</h5>
                                <p class="text-muted">No orders match your current filters.</p>
                                <?php if ($role === 'admin'): ?>
                                    <a href="upload_orders.php" class="btn btn-primary">
                                        <i class="fas fa-upload me-2"></i>Upload Orders
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Phone</th>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <?php if ($role === 'admin'): ?>
                                                <th>Agent</th>
                                            <?php endif; ?>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <a href="view_order.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($order['order_id']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_city']); ?></small>
                                                </td>
                                                <td>
                                                    <a href="tel:<?php echo $order['customer_phone']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                                <td>$<?php echo number_format($order['product_price'], 2); ?></td>
                                                <td><?php echo getStatusBadge($order['status']); ?></td>
                                                <?php if ($role === 'admin'): ?>
                                                    <td><?php echo htmlspecialchars($order['agent_name'] ?? 'Unassigned'); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo formatDateTime($order['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($role === 'agent' || $role === 'admin'): ?>
                                                            <button class="btn btn-sm btn-outline-success" onclick="updateOrderStatus(<?php echo $order['id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <a href="tel:<?php echo $order['customer_phone']; ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-phone"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="updateStatusForm">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="modal_order_id">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">New Status</label>
                            <select name="new_status" id="new_status" class="form-select" required>
                                <option value="new">New</option>
                                <option value="called">Called</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="in_transit">In Transit</option>
                                <option value="delivered">Delivered</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea name="remarks" id="remarks" class="form-control" rows="3" 
                                      placeholder="Add any remarks or notes about this status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        function updateOrderStatus(orderId) {
            document.getElementById('modal_order_id').value = orderId;
            var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }
    </script>
</body>
</html>
