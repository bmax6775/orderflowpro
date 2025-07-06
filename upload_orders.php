<?php
session_start();
require_once 'config.php';

requireRole('admin');

$success = '';
$error = '';

// Get admin's stores
$stmt = $pdo->prepare("SELECT * FROM stores WHERE admin_id = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$stores = $stmt->fetchAll();

// Get admin's agents
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'agent' AND created_by = ? ORDER BY full_name");
$stmt->execute([$_SESSION['user_id']]);
$agents = $stmt->fetchAll();

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        
        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ['csv'])) {
            $error = 'Please upload a CSV file.';
        } else {
            // Parse CSV file
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle); // Skip header row
            
            $uploaded_count = 0;
            $errors = [];
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 6) {
                    $errors[] = "Row " . ($uploaded_count + 2) . ": Insufficient columns";
                    continue;
                }
                
                $order_id = trim($data[0]);
                $customer_name = trim($data[1]);
                $customer_phone = trim($data[2]);
                $customer_city = trim($data[3]);
                $product_name = trim($data[4]);
                $product_price = floatval($data[5]);
                $store_id = $_POST['store_id'] ?? null;
                
                // Validate required fields
                if (empty($order_id) || empty($customer_name) || empty($customer_phone) || empty($product_name)) {
                    $errors[] = "Row " . ($uploaded_count + 2) . ": Missing required fields";
                    continue;
                }
                
                // Check if order ID already exists
                $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_id = ? AND admin_id = ?");
                $stmt->execute([$order_id, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $errors[] = "Row " . ($uploaded_count + 2) . ": Order ID '$order_id' already exists";
                    continue;
                }
                
                // Insert order
                $stmt = $pdo->prepare("INSERT INTO orders (order_id, customer_name, customer_phone, customer_city, product_name, product_price, store_id, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$order_id, $customer_name, $customer_phone, $customer_city, $product_name, $product_price, $store_id, $_SESSION['user_id']])) {
                    $uploaded_count++;
                } else {
                    $errors[] = "Row " . ($uploaded_count + 2) . ": Failed to insert order";
                }
            }
            
            fclose($handle);
            
            if ($uploaded_count > 0) {
                $success = "Successfully uploaded $uploaded_count orders.";
                logActivity($_SESSION['user_id'], 'bulk_order_upload', "Uploaded $uploaded_count orders via CSV");
            }
            
            if (!empty($errors)) {
                $error = "Some orders failed to upload:\n" . implode("\n", $errors);
            }
        }
    } else {
        $error = 'Please select a CSV file to upload.';
    }
}

// Handle manual order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $order_id = trim($_POST['order_id']);
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_city = trim($_POST['customer_city']);
    $product_name = trim($_POST['product_name']);
    $product_price = floatval($_POST['product_price']);
    $store_id = $_POST['store_id'] ?? null;
    $agent_id = $_POST['agent_id'] ?? null;
    
    if (empty($order_id) || empty($customer_name) || empty($customer_phone) || empty($product_name)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if order ID already exists
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_id = ? AND admin_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $error = 'Order ID already exists.';
        } else {
            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (order_id, customer_name, customer_phone, customer_city, product_name, product_price, store_id, admin_id, assigned_agent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$order_id, $customer_name, $customer_phone, $customer_city, $product_name, $product_price, $store_id, $_SESSION['user_id'], $agent_id])) {
                $success = 'Order created successfully!';
                logActivity($_SESSION['user_id'], 'order_created', "Created order: $order_id");
                
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to create order. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Orders - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
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
                <h2>Upload Orders</h2>
                <p class="text-muted">Upload orders in bulk via CSV or create orders manually</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo nl2br(htmlspecialchars($success)); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo nl2br(htmlspecialchars($error)); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- CSV Upload -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-upload me-2"></i>Bulk Upload via CSV
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">CSV File</label>
                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                <div class="form-text">
                                    Upload a CSV file with orders. <a href="demo_orders.csv" download>Download sample format</a>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="store_id" class="form-label">Store</label>
                                <select name="store_id" id="store_id" class="form-select">
                                    <option value="">Select Store (Optional)</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="upload_csv" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload CSV
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <h6>CSV Format Requirements:</h6>
                            <ul class="small">
                                <li>Order ID, Customer Name, Phone, City, Product Name, Price</li>
                                <li>First row should contain headers</li>
                                <li>All fields are required except City</li>
                                <li>Price should be numeric</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Order Creation -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus me-2"></i>Create Order Manually
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="order_id" class="form-label">Order ID *</label>
                                <input type="text" class="form-control" id="order_id" name="order_id" 
                                       value="<?php echo htmlspecialchars($_POST['order_id'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Customer Name *</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">Customer Phone *</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                                       value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_city" class="form-label">Customer City</label>
                                <input type="text" class="form-control" id="customer_city" name="customer_city" 
                                       value="<?php echo htmlspecialchars($_POST['customer_city'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="product_name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="product_name" name="product_name" 
                                       value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="product_price" class="form-label">Product Price *</label>
                                <input type="number" class="form-control" id="product_price" name="product_price" 
                                       step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['product_price'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="store_id_manual" class="form-label">Store</label>
                                <select name="store_id" id="store_id_manual" class="form-select">
                                    <option value="">Select Store (Optional)</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>" 
                                                <?php echo ($_POST['store_id'] ?? '') == $store['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($store['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="agent_id" class="form-label">Assign Agent</label>
                                <select name="agent_id" id="agent_id" class="form-select">
                                    <option value="">Select Agent (Optional)</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" 
                                                <?php echo ($_POST['agent_id'] ?? '') == $agent['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="create_order" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Create Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
