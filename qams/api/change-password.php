<?php
// api/change-password.php
header('Content-Type: application/json');
require_once '../config/database.php';
session_start();

$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$current = $data['current'] ?? '';
$new = $data['new'] ?? '';
$confirm = $data['confirm'] ?? '';

// Validate input
if (empty($current) || empty($new) || empty($confirm)) {
    $response['message'] = 'All fields are required';
    echo json_encode($response);
    exit();
}

if ($new !== $confirm) {
    $response['message'] = 'New passwords do not match';
    echo json_encode($response);
    exit();
}

if (strlen($new) < 8) {
    $response['message'] = 'Password must be at least 8 characters long';
    echo json_encode($response);
    exit();
}

if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
    $response['message'] = 'Password must contain at least one letter and one number';
    echo json_encode($response);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get current password hash
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $response['message'] = 'User not found';
    echo json_encode($response);
    exit();
}

// Verify current password
if (!password_verify($current, $user['password_hash'])) {
    $response['message'] = 'Current password is incorrect';
    echo json_encode($response);
    exit();
}

// Hash new password
$newHash = password_hash($new, PASSWORD_BCRYPT);

// Update password
$updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
$updateStmt->bind_param("si", $newHash, $userId);

if ($updateStmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Password changed successfully. Please login again.';
    
    // Optional: Log the user out after password change for security
    // session_destroy();
} else {
    $response['message'] = 'Failed to update password: ' . $conn->error;
}

$updateStmt->close();
$stmt->close();
$conn->close();

echo json_encode($response);
?>