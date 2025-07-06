<?php
session_start();
require_once 'config.php';

requireRole('super_admin');

$invoice_id = $_GET['id'] ?? 0;

// Get invoice details
$stmt = $pdo->prepare("SELECT pr.*, u.full_name, u.email, u.phone, pp.name as plan_name 
                       FROM payment_records pr 
                       JOIN users u ON pr.admin_id = u.id 
                       JOIN pricing_plans pp ON pr.plan_id = pp.id 
                       WHERE pr.id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

$invoice_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime($invoice['due_date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
        }
        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .invoice-footer {
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row no-print">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Invoice Preview</h2>
                    <div>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Invoice
                        </button>
                        <a href="payment_tracking.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Content -->
        <div class="card">
            <div class="card-body">
                <!-- Invoice Header -->
                <div class="invoice-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h1 class="h3 text-primary">OrderDesk</h1>
                            <p class="mb-0">eCommerce Order Management System</p>
                            <p class="mb-0">Email: admin@orderdesk.com</p>
                            <p class="mb-0">Phone: +1 (555) 123-4567</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h2 class="h4">INVOICE</h2>
                            <p class="mb-1"><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                            <p class="mb-1"><strong>Date:</strong> <?php echo $invoice_date; ?></p>
                            <p class="mb-1"><strong>Due Date:</strong> <?php echo $due_date; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Bill To -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Bill To:</h5>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
                        <p class="mb-1"><?php echo htmlspecialchars($invoice['email']); ?></p>
                        <?php if ($invoice['phone']): ?>
                            <p class="mb-1"><?php echo htmlspecialchars($invoice['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-<?php echo $invoice['status'] === 'paid' ? 'success' : 'warning'; ?>">
                            <strong>Status:</strong> <?php echo ucfirst($invoice['status']); ?>
                            <?php if ($invoice['status'] === 'paid' && $invoice['payment_date']): ?>
                                <br><strong>Paid on:</strong> <?php echo formatDateTime($invoice['payment_date']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="table-responsive mb-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Period</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($invoice['plan_name']); ?> Plan</strong>
                                    <br>
                                    <small class="text-muted">Monthly subscription for OrderDesk service</small>
                                </td>
                                <td>
                                    <?php echo date('M Y', strtotime($invoice['created_at'])); ?>
                                </td>
                                <td class="text-end">$<?php echo number_format($invoice['amount'], 2); ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Subtotal:</th>
                                <th class="text-end">$<?php echo number_format($invoice['amount'], 2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="2" class="text-end">Tax (0%):</th>
                                <th class="text-end">$0.00</th>
                            </tr>
                            <tr class="table-primary">
                                <th colspan="2" class="text-end">Total:</th>
                                <th class="text-end">$<?php echo number_format($invoice['amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Payment Information -->
                <div class="row">
                    <div class="col-md-6">
                        <h5>Payment Information</h5>
                        <p>Please make payment to the following account:</p>
                        <ul class="list-unstyled">
                            <li><strong>Bank:</strong> OrderDesk Bank</li>
                            <li><strong>Account Name:</strong> OrderDesk Inc.</li>
                            <li><strong>Account Number:</strong> 1234567890</li>
                            <li><strong>Routing Number:</strong> 987654321</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>Terms & Conditions</h5>
                        <ul class="small">
                            <li>Payment is due within 30 days of invoice date</li>
                            <li>Late payments may result in service suspension</li>
                            <li>All payments are non-refundable</li>
                            <li>Service continues until cancelled</li>
                        </ul>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($invoice['notes']): ?>
                    <div class="invoice-footer">
                        <h5>Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="invoice-footer text-center">
                    <p class="text-muted mb-0">Thank you for your business!</p>
                    <p class="text-muted">For questions about this invoice, please contact us at admin@orderdesk.com</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
