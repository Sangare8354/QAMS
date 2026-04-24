<?php
// api/dean/view-form.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dean');

$formId = $_GET['id'] ?? 0;

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID required']);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get faculty_id of this dean
$deptStmt = $conn->prepare("SELECT faculty_id, full_name FROM users WHERE id = ?");
$deptStmt->bind_param("i", $userId);
$deptStmt->execute();
$dean = $deptStmt->get_result()->fetch_assoc();

if (!$dean || !$dean['faculty_id']) {
    echo json_encode(['success' => false, 'message' => 'You are not assigned to any faculty']);
    exit();
}

$facultyId = $dean['faculty_id'];

// Get form details - simplified query first to debug
$query = "
    SELECT 
        f.*,
        t.full_name AS teacher_name,
        t.employee_id AS teacher_emp_id,
        t.email AS teacher_email,
        c.course_code,
        c.course_title,
        c.credit_hours,
        ay.year_name,
        s.semester_number,
        d.department_name,
        d.id as department_id,
        fac.faculty_name,
        fac.id as faculty_id
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    JOIN courses c ON f.course_id = c.id
    JOIN academic_years ay ON f.academic_year_id = ay.id
    JOIN semesters s ON f.semester_id = s.id
    JOIN departments d ON t.department_id = d.id
    JOIN faculties fac ON d.faculty_id = fac.id
    WHERE f.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found']);
    exit();
}

// Verify this form belongs to dean's faculty
if ($form['faculty_id'] != $facultyId) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to view this form']);
    exit();
}

// Get dept head name separately
$deptHeadQuery = $conn->prepare("
    SELECT h.full_name as dept_head_name, h.email as dept_head_email
    FROM hierarchy_mappings hm
    JOIN users h ON hm.dept_head_id = h.id
    WHERE hm.teacher_id = ? AND hm.academic_year_id = ? AND hm.is_active = 1
");
$deptHeadQuery->bind_param("ii", $form['teacher_id'], $form['academic_year_id']);
$deptHeadQuery->execute();
$deptHeadResult = $deptHeadQuery->get_result();
$deptHead = $deptHeadResult->fetch_assoc();

$form['dept_head_name'] = $deptHead['dept_head_name'] ?? 'N/A';
$form['dept_head_email'] = $deptHead['dept_head_email'] ?? 'N/A';

// Get section from teacher_courses
$sectionQuery = $conn->prepare("
    SELECT section 
    FROM teacher_courses 
    WHERE teacher_id = ? AND course_id = ? AND academic_year_id = ? AND semester_id = ?
");
$sectionQuery->bind_param("iiii", $form['teacher_id'], $form['course_id'], $form['academic_year_id'], $form['semester_id']);
$sectionQuery->execute();
$sectionResult = $sectionQuery->get_result();
$section = $sectionResult->fetch_assoc();
$form['section'] = $section['section'] ?? 'A';

// Function to get filename from path
function getFilenameFromPath($path) {
    if (!$path) return null;
    // Get just the filename (remove any directory path)
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $filename = end($parts);
    $parts = explode('/', $path);
    $filename = end($parts);
    return $filename;
}

// Get file URLs
$attendanceFile = getFilenameFromPath($form['attendance_sheet']);
$midtermFile = getFilenameFromPath($form['midterm_question']);
$finalFile = getFilenameFromPath($form['final_question']);

$baseUrl = '/qams/uploads/';

$form['attendance_url'] = $attendanceFile ? $baseUrl . 'attendance/' . $attendanceFile : null;
$form['midterm_url'] = $midtermFile ? $baseUrl . 'midterm/' . $midtermFile : null;
$form['final_url'] = $finalFile ? $baseUrl . 'final/' . $finalFile : null;

// Check if files exist
$uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/qams/uploads/';
$form['attendance_exists'] = $attendanceFile && file_exists($uploadPath . 'attendance/' . $attendanceFile);
$form['midterm_exists'] = $midtermFile && file_exists($uploadPath . 'midterm/' . $midtermFile);
$form['final_exists'] = $finalFile && file_exists($uploadPath . 'final/' . $finalFile);

$conn->close();

echo json_encode([
    'success' => true,
    'form' => $form
]);
?>