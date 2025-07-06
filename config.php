<?php
// Database configuration - Using SQLite for easy setup
$database_path = 'orderdesk.db';

try {
    $pdo = new PDO("sqlite:$database_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Application settings
define('APP_NAME', 'OrderDesk');
define('APP_VERSION', '1.0.0');
define('UPLOAD_PATH', 'uploads/');
define('SCREENSHOT_PATH', 'uploads/screenshots/');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(SCREENSHOT_PATH)) {
    mkdir(SCREENSHOT_PATH, 0755, true);
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        $user_role = getUserRole();
        $dashboard_file = $user_role === 'super_admin' ? 'dashboard_superadmin.php' : 'dashboard_' . $user_role . '.php';
        header('Location: ' . $dashboard_file);
        exit();
    }
}

function logActivity($user_id, $action, $details = '') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (?, ?, ?, datetime('now'))");
    $stmt->execute([$user_id, $action, $details]);
}

function getPlanLimits($plan_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM pricing_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

function getStatusBadge($status) {
    $badges = [
        'new' => '<span class="badge bg-primary">New</span>',
        'called' => '<span class="badge bg-info">Called</span>',
        'confirmed' => '<span class="badge bg-warning">Confirmed</span>',
        'in_transit' => '<span class="badge bg-secondary">In Transit</span>',
        'delivered' => '<span class="badge bg-success">Delivered</span>',
        'failed' => '<span class="badge bg-danger">Failed</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-light">Unknown</span>';
}
?>
