<?php
// api/admin/hierarchy.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('admin');

$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all hierarchy mappings with details
        $yearId = isset($_GET['year_id']) ? intval($_GET['year_id']) : null;
        
        $query = "
            SELECT hm.*, 
                   t.full_name as teacher_name, 
                   t.employee_id as teacher_emp_id,
                   h.full_name as dept_head_name,
                   d.full_name as dean_name,
                   q.full_name as qams_head_name,
                   ay.year_name
            FROM hierarchy_mappings hm
            JOIN users t ON hm.teacher_id = t.id
            JOIN users h ON hm.dept_head_id = h.id
            JOIN users d ON hm.dean_id = d.id
            JOIN users q ON hm.qams_head_id = q.id
            JOIN academic_years ay ON hm.academic_year_id = ay.id
            WHERE hm.is_active = 1
        ";
        
        if ($yearId) {
            $query .= " AND hm.academic_year_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $yearId);
            $stmt->execute();
            $mappings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $mappings = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
        }
        
        // Get dropdown options
        $teachers = $conn->query("SELECT id, full_name, employee_id FROM users WHERE user_type_id = (SELECT id FROM user_types WHERE type_name = 'teacher') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
        $deptHeads = $conn->query("SELECT id, full_name FROM users WHERE user_type_id = (SELECT id FROM user_types WHERE type_name = 'dept_head') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
        $deans = $conn->query("SELECT id, full_name FROM users WHERE user_type_id = (SELECT id FROM user_types WHERE type_name = 'dean') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
        $qamsHeads = $conn->query("SELECT id, full_name FROM users WHERE user_type_id = (SELECT id FROM user_types WHERE type_name = 'qams_head') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
        $years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC")->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'mappings' => $mappings,
            'teachers' => $teachers,
            'dept_heads' => $deptHeads,
            'deans' => $deans,
            'qams_heads' => $qamsHeads,
            'years' => $years
        ]);
        break;
        
    case 'POST':
        // Create new mapping
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check if mapping already exists for this teacher and year
        $checkStmt = $conn->prepare("SELECT id FROM hierarchy_mappings WHERE teacher_id = ? AND academic_year_id = ? AND is_active = 1");
        $checkStmt->bind_param("ii", $data['teacher_id'], $data['academic_year_id']);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This teacher already has a mapping for this academic year']);
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO hierarchy_mappings (teacher_id, dept_head_id, dean_id, qams_head_id, academic_year_id) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiii", $data['teacher_id'], $data['dept_head_id'], $data['dean_id'], $data['qams_head_id'], $data['academic_year_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Hierarchy mapping created']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;
        
    case 'DELETE':
        // Delete mapping
        $id = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM hierarchy_mappings WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Mapping deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        break;
}

$conn->close();
?>