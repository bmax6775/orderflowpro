<?php
session_start();
require_once 'config.php';

requireRole('super_admin');

$success = '';
$error = '';

// Handle plan creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_plan'])) {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $max_agents = intval($_POST['max_agents']);
        $max_orders = intval($_POST['max_orders']);
        $features = trim($_POST['features']);
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        
        if (empty($name) || $price < 0) {
            $error = 'Please provide a valid plan name and price.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO pricing_plans (name, price, max_agents, max_orders, features, is_custom) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $price, $max_agents, $max_orders, $features, $is_custom])) {
                $success = 'Pricing plan created successfully!';
                logActivity($_SESSION['user_id'], 'pricing_plan_created', "Created pricing plan: $name");
            } else {
                $error = 'Failed to create pricing plan.';
            }
        }
    } elseif (isset($_POST['update_plan'])) {
        $plan_id = intval($_POST['plan_id']);
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $max_agents = intval($_POST['max_agents']);
        $max_orders = intval($_POST['max_orders']);
        $features = trim($_POST['features']);
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        
        if (empty($name) || $price < 0) {
            $error = 'Please provide a valid plan name and price.';
        } else {
            $stmt = $pdo->prepare("UPDATE pricing_plans SET name = ?, price = ?, max_agents = ?, max_orders = ?, features = ?, is_custom = ? WHERE id = ?");
            if ($stmt->execute([$name, $price, $max_agents, $max_orders, $features, $is_custom, $plan_id])) {
                $success = 'Pricing plan updated successfully!';
                logActivity($_SESSION['user_id'], 'pricing_plan_updated', "Updated pricing plan: $name");
            } else {
                $error = 'Failed to update pricing plan.';
            }
        }
    } elseif (isset($_POST['delete_plan'])) {
        $plan_id = intval($_POST['plan_id']);
        
        // Check if plan is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE plan_id = ?");
        $stmt->execute([$plan_id]);
        $usage_count = $stmt->fetch()['count'];
        
        if ($usage_count > 0) {
            $error = 'Cannot delete plan. It is currently assigned to ' . $usage_count . ' users.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM pricing_plans WHERE id = ?");
            if ($stmt->execute([$plan_id])) {
                $success = 'Pricing plan deleted successfully!';
                logActivity($_SESSION['user_id'], 'pricing_plan_deleted', "Deleted pricing plan ID: $plan_id");
            } else {
                $error = 'Failed to delete pricing plan.';
            }
        }
    }
}

// Get all pricing plans
$stmt = $pdo->query("SELECT pp.*, COUNT(u.id) as users_count FROM pricing_plans pp LEFT JOIN users u ON pp.id = u.plan_id GROUP BY pp.id ORDER BY pp.price");
$plans = $stmt->fetchAll();

// Get plan for editing
$edit_plan = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM pricing_plans WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_plan = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pricing Plans - OrderDesk</title>
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
                        <a class="nav-link active" href="manage_pricing.php">Pricing Plans</a>
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Manage Pricing Plans</h2>
                        <p class="text-muted">Create and manage pricing plans for your customers</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                            <i class="fas fa-plus me-2"></i>Create New Plan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Pricing Plans Grid -->
        <div class="row">
            <?php foreach ($plans as $plan): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 <?php echo $plan['is_custom'] ? 'border-warning' : ''; ?>">
                        <div class="card-header text-center">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($plan['name']); ?>
                                <?php if ($plan['is_custom']): ?>
                                    <span class="badge bg-warning">Custom</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="pricing-price mb-3">
                                <span class="h2 fw-bold">
                                    <?php echo $plan['price'] > 0 ? '$' . number_format($plan['price'], 2) : 'Custom'; ?>
                                </span>
                                <?php if ($plan['price'] > 0): ?>
                                    <span class="text-muted">/month</span>
                                <?php endif; ?>
                            </div>
                            
                            <ul class="list-unstyled mb-4">
                                <li><i class="fas fa-users text-success me-2"></i>
                                    <?php echo $plan['max_agents'] == -1 ? 'Unlimited' : $plan['max_agents']; ?> Agents
                                </li>
                                <li><i class="fas fa-shopping-bag text-success me-2"></i>
                                    <?php echo $plan['max_orders'] == -1 ? 'Unlimited' : number_format($plan['max_orders']); ?> Orders/month
                                </li>
                                <?php if ($plan['features']): ?>
                                    <?php foreach (explode(',', $plan['features']) as $feature): ?>
                                        <li><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars(trim($feature)); ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $plan['users_count']; ?> users assigned
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <a href="manage_pricing.php?edit=<?php echo $plan['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($plan['users_count'] == 0): ?>
                                    <button class="btn btn-outline-danger" onclick="deletePlan(<?php echo $plan['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create/Edit Plan Modal -->
    <div class="modal fade" id="createPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $edit_plan ? 'Edit Pricing Plan' : 'Create New Pricing Plan'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php if ($edit_plan): ?>
                            <input type="hidden" name="plan_id" value="<?php echo $edit_plan['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Plan Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($edit_plan['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price (Monthly)</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" value="<?php echo $edit_plan['price'] ?? ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_agents" class="form-label">Max Agents</label>
                                    <input type="number" class="form-control" id="max_agents" name="max_agents" 
                                           value="<?php echo $edit_plan['max_agents'] ?? ''; ?>" required>
                                    <div class="form-text">Use -1 for unlimited</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_orders" class="form-label">Max Orders (Monthly)</label>
                                    <input type="number" class="form-control" id="max_orders" name="max_orders" 
                                           value="<?php echo $edit_plan['max_orders'] ?? ''; ?>" required>
                                    <div class="form-text">Use -1 for unlimited</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="features" class="form-label">Features</label>
                            <textarea class="form-control" id="features" name="features" rows="3" 
                                      placeholder="Enter features separated by commas"><?php echo htmlspecialchars($edit_plan['features'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_custom" name="is_custom" 
                                       <?php echo ($edit_plan['is_custom'] ?? false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_custom">
                                    Custom Plan (requires manual configuration)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="<?php echo $edit_plan ? 'update_plan' : 'create_plan'; ?>" class="btn btn-primary">
                            <?php echo $edit_plan ? 'Update Plan' : 'Create Plan'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this pricing plan? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="plan_id" id="deletePlanId">
                        <button type="submit" name="delete_plan" class="btn btn-danger">Delete Plan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        function deletePlan(planId) {
            document.getElementById('deletePlanId').value = planId;
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        <?php if ($edit_plan): ?>
            // Show modal for editing
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('createPlanModal'));
                modal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>
