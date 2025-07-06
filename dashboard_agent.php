<?php
session_start();
require_once 'config.php';

requireRole('agent');

// Get agent's assigned orders
$stmt = $pdo->prepare("SELECT o.*, s.name as store_name FROM orders o LEFT JOIN stores s ON o.store_id = s.id WHERE o.assigned_agent_id = ? ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$assigned_orders = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_assigned FROM orders WHERE assigned_agent_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_assigned = $stmt->fetch()['total_assigned'];

$stmt = $pdo->prepare("SELECT COUNT(*) as new_orders FROM orders WHERE assigned_agent_id = ? AND status = 'new'");
$stmt->execute([$_SESSION['user_id']]);
$new_orders = $stmt->fetch()['new_orders'];

$stmt = $pdo->prepare("SELECT COUNT(*) as in_progress FROM orders WHERE assigned_agent_id = ? AND status IN ('called', 'confirmed', 'in_transit')");
$stmt->execute([$_SESSION['user_id']]);
$in_progress = $stmt->fetch()['in_progress'];

$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM orders WHERE assigned_agent_id = ? AND status IN ('delivered', 'failed')");
$stmt->execute([$_SESSION['user_id']]);
$completed = $stmt->fetch()['completed'];

// Get today's performance
$stmt = $pdo->prepare("SELECT COUNT(*) as today_calls FROM order_status_history WHERE changed_by = ? AND new_status = 'called' AND DATE(created_at) = DATE('now')");
$stmt->execute([$_SESSION['user_id']]);
$today_calls = $stmt->fetch()['today_calls'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_agent.php">
                <i class="fas fa-headset me-2"></i>OrderDesk Agent
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_agent.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_orders.php">My Orders</a>
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
                <h2>Agent Dashboard</h2>
                <p class="text-muted">Manage your assigned orders and track your performance</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                        <h4 class="card-title"><?php echo $total_assigned; ?></h4>
                        <p class="card-text">Total Assigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-2x text-warning mb-2"></i>
                        <h4 class="card-title"><?php echo $new_orders; ?></h4>
                        <p class="card-text">New Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-info mb-2"></i>
                        <h4 class="card-title"><?php echo $in_progress; ?></h4>
                        <p class="card-text">In Progress</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="card-title"><?php echo $completed; ?></h4>
                        <p class="card-text">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-phone fa-2x text-danger mb-2"></i>
                        <h4 class="card-title"><?php echo $today_calls; ?></h4>
                        <p class="card-text">Today's Calls</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-percentage fa-2x text-secondary mb-2"></i>
                        <h4 class="card-title"><?php echo $total_assigned > 0 ? round(($completed / $total_assigned) * 100, 1) : 0; ?>%</h4>
                        <p class="card-text">Success Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Assigned Orders -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>My Assigned Orders
                        </h5>
                        <a href="manage_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assigned_orders)): ?>
                            <p class="text-muted">No orders assigned to you yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Phone</th>
                                            <th>Product</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <a href="view_order.php?id=<?php echo $order['id']; ?>">
                                                        <?php echo htmlspecialchars($order['order_id']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td>
                                                    <a href="tel:<?php echo $order['customer_phone']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                                <td><?php echo getStatusBadge($order['status']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="tel:<?php echo $order['customer_phone']; ?>" class="btn btn-sm btn-outline-success">
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

            <!-- Call Center Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-phone me-2"></i>Call Center Panel
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="manage_orders.php?status=new" class="btn btn-warning">
                                <i class="fas fa-phone-alt me-2"></i>New Orders to Call
                                <?php if ($new_orders > 0): ?>
                                    <span class="badge bg-light text-dark"><?php echo $new_orders; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="manage_orders.php?status=called" class="btn btn-info">
                                <i class="fas fa-clock me-2"></i>Follow-up Calls
                            </a>
                            <a href="manage_orders.php?status=confirmed" class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Confirmed Orders
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Performance Summary -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>Performance Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="mb-3">
                                    <h6>Today's Calls</h6>
                                    <h4 class="text-primary"><?php echo $today_calls; ?></h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <h6>Success Rate</h6>
                                    <h4 class="text-success"><?php echo $total_assigned > 0 ? round(($completed / $total_assigned) * 100, 1) : 0; ?>%</h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $total_assigned > 0 ? ($completed / $total_assigned) * 100 : 0; ?>%">
                                Completed: <?php echo $completed; ?>/<?php echo $total_assigned; ?>
                            </div>
                        </div>
                        
                        <small class="text-muted">Keep up the great work! Your performance helps the team succeed.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
