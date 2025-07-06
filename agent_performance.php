<?php
session_start();
require_once 'config.php';

requireRole('admin');

// Get admin's agents
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'agent' AND created_by = ? ORDER BY full_name");
$stmt->execute([$_SESSION['user_id']]);
$agents = $stmt->fetchAll();

$selected_agent = $_GET['agent_id'] ?? '';
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

// Build agent filter
$agent_filter = "";
$params = [$_SESSION['user_id']];
if ($selected_agent) {
    $agent_filter = "AND o.assigned_agent_id = ?";
    $params[] = $selected_agent;
}

// Get agent performance data
$performance_data = [];

foreach ($agents as $agent) {
    $agent_id = $agent['id'];
    
    // Total assigned orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders o WHERE o.admin_id = ? AND o.assigned_agent_id = ? $date_filter");
    $stmt->execute([$_SESSION['user_id'], $agent_id]);
    $total_orders = $stmt->fetch()['total'];
    
    // Delivered orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as delivered FROM orders o WHERE o.admin_id = ? AND o.assigned_agent_id = ? AND o.status = 'delivered' $date_filter");
    $stmt->execute([$_SESSION['user_id'], $agent_id]);
    $delivered_orders = $stmt->fetch()['delivered'];
    
    // Failed orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as failed FROM orders o WHERE o.admin_id = ? AND o.assigned_agent_id = ? AND o.status = 'failed' $date_filter");
    $stmt->execute([$_SESSION['user_id'], $agent_id]);
    $failed_orders = $stmt->fetch()['failed'];
    
    // Calls made (status changes to 'called')
    $stmt = $pdo->prepare("SELECT COUNT(*) as calls FROM order_status_history osh JOIN orders o ON osh.order_id = o.id WHERE o.admin_id = ? AND osh.changed_by = ? AND osh.status = 'called' $date_filter");
    $stmt->execute([$_SESSION['user_id'], $agent_id]);
    $calls_made = $stmt->fetch()['calls'];
    
    // Today's calls
    $stmt = $pdo->prepare("SELECT COUNT(*) as today_calls FROM order_status_history osh JOIN orders o ON osh.order_id = o.id WHERE o.admin_id = ? AND osh.changed_by = ? AND osh.status = 'called' AND DATE(osh.created_at) = CURDATE()");
    $stmt->execute([$_SESSION['user_id'], $agent_id]);
    $today_calls = $stmt->fetch()['today_calls'];
    
    // Success rate
    $success_rate = $total_orders > 0 ? round(($delivered_orders / $total_orders) * 100, 2) : 0;
    
    // Average response time (hours between order creation and first call)
    $stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, o.created_at, osh.created_at)) as avg_response_time 
                           FROM orders o 
                           JOIN order_status_history osh ON o.id = osh.order_id 
                           WHERE o.admin_id = ? AND o.assigned_agent_id = ? AND osh.status = 'called' 
                           AND osh.created_at = (SELECT MIN(created_at) FROM order_status_history WHERE order_id = o.id AND status = 'called') 
                           $date_filter");
    $stmt->execute([$_SESSION['user_id'], $agent_id]);
    $avg_response_time = $stmt->fetch()['avg_response_time'] ?? 0;
    
    $performance_data[] = [
        'agent' => $agent,
        'total_orders' => $total_orders,
        'delivered_orders' => $delivered_orders,
        'failed_orders' => $failed_orders,
        'calls_made' => $calls_made,
        'today_calls' => $today_calls,
        'success_rate' => $success_rate,
        'avg_response_time' => round($avg_response_time, 1)
    ];
}

// Get daily call data for chart
$chart_data = [];
if ($selected_agent) {
    $stmt = $pdo->prepare("SELECT DATE(osh.created_at) as call_date, COUNT(*) as calls 
                           FROM order_status_history osh 
                           JOIN orders o ON osh.order_id = o.id 
                           WHERE o.admin_id = ? AND osh.changed_by = ? AND osh.status = 'called' 
                           $date_filter 
                           GROUP BY DATE(osh.created_at) 
                           ORDER BY call_date DESC 
                           LIMIT 30");
    $stmt->execute([$_SESSION['user_id'], $selected_agent]);
    $chart_data = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Performance - OrderDesk</title>
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
                        <a class="nav-link" href="store_analytics.php">Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="agent_performance.php">Agent Performance</a>
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
                <h2>Agent Performance</h2>
                <p class="text-muted">Track and analyze your agents' performance metrics</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="agent_id" class="form-label">Agent</label>
                                <select name="agent_id" id="agent_id" class="form-select">
                                    <option value="">All Agents</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" 
                                                <?php echo $selected_agent == $agent['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['full_name']); ?>
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

        <!-- Performance Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Agent Performance Metrics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Total Orders</th>
                                        <th>Delivered</th>
                                        <th>Failed</th>
                                        <th>Success Rate</th>
                                        <th>Total Calls</th>
                                        <th>Today's Calls</th>
                                        <th>Avg Response Time</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performance_data as $data): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($data['agent']['full_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($data['agent']['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $data['total_orders']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $data['delivered_orders']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $data['failed_orders']; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="width: 60px; height: 20px;">
                                                        <div class="progress-bar bg-<?php echo $data['success_rate'] >= 80 ? 'success' : ($data['success_rate'] >= 60 ? 'warning' : 'danger'); ?>" 
                                                             style="width: <?php echo $data['success_rate']; ?>%"></div>
                                                    </div>
                                                    <small><?php echo $data['success_rate']; ?>%</small>
                                                </div>
                                            </td>
                                            <td><?php echo $data['calls_made']; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $data['today_calls']; ?></span>
                                            </td>
                                            <td><?php echo $data['avg_response_time']; ?>h</td>
                                            <td>
                                                <?php
                                                $performance_score = 0;
                                                if ($data['success_rate'] >= 80) $performance_score += 40;
                                                elseif ($data['success_rate'] >= 60) $performance_score += 25;
                                                elseif ($data['success_rate'] >= 40) $performance_score += 15;
                                                
                                                if ($data['avg_response_time'] <= 2) $performance_score += 30;
                                                elseif ($data['avg_response_time'] <= 4) $performance_score += 20;
                                                elseif ($data['avg_response_time'] <= 8) $performance_score += 10;
                                                
                                                if ($data['calls_made'] >= 50) $performance_score += 30;
                                                elseif ($data['calls_made'] >= 25) $performance_score += 20;
                                                elseif ($data['calls_made'] >= 10) $performance_score += 10;
                                                
                                                $performance_class = $performance_score >= 80 ? 'success' : 
                                                                   ($performance_score >= 60 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $performance_class; ?>">
                                                    <?php echo $performance_score; ?>/100
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <?php if ($selected_agent && !empty($chart_data)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2"></i>Daily Call Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="callsChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Top Performers -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-trophy me-2"></i>Top Performers
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Sort by success rate
                        $top_performers = $performance_data;
                        usort($top_performers, function($a, $b) {
                            return $b['success_rate'] <=> $a['success_rate'];
                        });
                        $top_performers = array_slice($top_performers, 0, 5);
                        ?>
                        <?php foreach ($top_performers as $index => $performer): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-warning me-2"><?php echo $index + 1; ?></span>
                                    <strong><?php echo htmlspecialchars($performer['agent']['full_name']); ?></strong>
                                </div>
                                <span class="badge bg-success"><?php echo $performer['success_rate']; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-phone me-2"></i>Most Active Today
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Sort by today's calls
                        $most_active = $performance_data;
                        usort($most_active, function($a, $b) {
                            return $b['today_calls'] <=> $a['today_calls'];
                        });
                        $most_active = array_slice($most_active, 0, 5);
                        ?>
                        <?php foreach ($most_active as $index => $active): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-info me-2"><?php echo $index + 1; ?></span>
                                    <strong><?php echo htmlspecialchars($active['agent']['full_name']); ?></strong>
                                </div>
                                <span class="badge bg-primary"><?php echo $active['today_calls']; ?> calls</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>Fastest Response
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Sort by response time (ascending)
                        $fastest_response = array_filter($performance_data, function($p) {
                            return $p['avg_response_time'] > 0;
                        });
                        usort($fastest_response, function($a, $b) {
                            return $a['avg_response_time'] <=> $b['avg_response_time'];
                        });
                        $fastest_response = array_slice($fastest_response, 0, 5);
                        ?>
                        <?php foreach ($fastest_response as $index => $fast): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-success me-2"><?php echo $index + 1; ?></span>
                                    <strong><?php echo htmlspecialchars($fast['agent']['full_name']); ?></strong>
                                </div>
                                <span class="badge bg-secondary"><?php echo $fast['avg_response_time']; ?>h</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/script.js"></script>
    
    <?php if ($selected_agent && !empty($chart_data)): ?>
        <script>
            // Daily Calls Chart
            const callsCtx = document.getElementById('callsChart').getContext('2d');
            new Chart(callsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_reverse(array_column($chart_data, 'call_date'))); ?>,
                    datasets: [{
                        label: 'Calls Made',
                        data: <?php echo json_encode(array_reverse(array_column($chart_data, 'calls'))); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
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
    <?php endif; ?>
</body>
</html>
