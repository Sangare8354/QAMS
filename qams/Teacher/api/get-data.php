<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple test – uncomment to verify the script runs
// echo json_encode(['debug' => 'script started']); exit;

require_once '../../config/database.php';
session_start();

$response = ['success' => false, 'data' => [], 'message' => ''];

// Check session
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

// Check role
if ($_SESSION['user_role'] !== 'teacher') {
    $response['message'] = 'Unauthorized role: ' . $_SESSION['user_role'];
    echo json_encode($response);
    exit;
}

$conn = getConnection();
if (!$conn) {
    $response['message'] = 'Database connection failed';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];

// Get filter parameters
$academicYear = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;

// Get teacher info
$stmt = $conn->prepare("SELECT u.*, d.department_name, f.faculty_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id
                        LEFT JOIN faculties f ON u.faculty_id = f.id
                        WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

// Get current academic year and semester
$currentYear = $conn->query("SELECT id, year_name FROM academic_years WHERE is_current = 1")->fetch_assoc();
$currentSemester = $currentYear ? $conn->query("SELECT id, semester_number FROM semesters WHERE is_current = 1 AND academic_year_id = " . $currentYear['id'])->fetch_assoc() : null;

// Build query
$query = "
    SELECT 
        c.*, 
        tc.section, 
        tc.id as teacher_course_id,
        f.id as form_id,
        f.current_status as form_status,
        f.submitted_at,
        f.dept_approved_at,
        f.dean_approved_at,
        f.qams_approved_at,
        f.rejection_reason,
        ay.year_name,
        s.semester_number
    FROM teacher_courses tc
    JOIN courses c ON tc.course_id = c.id
    JOIN academic_years ay ON tc.academic_year_id = ay.id
    JOIN semesters s ON tc.semester_id = s.id
    LEFT JOIN qams_forms f ON (
        f.teacher_id = tc.teacher_id 
        AND f.course_id = tc.course_id 
        AND f.academic_year_id = tc.academic_year_id 
        AND f.semester_id = tc.semester_id
    )
    WHERE tc.teacher_id = ? 
";

$params = [$userId];
$types = "i";

if ($academicYear && $academicYear !== 'all') {
    $query .= " AND ay.year_name = ?";
    $params[] = $academicYear;
    $types .= "s";
} elseif ($currentYear) {
    $query .= " AND ay.is_current = 1";
} else {
    // fallback: use the most recent year
    $query .= " AND ay.id = (SELECT id FROM academic_years ORDER BY id DESC LIMIT 1)";
}

if ($semester && $semester !== 'all') {
    $query .= " AND s.semester_number = ?";
    $params[] = $semester;
    $types .= "i";
} elseif ($currentSemester) {
    $query .= " AND s.is_current = 1";
}

$query .= " ORDER BY c.course_code ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    $response['message'] = 'Query error: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

$response['success'] = true;
$response['data'] = [
    'teacher' => $teacher,
    'currentYear' => $currentYear,
    'currentSemester' => $currentSemester,
    'courses' => $courses,
];

echo json_encode($response);