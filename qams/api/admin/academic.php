<?php
// api/admin/academic.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('admin');

$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? 'years';

switch ($type) {
    case 'current':
        // Get current academic year and semester
        $query = "
            SELECT 
                ay.id as year_id,
                ay.year_name as current_year,
                ay.start_date as year_start,
                ay.end_date as year_end,
                s.id as semester_id,
                s.semester_number as current_semester,
                s.start_date as semester_start,
                s.end_date as semester_end
            FROM academic_years ay
            LEFT JOIN semesters s ON s.academic_year_id = ay.id AND s.is_current = 1
            WHERE ay.is_current = 1
            LIMIT 1
        ";
        $result = $conn->query($query);
        $current = $result->fetch_assoc();
        
        if (!$current) {
            // Fallback - get the latest year and its latest semester
            $fallbackQuery = "
                SELECT 
                    ay.id as year_id,
                    ay.year_name as current_year,
                    ay.start_date as year_start,
                    ay.end_date as year_end,
                    s.id as semester_id,
                    s.semester_number as current_semester,
                    s.start_date as semester_start,
                    s.end_date as semester_end
                FROM academic_years ay
                LEFT JOIN semesters s ON s.academic_year_id = ay.id
                ORDER BY ay.id DESC, s.semester_number DESC
                LIMIT 1
            ";
            $result = $conn->query($fallbackQuery);
            $current = $result->fetch_assoc();
        }
        
        echo json_encode(['success' => true, 'current' => $current]);
        break;
        
    case 'years':
        if ($method === 'GET') {
            $years = $conn->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'years' => $years]);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // If setting as current, unset others
            if ($data['is_current'] == 1) {
                $conn->query("UPDATE academic_years SET is_current = 0");
            }
            
            $stmt = $conn->prepare("INSERT INTO academic_years (year_name, start_date, end_date, is_current) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $data['year_name'], $data['start_date'], $data['end_date'], $data['is_current']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Academic year added']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } elseif ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            
            if (isset($data['is_current']) && $data['is_current'] == 1) {
                $conn->query("UPDATE academic_years SET is_current = 0");
            }
            
            $updates = [];
            $params = [];
            $types = "";
            foreach (['year_name', 'start_date', 'end_date', 'is_current'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                    $types .= "s";
                }
            }
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare("UPDATE academic_years SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Academic year updated']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM academic_years WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Academic year deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        }
        break;
        
    case 'semesters':
        if ($method === 'GET') {
            $yearId = $_GET['year_id'] ?? null;
            if ($yearId) {
                $stmt = $conn->prepare("SELECT * FROM semesters WHERE academic_year_id = ? ORDER BY semester_number");
                $stmt->bind_param("i", $yearId);
                $stmt->execute();
                $semesters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $semesters = $conn->query("SELECT s.*, ay.year_name FROM semesters s JOIN academic_years ay ON s.academic_year_id = ay.id ORDER BY ay.year_name DESC, s.semester_number")->fetch_all(MYSQLI_ASSOC);
            }
            echo json_encode(['success' => true, 'semesters' => $semesters]);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // If setting as current, unset others in same year
            if ($data['is_current'] == 1) {
                $conn->query("UPDATE semesters SET is_current = 0 WHERE academic_year_id = " . $data['academic_year_id']);
            }
            
            $stmt = $conn->prepare("INSERT INTO semesters (semester_name, semester_number, academic_year_id, start_date, end_date, is_current) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siissi", $data['semester_name'], $data['semester_number'], $data['academic_year_id'], $data['start_date'], $data['end_date'], $data['is_current']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Semester added']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } elseif ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            
            $updates = [];
            $params = [];
            $types = "";
            
            if (isset($data['start_date'])) {
                $updates[] = "start_date = ?";
                $params[] = $data['start_date'];
                $types .= "s";
            }
            if (isset($data['end_date'])) {
                $updates[] = "end_date = ?";
                $params[] = $data['end_date'];
                $types .= "s";
            }
            if (isset($data['is_current'])) {
                if ($data['is_current'] == 1) {
                    // Get the academic year of this semester
                    $yearStmt = $conn->prepare("SELECT academic_year_id FROM semesters WHERE id = ?");
                    $yearStmt->bind_param("i", $id);
                    $yearStmt->execute();
                    $yearResult = $yearStmt->get_result()->fetch_assoc();
                    if ($yearResult) {
                        $conn->query("UPDATE semesters SET is_current = 0 WHERE academic_year_id = " . $yearResult['academic_year_id']);
                    }
                }
                $updates[] = "is_current = ?";
                $params[] = $data['is_current'];
                $types .= "i";
            }
            
            if (empty($updates)) {
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                break;
            }
            
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare("UPDATE semesters SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Semester updated']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        }
        break;
        
    case 'deadlines':
        if ($method === 'GET') {
            $deadlines = $conn->query("SELECT d.*, ay.year_name, s.semester_name FROM academic_deadlines d 
                                       LEFT JOIN academic_years ay ON d.academic_year_id = ay.id 
                                       LEFT JOIN semesters s ON d.semester_id = s.id 
                                       ORDER BY d.deadline_date DESC")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'deadlines' => $deadlines]);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO academic_deadlines (form_type, academic_year_id, semester_id, deadline_date, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("siiss", $data['form_type'], $data['academic_year_id'], $data['semester_id'], $data['deadline_date'], $data['description']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Deadline added']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM academic_deadlines WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Deadline deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        }
        break;
}

$conn->close();
?>