<?php
// api/login.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (login($email, $password)) {
        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['user'] = [
            'name' => $_SESSION['full_name'],
            'role' => $_SESSION['user_role'],
            'email' => $_SESSION['email']
        ];
        $response['redirect'] = getRedirectUrl($_SESSION['user_role']);
    } else {
        $response['message'] = 'Invalid email or password';
    }
}

function getRedirectUrl($role) {
    switch($role) {
        case 'admin': return '/qams/Admin/admin-dashboard.html';
        case 'teacher': return '/qams/Teacher/teacher-panel.html';
        case 'dept_head': return '/qams/Dept_Head/dept_head-panel.html';
        case 'dean': return '/qams/Dean/dean-panel.html';
        case 'qams_head': return '/qams/QAMS_Head/QAMS_head-panel.html';
        default: return '/qams/Login/index.html';
    }
}

echo json_encode($response);
?>