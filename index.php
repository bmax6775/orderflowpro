<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrderDesk - eCommerce Order Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shopping-cart me-2"></i>OrderDesk
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $_SESSION['role'] == 'super_admin' ? 'dashboard_superadmin.php' : ($_SESSION['role'] == 'admin' ? 'dashboard_admin.php' : 'dashboard_agent.php'); ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="signup.php">
                                <i class="fas fa-user-plus me-1"></i>Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <button class="btn btn-outline-light btn-sm" onclick="toggleDarkMode()">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section py-5 bg-gradient-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Streamline Your eCommerce Orders</h1>
                    <p class="lead mb-4">OrderDesk is a comprehensive order management system designed for Shopify and dropshipping businesses. Manage orders, track deliveries, and monitor agent performance all in one place.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="signup.php" class="btn btn-light btn-lg px-4 me-md-2">Get Started</a>
                        <a href="login.php" class="btn btn-outline-light btn-lg px-4">Login</a>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Sign-ups are manually reviewed and approved by our Admin team.
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-chart-line display-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="display-5 fw-bold">Key Features</h2>
                    <p class="lead text-muted">Everything you need to manage your eCommerce orders efficiently</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-upload fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title">Bulk Order Upload</h5>
                            <p class="card-text">Upload orders in bulk via CSV or add them manually. Includes demo CSV format for easy setup.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-tasks fa-3x text-success"></i>
                            </div>
                            <h5 class="card-title">Order Status Tracking</h5>
                            <p class="card-text">Track orders from New → Called → Confirmed → In Transit → Delivered/Failed with complete history.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-users fa-3x text-info"></i>
                            </div>
                            <h5 class="card-title">Agent Management</h5>
                            <p class="card-text">Assign agents to orders, track their performance, and monitor call center activities.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-camera fa-3x text-warning"></i>
                            </div>
                            <h5 class="card-title">Screenshot Upload</h5>
                            <p class="card-text">Upload proof of calls and delivery confirmations with organized screenshot management.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-chart-bar fa-3x text-danger"></i>
                            </div>
                            <h5 class="card-title">Analytics Dashboard</h5>
                            <p class="card-text">Store-wise analytics, agent performance reports, and comprehensive business insights.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-file-export fa-3x text-dark"></i>
                            </div>
                            <h5 class="card-title">Export & Reports</h5>
                            <p class="card-text">Export filtered orders to CSV/Excel and generate invoices for manual payment processing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="display-5 fw-bold">Pricing Plans</h2>
                    <p class="lead text-muted">Choose the perfect plan for your business needs</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-primary text-white text-center">
                            <h5 class="card-title mb-0">Basic</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="pricing-price mb-3">
                                <span class="h2 fw-bold">$29</span>
                                <span class="text-muted">/month</span>
                            </div>
                            <ul class="list-unstyled mb-4">
                                <li><i class="fas fa-check text-success me-2"></i>Up to 3 Agents</li>
                                <li><i class="fas fa-check text-success me-2"></i>500 Orders/month</li>
                                <li><i class="fas fa-check text-success me-2"></i>Basic Analytics</li>
                                <li><i class="fas fa-check text-success me-2"></i>Email Support</li>
                                <li><i class="fas fa-check text-success me-2"></i>CSV Export</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-success text-white text-center">
                            <h5 class="card-title mb-0">Professional</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="pricing-price mb-3">
                                <span class="h2 fw-bold">$59</span>
                                <span class="text-muted">/month</span>
                            </div>
                            <ul class="list-unstyled mb-4">
                                <li><i class="fas fa-check text-success me-2"></i>Up to 10 Agents</li>
                                <li><i class="fas fa-check text-success me-2"></i>2000 Orders/month</li>
                                <li><i class="fas fa-check text-success me-2"></i>Advanced Analytics</li>
                                <li><i class="fas fa-check text-success me-2"></i>Priority Support</li>
                                <li><i class="fas fa-check text-success me-2"></i>All Export Formats</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow border-warning">
                        <div class="card-header bg-warning text-dark text-center">
                            <h5 class="card-title mb-0">Enterprise</h5>
                            <small class="badge bg-danger">Most Popular</small>
                        </div>
                        <div class="card-body text-center">
                            <div class="pricing-price mb-3">
                                <span class="h2 fw-bold">$99</span>
                                <span class="text-muted">/month</span>
                            </div>
                            <ul class="list-unstyled mb-4">
                                <li><i class="fas fa-check text-success me-2"></i>Unlimited Agents</li>
                                <li><i class="fas fa-check text-success me-2"></i>Unlimited Orders</li>
                                <li><i class="fas fa-check text-success me-2"></i>Premium Analytics</li>
                                <li><i class="fas fa-check text-success me-2"></i>24/7 Support</li>
                                <li><i class="fas fa-check text-success me-2"></i>Custom Features</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow">
                        <div class="card-header bg-info text-white text-center">
                            <h5 class="card-title mb-0">Custom</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="pricing-price mb-3">
                                <span class="h2 fw-bold">Custom</span>
                                <span class="text-muted">pricing</span>
                            </div>
                            <ul class="list-unstyled mb-4">
                                <li><i class="fas fa-check text-success me-2"></i>Custom Limits</li>
                                <li><i class="fas fa-check text-success me-2"></i>Custom Features</li>
                                <li><i class="fas fa-check text-success me-2"></i>White Label</li>
                                <li><i class="fas fa-check text-success me-2"></i>Dedicated Support</li>
                                <li><i class="fas fa-check text-success me-2"></i>API Access</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>OrderDesk</h5>
                    <p>Professional eCommerce Order Management System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2025 OrderDesk. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
