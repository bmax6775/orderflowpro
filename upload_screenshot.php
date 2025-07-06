<?php
session_start();
require_once 'config.php';

requireLogin();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    
    // Verify order access
    $user_role = $_SESSION['role'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT id, admin_id, assigned_agent_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $response['message'] = 'Order not found.';
    } elseif ($user_role === 'admin' && $order['admin_id'] != $user_id) {
        $response['message'] = 'Access denied.';
    } elseif ($user_role === 'agent' && $order['assigned_agent_id'] != $user_id) {
        $response['message'] = 'Access denied.';
    } else {
        // Handle file upload
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['screenshot'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $file['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $response['message'] = 'Only JPEG and PNG files are allowed.';
            } else {
                // Validate file size (max 5MB)
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $max_size) {
                    $response['message'] = 'File size must be less than 5MB.';
                } else {
                    // Create order directory
                    $order_dir = SCREENSHOT_PATH . $order_id . '/';
                    if (!file_exists($order_dir)) {
                        mkdir($order_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $order_id . '/' . uniqid() . '_' . time() . '.' . $file_extension;
                    $full_path = SCREENSHOT_PATH . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $full_path)) {
                        // Save to database
                        $stmt = $pdo->prepare("INSERT INTO screenshots (order_id, filename, original_filename, uploaded_by) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$order_id, $filename, $file['name'], $user_id])) {
                            $response['success'] = true;
                            $response['message'] = 'Screenshot uploaded successfully!';
                            
                            // Log activity
                            logActivity($user_id, 'screenshot_uploaded', "Uploaded screenshot for order ID: $order_id");
                        } else {
                            $response['message'] = 'Failed to save screenshot record.';
                            unlink($full_path); // Delete uploaded file
                        }
                    } else {
                        $response['message'] = 'Failed to upload file. Please try again.';
                    }
                }
            }
        } else {
            $response['message'] = 'Please select a file to upload.';
        }
    }
}

// If AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// For regular form submission, redirect back to order page
if ($response['success']) {
    $_SESSION['success_message'] = $response['message'];
} else {
    $_SESSION['error_message'] = $response['message'];
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'manage_orders.php';
header('Location: ' . $redirect_url);
exit();
?>
