<?php
session_start();
require_once 'config.php';

requireRole('super_admin');

$success = '';
$error = '';

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_payment'])) {
        $payment_id = intval($_POST['payment_id']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'];
        
        $stmt = $pdo->prepare("UPDATE payment_records SET status = 'paid', payment_date = ?, payment_method = ?, notes = ? WHERE id = ?");
        if ($stmt->execute([$payment_date, $payment_method, $notes, $payment_id])) {
            $success = 'Payment confirmed successfully!';
            logActivity($_SESSION['user_id'], 'payment_confirmed', "Confirmed payment ID: $payment_id");
        } else {
            $error = 'Failed to confirm payment.';
        }
    } elseif (isset($_POST['create_invoice'])) {
        $admin_id = intval($_POST['admin_id']);
        $plan_id = intval($_POST['plan_id']);
        $amount = floatval($_POST['amount']);
        $due_date = $_POST['due_date'];
        
        // Generate invoice number
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("INSERT INTO payment_records (admin_id, plan_id, amount, due_date, invoice_number, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$admin_id, $plan_id, $amount, $due_date, $invoice_number])) {
            $success = 'Invoice created successfully! Invoice Number: ' . $invoice_number;
            logActivity($_SESSION['user_id'], 'invoice_created', "Created invoice: $invoice_number for admin ID: $admin_id");
        } else {
            $error = 'Failed to create invoice.';
        }
    }
}

// Get payment records
$stmt = $pdo->query("SELECT pr.*, u.full_name, u.email, pp.name as plan_name 
                     FROM payment_records pr 
                     JOIN users u ON pr.admin_id = u.id 
                     JOIN pricing_plans pp ON pr.plan_id = pp.id 
                     ORDER BY pr.created_at DESC");
$payments = $stmt->fetchAll();

// Get admins for invoice creation
$stmt = $pdo->query("SELECT u.*, pp.name as plan_name FROM users u LEFT JOIN pricing_plans pp ON u.plan_id = pp.id WHERE u.role = 'admin' ORDER BY u.full_name");
$admins = $stmt->fetchAll();

// Get pricing plans
$stmt = $pdo->query("SELECT * FROM pricing_plans ORDER BY price");
$plans = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking - OrderDesk</title>
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
                        <a class="nav-link active" href="payment_tracking.php">Payment Tracking</a>
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
                        <h2>Payment Tracking</h2>
                        <p class="text-muted">Track and manage customer payments</p>
                    </div>
                    <div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                            <i class="fas fa-plus me-2"></i>Create Invoice
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

        <!-- Payment Records -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-credit-card me-2"></i>Payment Records
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Admin</th>
                                        <th>Plan</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Payment Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['invoice_number']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['full_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['plan_name']); ?></td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo formatDateTime($payment['due_date']); ?></td>
                                            <td><?php echo $payment['payment_date'] ? formatDateTime($payment['payment_date']) : 'Not paid'; ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'paid' => 'success',
                                                    'overdue' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $status_class[$payment['status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="generate_invoice.php?id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <?php if ($payment['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="confirmPayment(<?php echo $payment['id']; ?>)">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
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

    <!-- Create Invoice Modal -->
    <div class="modal fade" id="createInvoiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="admin_id" class="form-label">Admin</label>
                            <select name="admin_id" id="admin_id" class="form-select" required>
                                <option value="">Select Admin</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['full_name']); ?> 
                                        (<?php echo htmlspecialchars($admin['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="plan_id" class="form-label">Plan</label>
                            <select name="plan_id" id="plan_id" class="form-select" required onchange="updateAmount()">
                                <option value="">Select Plan</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>" data-price="<?php echo $plan['price']; ?>">
                                        <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo number_format($plan['price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_invoice" class="btn btn-success">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirm Payment Modal -->
    <div class="modal fade" id="confirmPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" id="confirmPaymentId">
                        
                        <div class="mb-3">
                            <label for="payment_date" class="form-label">Payment Date</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="">Select Method</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" 
                                      placeholder="Add any notes about this payment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="confirm_payment" class="btn btn-success">Confirm Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        function confirmPayment(paymentId) {
            document.getElementById('confirmPaymentId').value = paymentId;
            var modal = new bootstrap.Modal(document.getElementById('confirmPaymentModal'));
            modal.show();
        }
        
        function updateAmount() {
            const planSelect = document.getElementById('plan_id');
            const amountInput = document.getElementById('amount');
            const selectedOption = planSelect.options[planSelect.selectedIndex];
            
            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                amountInput.value = price;
            }
        }
        
        // Set default due date to 30 days from now
        document.getElementById('due_date').value = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    </script>
</body>
</html>
