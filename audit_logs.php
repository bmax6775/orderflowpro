<?php
session_start();
require_once 'config.php';

requireRole('super_admin');

// Get filters
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;

// Build query
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($date_from) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM audit_logs al $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_logs = $stmt->fetch()['total'];

// Calculate pagination
$total_pages = ceil($total_logs / $per_page);
$offset = ($page - 1) * $per_page;

// Get logs
$sql = "SELECT al.*, u.full_name, u.email 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        $where_clause 
        ORDER BY al.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get users for filter
$stmt = $pdo->query("SELECT id, full_name, email FROM users ORDER BY full_name");
$users = $stmt->fetchAll();

// Get common actions for filter
$stmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$actions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
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
                        <a class="nav-link" href="dashboard_superadmin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="approve_users.php">User Approvals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_pricing.php">Pricing Plans</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payment_tracking.php">Payment Tracking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="audit_logs.php">Audit Logs</a>
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
                <h2>Audit Logs</h2>
                <p class="text-muted">System activity logs and user actions</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Filter Logs
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="user_id" class="form-label">User</label>
                                <select name="user_id" id="user_id" class="form-select">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action</label>
                                <select name="action" id="action" class="form-select">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                                <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($action['action']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <a href="audit_logs.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-refresh me-2"></i>Clear Filters
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Activity Logs
                            <span class="badge bg-primary"><?php echo number_format($total_logs); ?></span>
                        </h5>
                        <div>
                            <small class="text-muted">
                                Showing <?php echo number_format($offset + 1); ?> - 
                                <?php echo number_format(min($offset + $per_page, $total_logs)); ?> 
                                of <?php echo number_format($total_logs); ?> logs
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No logs found</h5>
                                <p class="text-muted">No logs match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo formatDateTime($log['created_at']); ?></div>
                                                    <small class="text-muted"><?php echo date('D, M j, Y', strtotime($log['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($log['full_name']): ?>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">System</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo strpos($log['action'], 'login') !== false ? 'success' : 
                                                             (strpos($log['action'], 'error') !== false ? 'danger' : 
                                                              (strpos($log['action'], 'delete') !== false ? 'warning' : 'info')); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($log['action']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="details-text">
                                                        <?php echo htmlspecialchars($log['details']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav aria-label="Audit logs pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        // Auto-refresh every 30 seconds
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
        
        // Truncate long details text
        document.addEventListener('DOMContentLoaded', function() {
            const detailsElements = document.querySelectorAll('.details-text');
            detailsElements.forEach(function(element) {
                if (element.textContent.length > 100) {
                    const truncated = element.textContent.substring(0, 100) + '...';
                    const original = element.textContent;
                    element.textContent = truncated;
                    element.style.cursor = 'pointer';
                    element.title = 'Click to expand';
                    
                    element.addEventListener('click', function() {
                        if (element.textContent === truncated) {
                            element.textContent = original;
                            element.title = 'Click to collapse';
                        } else {
                            element.textContent = truncated;
                            element.title = 'Click to expand';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
