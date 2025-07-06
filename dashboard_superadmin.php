<?php
session_start();
require_once 'config.php';

requireRole('super_admin');

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'super_admin'");
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
$total_orders = $stmt->fetch()['total_orders'];

$stmt = $pdo->query("SELECT COUNT(*) as pending_users FROM users WHERE status = 'pending'");
$pending_users = $stmt->fetch()['pending_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_admins FROM users WHERE role = 'admin'");
$total_admins = $stmt->fetch()['total_admins'];

$stmt = $pdo->query("SELECT COUNT(*) as total_agents FROM users WHERE role = 'agent'");
$total_agents = $stmt->fetch()['total_agents'];

// Get recent activities
$stmt = $pdo->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
$recent_activities = $stmt->fetchAll();

// Get pending payment confirmations
$stmt = $pdo->query("SELECT pr.*, u.full_name, pp.name as plan_name FROM payment_records pr JOIN users u ON pr.admin_id = u.id JOIN pricing_plans pp ON pr.plan_id = pp.id WHERE pr.status = 'pending' ORDER BY pr.created_at DESC");
$pending_payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_superadmin.php">
                <i class="fas fa-crown me-2"></i>OrderDesk Super Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_superadmin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="approve_users.php">
                            User Approvals
                            <?php if ($pending_users > 0): ?>
                                <span class="badge bg-warning"><?php echo $pending_users; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Manage Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_stores.php">Stores</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_pricing.php">Pricing Plans</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payment_tracking.php">Payment Tracking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="audit_logs.php">Audit Logs</a>
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
                <h2>Super Admin Dashboard</h2>
                <p class="text-muted">Manage the entire OrderDesk system</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h5 class="card-title"><?php echo $total_users; ?></h5>
                        <p class="card-text">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                        <h5 class="card-title"><?php echo $total_admins; ?></h5>
                        <p class="card-text">Admins</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-headset fa-2x text-info mb-2"></i>
                        <h5 class="card-title"><?php echo $total_agents; ?></h5>
                        <p class="card-text">Agents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-shopping-bag fa-2x text-warning mb-2"></i>
                        <h5 class="card-title"><?php echo $total_orders; ?></h5>
                        <p class="card-text">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                        <h5 class="card-title"><?php echo $pending_users; ?></h5>
                        <p class="card-text">Pending Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                        <h5 class="card-title"><?php echo count($pending_payments); ?></h5>
                        <p class="card-text">Pending Payments</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Activities -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Recent Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activities)): ?>
                            <p class="text-muted">No recent activities found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></td>
                                                <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                                <td><?php echo formatDateTime($activity['created_at']); ?></td>
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
                            <a href="approve_users.php" class="btn btn-primary">
                                <i class="fas fa-user-check me-2"></i>Approve Users
                                <?php if ($pending_users > 0): ?>
                                    <span class="badge bg-warning"><?php echo $pending_users; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="manage_pricing.php" class="btn btn-success">
                                <i class="fas fa-tags me-2"></i>Manage Pricing
                            </a>
                            <a href="payment_tracking.php" class="btn btn-info">
                                <i class="fas fa-credit-card me-2"></i>Payment Tracking
                            </a>
                            <a href="audit_logs.php" class="btn btn-warning">
                                <i class="fas fa-search me-2"></i>View Audit Logs
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pending Payments -->
                <?php if (!empty($pending_payments)): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Pending Payments
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($pending_payments as $payment): ?>
                                <div class="alert alert-warning mb-2">
                                    <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                                    <?php echo htmlspecialchars($payment['plan_name']); ?> - $<?php echo $payment['amount']; ?>
                                    <div class="mt-1">
                                        <a href="payment_tracking.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline-primary">Review</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
