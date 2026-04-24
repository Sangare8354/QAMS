<?php
// api/dept_head/pending.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dept_head');

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get department_id of this dept head
$stmt = $conn->prepare("SELECT department_id, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$deptHead = $stmt->get_result()->fetch_assoc();

if (!$deptHead || !$deptHead['department_id']) {
    echo json_encode([
        'success' => false, 
        'message' => 'You are not assigned to any department. Please contact the administrator.',
        'forms' => [],
        'total_pending' => 0
    ]);
    exit();
}

$departmentId = $deptHead['department_id'];

// Get filter parameters from request
$filterDepartment = isset($_GET['department']) ? $_GET['department'] : null;
$filterAcademicYear = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
$filterSemester = isset($_GET['semester']) ? $_GET['semester'] : null;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : null;

// Build the query with filters
$query = "
    SELECT 
        f.id as form_id,
        f.form_number,
        f.current_status,
        f.submitted_at,
        f.rejection_reason,
        t.id as teacher_id,
        t.full_name AS teacher_name,
        t.employee_id,
        c.course_code,
        c.course_title,
        tc.section,
        ay.year_name,
        s.semester_number,
        d.department_name
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    JOIN courses c ON f.course_id = c.id
    JOIN teacher_courses tc ON tc.teacher_id = t.id AND tc.course_id = c.id 
        AND tc.academic_year_id = f.academic_year_id AND tc.semester_id = f.semester_id
    JOIN academic_years ay ON f.academic_year_id = ay.id
    JOIN semesters s ON f.semester_id = s.id
    JOIN departments d ON t.department_id = d.id
    WHERE t.department_id = ?
";

$params = [$departmentId];
$types = "i";

// Apply department filter (if specified and different from current dept)
if ($filterDepartment && $filterDepartment !== $departmentId) {
    $query .= " AND d.department_name = ?";
    $params[] = $filterDepartment;
    $types .= "s";
}

// Apply academic year filter
if ($filterAcademicYear && $filterAcademicYear !== '') {
    $query .= " AND ay.year_name = ?";
    $params[] = $filterAcademicYear;
    $types .= "s";
}

// Apply semester filter
if ($filterSemester && $filterSemester !== '') {
    $query .= " AND s.semester_number = ?";
    $params[] = $filterSemester;
    $types .= "i";
}

// Apply status filter
if ($filterStatus && $filterStatus !== '') {
    $query .= " AND f.current_status = ?";
    $params[] = $filterStatus;
    $types .= "s";
} else {
    // Default to pending_dept if no status filter
    $query .= " AND f.current_status = 'pending_dept'";
}

$query .= " ORDER BY f.submitted_at ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$forms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode([
    'success' => true,
    'dept_head' => [
        'id' => $userId,
        'name' => $deptHead['full_name'],
        'department_id' => $departmentId
    ],
    'filters' => [
        'department' => $filterDepartment,
        'academic_year' => $filterAcademicYear,
        'semester' => $filterSemester,
        'status' => $filterStatus
    ],
    'forms' => $forms,
    'total_pending' => count($forms)
]);
?>