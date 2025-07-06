<?php
session_start();
require_once 'config.php';

requireLogin();

$order_id = $_GET['id'] ?? 0;

// Get order details
$stmt = $pdo->prepare("SELECT o.*, s.name as store_name, u.full_name as agent_name, a.full_name as admin_name 
                       FROM orders o 
                       LEFT JOIN stores s ON o.store_id = s.id 
                       LEFT JOIN users u ON o.assigned_agent_id = u.id 
                       LEFT JOIN users a ON o.admin_id = a.id 
                       WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: manage_orders.php');
    exit();
}

// Check access permissions
$user_role = $_SESSION['role'];
if ($user_role === 'admin' && $order['admin_id'] != $_SESSION['user_id']) {
    header('Location: manage_orders.php');
    exit();
} elseif ($user_role === 'agent' && $order['assigned_agent_id'] != $_SESSION['user_id']) {
    header('Location: manage_orders.php');
    exit();
}

// Get order status history
$stmt = $pdo->prepare("SELECT osh.*, u.full_name FROM order_status_history osh LEFT JOIN users u ON osh.changed_by = u.id WHERE osh.order_id = ? ORDER BY osh.created_at DESC");
$stmt->execute([$order_id]);
$status_history = $stmt->fetchAll();

// Get screenshots
$stmt = $pdo->prepare("SELECT s.*, u.full_name FROM screenshots s LEFT JOIN users u ON s.uploaded_by = u.id WHERE s.order_id = ? ORDER BY s.created_at DESC");
$stmt->execute([$order_id]);
$screenshots = $stmt->fetchAll();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];
    $remarks = $_POST['remarks'];
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, remarks = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$new_status, $remarks, $order_id]);
    
    // Log status change
    $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, new_status, changed_by, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $new_status, $_SESSION['user_id'], $remarks]);
    
    logActivity($_SESSION['user_id'], 'order_status_changed', "Changed order {$order['order_id']} status to $new_status");
    
    $success = "Order status updated successfully!";
    
    // Refresh order data
    $stmt = $pdo->prepare("SELECT o.*, s.name as store_name, u.full_name as agent_name, a.full_name as admin_name 
                           FROM orders o 
                           LEFT JOIN stores s ON o.store_id = s.id 
                           LEFT JOIN users u ON o.assigned_agent_id = u.id 
                           LEFT JOIN users a ON o.admin_id = a.id 
                           WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
}

// Handle agent assignment (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_agent']) && $user_role === 'admin') {
    $agent_id = $_POST['agent_id'] ?: null;
    
    $stmt = $pdo->prepare("UPDATE orders SET assigned_agent_id = ? WHERE id = ?");
    $stmt->execute([$agent_id, $order_id]);
    
    logActivity($_SESSION['user_id'], 'order_agent_assigned', "Assigned agent to order {$order['order_id']}");
    
    $success = "Agent assigned successfully!";
    
    // Refresh order data
    $stmt = $pdo->prepare("SELECT o.*, s.name as store_name, u.full_name as agent_name, a.full_name as admin_name 
                           FROM orders o 
                           LEFT JOIN stores s ON o.store_id = s.id 
                           LEFT JOIN users u ON o.assigned_agent_id = u.id 
                           LEFT JOIN users a ON o.admin_id = a.id 
                           WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
}

// Handle screenshot upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_screenshot'])) {
    $notes = $_POST['notes'] ?? '';
    $uploaded_file = $_FILES['screenshot'];
    
    if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
        $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/screenshots/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($uploaded_file['tmp_name'], $filepath)) {
                // Save to database
                $stmt = $pdo->prepare("INSERT INTO screenshots (order_id, filename, original_filename, file_size, uploaded_by, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $filename, $uploaded_file['name'], $uploaded_file['size'], $_SESSION['user_id'], $notes]);
                
                logActivity($_SESSION['user_id'], 'screenshot_uploaded', "Uploaded screenshot for order {$order['order_id']}");
                
                $success = "Screenshot uploaded successfully!";
                
                // Refresh screenshots
                $stmt = $pdo->prepare("SELECT s.*, u.full_name FROM screenshots s LEFT JOIN users u ON s.uploaded_by = u.id WHERE s.order_id = ? ORDER BY s.created_at DESC");
                $stmt->execute([$order_id]);
                $screenshots = $stmt->fetchAll();
            } else {
                $error = "Failed to upload screenshot. Please try again.";
            }
        } else {
            $error = "Invalid file type. Please upload an image file (JPG, PNG, GIF, BMP).";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Get agents for assignment (admin only)
$agents = [];
if ($user_role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'agent' AND created_by = ? ORDER BY full_name");
    $stmt->execute([$_SESSION['user_id']]);
    $agents = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - OrderDesk</title>
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
                        <a class="nav-link active" href="manage_orders.php">Orders</a>
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
                        <h2>Order Details</h2>
                        <p class="text-muted">Order ID: <?php echo htmlspecialchars($order['order_id']); ?></p>
                    </div>
                    <div>
                        <a href="manage_orders.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Order Information -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Order Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th>Order ID:</th>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Customer Name:</th>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Customer Phone:</th>
                                        <td>
                                            <a href="tel:<?php echo $order['customer_phone']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($order['customer_phone']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Customer City:</th>
                                        <td><?php echo htmlspecialchars($order['customer_city']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Product Name:</th>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th>Product Price:</th>
                                        <td>$<?php echo number_format($order['product_price'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td><?php echo getStatusBadge($order['status']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Store:</th>
                                        <td><?php echo htmlspecialchars($order['store_name'] ?? 'Not assigned'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Assigned Agent:</th>
                                        <td><?php echo htmlspecialchars($order['agent_name'] ?? 'Not assigned'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Created Date:</th>
                                        <td><?php echo formatDateTime($order['created_at']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($order['remarks']): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Remarks:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['remarks'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status History -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Status History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($status_history)): ?>
                            <p class="text-muted">No status history available.</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($status_history as $history): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <h6><?php echo getStatusBadge($history['new_status']); ?></h6>
                                            <p class="mb-1">
                                                <strong><?php echo htmlspecialchars($history['full_name']); ?></strong>
                                                <small class="text-muted"><?php echo formatDateTime($history['created_at']); ?></small>
                                            </p>
                                            <?php if ($history['notes']): ?>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($history['notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Screenshots -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-camera me-2"></i>Screenshots
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($screenshots)): ?>
                            <p class="text-muted">No screenshots uploaded.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($screenshots as $screenshot): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card">
                                            <img src="<?php echo SCREENSHOT_PATH . $screenshot['filename']; ?>" 
                                                 class="card-img-top" style="height: 200px; object-fit: cover;" 
                                                 alt="Screenshot">
                                            <div class="card-body p-2">
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($screenshot['full_name']); ?><br>
                                                    <?php echo formatDateTime($screenshot['created_at']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button class="btn btn-primary" onclick="showUploadModal()">
                                <i class="fas fa-plus me-2"></i>Upload Screenshot
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Panel -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="tel:<?php echo $order['customer_phone']; ?>" class="btn btn-success">
                                <i class="fas fa-phone me-2"></i>Call Customer
                            </a>
                            <button class="btn btn-warning" onclick="showStatusModal()">
                                <i class="fas fa-edit me-2"></i>Update Status
                            </button>
                            <button class="btn btn-info" onclick="showUploadModal()">
                                <i class="fas fa-camera me-2"></i>Upload Screenshot
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Agent Assignment (Admin Only) -->
                <?php if ($user_role === 'admin'): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-check me-2"></i>Agent Assignment
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="agent_id" class="form-label">Assign Agent</label>
                                    <select name="agent_id" id="agent_id" class="form-select">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?php echo $agent['id']; ?>" 
                                                    <?php echo $order['assigned_agent_id'] == $agent['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($agent['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="assign_agent" class="btn btn-primary">
                                        <i class="fas fa-check me-2"></i>Assign Agent
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">New Status</label>
                            <select name="new_status" id="new_status" class="form-select" required>
                                <option value="new" <?php echo $order['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="called" <?php echo $order['status'] === 'called' ? 'selected' : ''; ?>>Called</option>
                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_transit" <?php echo $order['status'] === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="failed" <?php echo $order['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
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

    <!-- Upload Screenshot Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Screenshot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="upload_screenshot.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        
                        <div class="mb-3">
                            <label for="screenshot" class="form-label">Select Screenshot</label>
                            <input type="file" class="form-control" id="screenshot" name="screenshot" 
                                   accept="image/jpeg,image/png,image/jpg" required>
                            <div class="form-text">Only JPEG and PNG files are allowed.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Screenshot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        function showStatusModal() {
            var modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
        
        function showUploadModal() {
            var modal = new bootstrap.Modal(document.getElementById('uploadModal'));
            modal.show();
        }
    </script>
</body>
</html>
