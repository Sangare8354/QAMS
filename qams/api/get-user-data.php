<?php
// api/get-user-data.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

$userData = [];

// Get user basic info
$stmt = $conn->prepare("SELECT u.*, d.department_name, f.faculty_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id
                        LEFT JOIN faculties f ON u.faculty_id = f.id
                        WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData['profile'] = $result->fetch_assoc();

// Get current academic year
$yearResult = $conn->query("SELECT id, year_name FROM academic_years WHERE is_current = 1");
$currentYear = $yearResult->fetch_assoc();

if (!$currentYear) {
    $yearResult = $conn->query("SELECT id, year_name FROM academic_years ORDER BY id DESC LIMIT 1");
    $currentYear = $yearResult->fetch_assoc();
}

// Get current semester
$semResult = $conn->query("SELECT id, semester_number FROM semesters WHERE is_current = 1 AND academic_year_id = " . ($currentYear['id'] ?? 12));
$currentSemester = $semResult->fetch_assoc();

$userData['current_year'] = $currentYear['year_name'] ?? '2025-26';
$userData['current_semester'] = $currentSemester['semester_number'] ?? '5';

// Get courses for teacher
if ($role == 'teacher') {
    $stmt = $conn->prepare("SELECT c.*, tc.section, tc.academic_year_id, tc.semester_id,
                            ay.year_name, s.semester_number
                            FROM teacher_courses tc
                            JOIN courses c ON tc.course_id = c.id
                            JOIN academic_years ay ON tc.academic_year_id = ay.id
                            JOIN semesters s ON tc.semester_id = s.id
                            WHERE tc.teacher_id = ? AND ay.is_current = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userData['courses'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get forms status
$stmt = $conn->prepare("SELECT COUNT(*) as count, current_status 
                        FROM qams_forms 
                        WHERE teacher_id = ? 
                        GROUP BY current_status");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData['form_stats'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $userData]);
?>