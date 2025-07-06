<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getUserRole();
    $dashboard_file = $role === 'super_admin' ? 'dashboard_superadmin.php' : 'dashboard_' . $role . '.php';
    header('Location: ' . $dashboard_file);
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role, status, full_name FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Log the login activity
                logActivity($user['id'], 'login', 'User logged in from IP: ' . $_SERVER['REMOTE_ADDR']);
                
                // Redirect to appropriate dashboard
                $dashboard_file = $user['role'] === 'super_admin' ? 'dashboard_superadmin.php' : 'dashboard_' . $user['role'] . '.php';
                header('Location: ' . $dashboard_file);
                exit();
            } elseif ($user['status'] === 'pending') {
                $error = 'Your account is pending approval. Please wait for admin approval.';
            } else {
                $error = 'Your account has been suspended. Please contact support.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OrderDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5 shadow">
                    <div class="card-header text-center bg-primary text-white">
                        <h4><i class="fas fa-sign-in-alt me-2"></i>Login to OrderDesk</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p class="mb-0">Don't have an account? <a href="signup.php">Sign up here</a></p>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Credentials -->
                <div class="card mt-3 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                    </div>
                    <div class="card-body small">
                        <p class="mb-2"><strong>Super Admin:</strong><br>
                        Username: superadmin<br>
                        Password: password</p>
                        
                        <p class="mb-2"><strong>Demo Admin:</strong><br>
                        Username: demoadmin<br>
                        Password: password</p>
                        
                        <p class="mb-0"><strong>Demo Agent:</strong><br>
                        Username: demoagent<br>
                        Password: password</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
