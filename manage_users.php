<?php
session_start();
require_once 'config.php';

requireLogin();
if ($_SESSION['role'] !== 'super_admin') {
    header('Location: dashboard_' . $_SESSION['role'] . '.php');
    exit();
}

$success = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $plan_id = $_POST['plan_id'] ?: null;
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($email) || empty($full_name) || empty($role) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role, status, plan_id, created_by) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role, $plan_id, $_SESSION['user_id']])) {
                $success = 'User created successfully!';
                logActivity($_SESSION['user_id'], 'user_created', "Created user: $username ($role)");
            } else {
                $error = 'Failed to create user. Please try again.';
            }
        }
    }
}

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $user_id])) {
        $success = 'User status updated successfully!';
        logActivity($_SESSION['user_id'], 'user_status_changed', "Changed user ID $user_id status to $new_status");
    } else {
        $error = 'Failed to update user status.';
    }
}

// Handle user plan update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan'])) {
    $user_id = $_POST['user_id'];
    $plan_id = $_POST['plan_id'] ?: null;
    
    $stmt = $pdo->prepare("UPDATE users SET plan_id = ? WHERE id = ?");
    if ($stmt->execute([$plan_id, $user_id])) {
        $success = 'User plan updated successfully!';
        logActivity($_SESSION['user_id'], 'user_plan_changed', "Changed user ID $user_id plan to $plan_id");
    } else {
        $error = 'Failed to update user plan.';
    }
}

// Get all users
$stmt = $pdo->prepare("SELECT u.*, p.name as plan_name FROM users u LEFT JOIN pricing_plans p ON u.plan_id = p.id WHERE u.id != ? ORDER BY u.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Get pricing plans
$stmt = $pdo->prepare("SELECT * FROM pricing_plans ORDER BY price ASC");
$stmt->execute();
$plans = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - OrderDesk</title>
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
                        <a class="nav-link" href="dashboard_superadmin.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="approve_users.php">User Approvals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_users.php">Manage Users</a>
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Manage Users</h2>
                        <p class="text-muted">Create and manage system users</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i>Create User
                    </button>
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

        <!-- Users Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>System Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Plan</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <?php if ($user['role'] === 'admin'): ?>
                                                            <i class="fas fa-user-tie text-success fa-lg"></i>
                                                        <?php elseif ($user['role'] === 'agent'): ?>
                                                            <i class="fas fa-headset text-info fa-lg"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-user text-secondary fa-lg"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small><br>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-success' : ($user['role'] === 'agent' ? 'bg-info' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['plan_name']): ?>
                                                    <?php echo htmlspecialchars($user['plan_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No Plan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>', '<?php echo $user['plan_id'] ?: ''; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
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
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin">Admin</option>
                                        <option value="agent">Agent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_id" class="form-label">Plan</label>
                                    <select class="form-select" id="plan_id" name="plan_id">
                                        <option value="">No Plan</option>
                                        <?php foreach ($plans as $plan): ?>
                                            <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editUserForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="new_status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_plan_id" class="form-label">Plan</label>
                                    <select class="form-select" id="edit_plan_id" name="plan_id">
                                        <option value="">No Plan</option>
                                        <?php foreach ($plans as $plan): ?>
                                            <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-warning">Update Status</button>
                        <button type="submit" name="update_plan" class="btn btn-primary">Update Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        function editUser(userId, status, planId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_plan_id').value = planId;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
    </script>
</body>
</html>