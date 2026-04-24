<?php
// api/dept_head/view-form.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dept_head');

$formId = $_GET['id'] ?? 0;

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID required']);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get department_id of this dept head with better error handling
$deptStmt = $conn->prepare("SELECT department_id, full_name FROM users WHERE id = ?");
$deptStmt->bind_param("i", $userId);
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
$deptHead = $deptResult->fetch_assoc();

// Debug: Check if dept head exists
if (!$deptHead) {
    echo json_encode(['success' => false, 'message' => 'Department head user not found']);
    exit();
}

// Debug: Check if department_id exists
if (!isset($deptHead['department_id']) || empty($deptHead['department_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'You are not assigned to any department. Please contact the administrator to assign your department.',
        'debug' => [
            'user_id' => $userId,
            'user_name' => $deptHead['full_name'],
            'department_id' => $deptHead['department_id'] ?? 'NULL'
        ]
    ]);
    exit();
}

$departmentId = $deptHead['department_id'];

// Get form details
$query = "
    SELECT 
        f.*,
        t.full_name AS teacher_name,
        t.employee_id AS teacher_emp_id,
        t.email AS teacher_email,
        t.department_id AS teacher_department_id,
        c.course_code,
        c.course_title,
        c.credit_hours,
        tc.section,
        ay.year_name,
        s.semester_number,
        d.department_name,
        fac.faculty_name
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    JOIN courses c ON f.course_id = c.id
    JOIN teacher_courses tc ON tc.teacher_id = t.id AND tc.course_id = c.id 
        AND tc.academic_year_id = f.academic_year_id AND tc.semester_id = f.semester_id
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

// Debug: Check department match
if ($form['teacher_department_id'] != $departmentId) {
    echo json_encode([
        'success' => false, 
        'message' => 'You do not have permission to view this form. This form belongs to a different department.',
        'debug' => [
            'your_department' => $departmentId,
            'form_department' => $form['teacher_department_id'],
            'teacher' => $form['teacher_name']
        ]
    ]);
    exit();
}

// Function to clean and get filename from any path format
function getFilenameFromPath($path) {
    if (!$path) return null;
    // Get just the filename (remove any directory path)
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $filename = end($parts);
    // Also handle forward slashes
    $parts = explode('/', $path);
    $filename = end($parts);
    return $filename;
}

// Get just the filenames from stored paths
$attendanceFile = getFilenameFromPath($form['attendance_sheet']);
$midtermFile = getFilenameFromPath($form['midterm_question']);
$finalFile = getFilenameFromPath($form['final_question']);

// Build the web-accessible URLs
$baseUrl = '/qams/uploads/';

$form['attendance_url'] = $attendanceFile ? $baseUrl . 'attendance/' . $attendanceFile : null;
$form['midterm_url'] = $midtermFile ? $baseUrl . 'midterm/' . $midtermFile : null;
$form['final_url'] = $finalFile ? $baseUrl . 'final/' . $finalFile : null;

$conn->close();

echo json_encode([
    'success' => true,
    'form' => $form
]);
?>