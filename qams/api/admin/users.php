<?php
// api/admin/users.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('admin');

$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get users with filters
        $role = isset($_GET['role']) && $_GET['role'] !== 'all' ? $_GET['role'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        $query = "SELECT u.*, ut.type_name as role, d.department_name, f.faculty_name 
                  FROM users u 
                  JOIN user_types ut ON u.user_type_id = ut.id 
                  LEFT JOIN departments d ON u.department_id = d.id 
                  LEFT JOIN faculties f ON u.faculty_id = f.id
                  WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($role) {
            $query .= " AND ut.type_name = ?";
            $params[] = $role;
            $types .= "s";
        }
        if ($status === 'active') {
            $query .= " AND u.is_active = 1";
        } elseif ($status === 'inactive') {
            $query .= " AND u.is_active = 0";
        }
        
        $query .= " ORDER BY u.created_at DESC";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    case 'POST':
        // Create new user
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = $data['username'];
        $email = $username . '@edu.gh';
        $full_name = $data['full_name'];
        $role_name = $data['role'];
        $employee_id = $data['employee_id'];
        $department_id = !empty($data['department_id']) ? intval($data['department_id']) : null;
        $faculty_id = !empty($data['faculty_id']) ? intval($data['faculty_id']) : null;
        
        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Get user_type_id from role name
        $typeStmt = $conn->prepare("SELECT id FROM user_types WHERE type_name = ?");
        $typeStmt->bind_param("s", $role_name);
        $typeStmt->execute();
        $user_type_id = $typeStmt->get_result()->fetch_assoc()['id'];
        
        $password_hash = password_hash($data['password'] ?? 'Welcome123', PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, user_type_id, employee_id, department_id, faculty_id, is_active) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssssiii", $username, $email, $password_hash, $full_name, $user_type_id, $employee_id, $department_id, $faculty_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'user_id' => $stmt->insert_id, 'message' => 'User created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;
        
    case 'PUT':
        // Update user
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['id'];
        
        $updates = [];
        $params = [];
        $types = "";
        
        $allowedFields = ['full_name', 'employee_id', 'department_id', 'faculty_id', 'is_active'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= "s";
            }
        }
        
        // Handle password update separately
        if (!empty($data['password'])) {
            $newHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $updates[] = "password_hash = ?";
            $params[] = $newHash;
            $types .= "s";
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit();
        }
        
        $params[] = $userId;
        $types .= "i";
        
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;
        
    case 'DELETE':
        // Delete (deactivate) user
        $userId = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;
}

$conn->close();
?>