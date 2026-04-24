<?php
// api/qams_head/view-form.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('qams_head');

$formId = $_GET['id'] ?? 0;

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID required']);
    exit();
}

$conn = getConnection();

// Get form details
$query = "
    SELECT 
        f.*,
        t.full_name AS teacher_name,
        t.employee_id AS teacher_emp_id,
        t.email AS teacher_email,
        c.course_code,
        c.course_title,
        c.credit_hours,
        tc.section,
        ay.year_name,
        s.semester_number,
        d.department_name,
        fac.faculty_name,
        h.full_name as dept_head_name,
        h.email as dept_head_email,
        de.full_name as dean_name,
        de.email as dean_email
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    JOIN courses c ON f.course_id = c.id
    JOIN teacher_courses tc ON tc.teacher_id = t.id AND tc.course_id = c.id 
        AND tc.academic_year_id = f.academic_year_id AND tc.semester_id = f.semester_id
    JOIN academic_years ay ON f.academic_year_id = ay.id
    JOIN semesters s ON f.semester_id = s.id
    JOIN departments d ON t.department_id = d.id
    JOIN faculties fac ON d.faculty_id = fac.id
    JOIN hierarchy_mappings hm ON t.id = hm.teacher_id AND hm.academic_year_id = f.academic_year_id
    JOIN users h ON hm.dept_head_id = h.id
    JOIN users de ON hm.dean_id = de.id
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

// Function to get filename from path
function getFilenameFromPath($path) {
    if (!$path) return null;
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

$conn->close();

echo json_encode([
    'success' => true,
    'form' => $form
]);
?>