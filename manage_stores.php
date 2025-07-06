<?php
session_start();
require_once 'config.php';

requireLogin();

$user_role = getUserRole();
if ($user_role !== 'super_admin' && $user_role !== 'admin') {
    header('Location: dashboard_' . ($user_role === 'super_admin' ? 'superadmin' : $user_role) . '.php');
    exit();
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_store':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $admin_id = $user_role === 'super_admin' ? $_POST['admin_id'] : $_SESSION['user_id'];
                
                if (empty($name)) {
                    $error = 'Store name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO stores (name, admin_id, description) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $admin_id, $description]);
                        $success = 'Store added successfully!';
                        logActivity($_SESSION['user_id'], 'store_created', "Created store: $name");
                    } catch (PDOException $e) {
                        $error = 'Error adding store: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_store':
                $store_id = $_POST['store_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (empty($name)) {
                    $error = 'Store name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE stores SET name = ?, description = ?, updated_at = datetime('now') WHERE id = ?");
                        $stmt->execute([$name, $description, $store_id]);
                        $success = 'Store updated successfully!';
                        logActivity($_SESSION['user_id'], 'store_updated', "Updated store ID: $store_id");
                    } catch (PDOException $e) {
                        $error = 'Error updating store: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_store':
                $store_id = $_POST['store_id'];
                try {
                    // Check if store has orders
                    $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE store_id = ?");
                    $stmt->execute([$store_id]);
                    $order_count = $stmt->fetch()['order_count'];
                    
                    if ($order_count > 0) {
                        $error = 'Cannot delete store with existing orders. Please reassign or remove orders first.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
                        $stmt->execute([$store_id]);
                        $success = 'Store deleted successfully!';
                        logActivity($_SESSION['user_id'], 'store_deleted', "Deleted store ID: $store_id");
                    }
                } catch (PDOException $e) {
                    $error = 'Error deleting store: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get stores based on user role
if ($user_role === 'super_admin') {
    $stmt = $pdo->query("SELECT s.*, u.full_name as admin_name, COUNT(o.id) as order_count 
                         FROM stores s 
                         LEFT JOIN users u ON s.admin_id = u.id 
                         LEFT JOIN orders o ON s.id = o.store_id 
                         GROUP BY s.id 
                         ORDER BY s.created_at DESC");
    $stores = $stmt->fetchAll();
    
    // Get all admins for store assignment
    $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'admin' AND status = 'active'");
    $admins = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT s.*, COUNT(o.id) as order_count 
                           FROM stores s 
                           LEFT JOIN orders o ON s.id = o.store_id 
                           WHERE s.admin_id = ? 
                           GROUP BY s.id 
                           ORDER BY s.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $stores = $stmt->fetchAll();
    $admins = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stores - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard_<?php echo $user_role === 'super_admin' ? 'superadmin' : $user_role; ?>.php">
                <i class="fas fa-shopping-cart me-2"></i>OrderDesk
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard_<?php echo $user_role === 'super_admin' ? 'superadmin' : $user_role; ?>.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <?php if ($user_role !== 'agent'): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_stores.php">
                            <i class="fas fa-store me-1"></i>Stores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_orders.php">
                            <i class="fas fa-box me-1"></i>Orders
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-outline-light btn-sm me-2" onclick="toggleDarkMode()">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-store me-2"></i>Manage Stores</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStoreModal">
                        <i class="fas fa-plus me-2"></i>Add New Store
                    </button>
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

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($stores)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No stores found</h5>
                                <p class="text-muted">Click "Add New Store" to create your first store.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Store Name</th>
                                            <th>Description</th>
                                            <?php if ($user_role === 'super_admin'): ?>
                                            <th>Admin</th>
                                            <?php endif; ?>
                                            <th>Orders</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stores as $store): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($store['name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($store['description'] ?: 'No description'); ?></td>
                                            <?php if ($user_role === 'super_admin'): ?>
                                            <td><?php echo htmlspecialchars($store['admin_name']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="badge bg-info"><?php echo $store['order_count']; ?> orders</span>
                                            </td>
                                            <td><?php echo formatDateTime($store['created_at']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-1" 
                                                        onclick="editStore(<?php echo $store['id']; ?>, '<?php echo addslashes($store['name']); ?>', '<?php echo addslashes($store['description']); ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($store['order_count'] == 0): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteStore(<?php echo $store['id']; ?>, '<?php echo addslashes($store['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete store with orders">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
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

    <!-- Add Store Modal -->
    <div class="modal fade" id="addStoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-store me-2"></i>Add New Store</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_store">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Store Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <?php if ($user_role === 'super_admin' && !empty($admins)): ?>
                        <div class="mb-3">
                            <label for="admin_id" class="form-label">Assign to Admin *</label>
                            <select class="form-select" id="admin_id" name="admin_id" required>
                                <option value="">Select Admin</option>
                                <?php foreach ($admins as $admin): ?>
                                <option value="<?php echo $admin['id']; ?>">
                                    <?php echo htmlspecialchars($admin['full_name'] . ' (' . $admin['username'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Store Modal -->
    <div class="modal fade" id="editStoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Store</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_store">
                        <input type="hidden" name="store_id" id="edit_store_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Store Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Store Form -->
    <form id="deleteStoreForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_store">
        <input type="hidden" name="store_id" id="delete_store_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        function editStore(id, name, description) {
            document.getElementById('edit_store_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            
            const modal = new bootstrap.Modal(document.getElementById('editStoreModal'));
            modal.show();
        }

        function deleteStore(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                document.getElementById('delete_store_id').value = id;
                document.getElementById('deleteStoreForm').submit();
            }
        }
    </script>
</body>
</html>