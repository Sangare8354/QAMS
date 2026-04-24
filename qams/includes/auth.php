<?php
// includes/auth.php
session_start();

require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

function isAdmin() {
    return hasRole('admin');
}

function isTeacher() {
    return hasRole('teacher');
}

function isDeptHead() {
    return hasRole('dept_head');
}

function isDean() {
    return hasRole('dean');
}

function isQamsHead() {
    return hasRole('qams_head');
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Check if this is an API request (JSON expected)
        $isApi = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
        
        if ($isApi) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit();
        } else {
            header('Location: /qams/Login/index.html');
            exit();
        }
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role) && !isAdmin()) {
        $isApi = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
        
        if ($isApi) {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Unauthorized role. Required: ' . $role . ', Current: ' . ($_SESSION['user_role'] ?? 'none')
            ]);
            exit();
        } else {
            die('Access Denied');
        }
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['user_role'],
            'email' => $_SESSION['email'],
            'employee_id' => $_SESSION['employee_id'] ?? ''
        ];
    }
    return null;
}

function login($email, $password) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT u.*, ut.type_name as role FROM users u 
                            JOIN user_types ut ON u.user_type_id = ut.id 
                            WHERE u.email = ? AND u.is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['faculty_id'] = $user['faculty_id'];
            
            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            $stmt->close();
            $conn->close();
            return true;
        }
    }
    $stmt->close();
    $conn->close();
    return false;
}

function logout() {
    session_destroy();
    header('Location: /qams/Login/index.html');
    exit();
}

function changePassword($userId, $currentPassword, $newPassword) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (password_verify($currentPassword, $user['password_hash'])) {
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("si", $newHash, $userId);
        $result = $updateStmt->execute();
        $updateStmt->close();
        $stmt->close();
        $conn->close();
        return $result;
    }
    $stmt->close();
    $conn->close();
    return false;
}
?>