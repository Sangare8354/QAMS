<?php
// api/qams_head/pending.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('qams_head');

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get filter parameters
$filterFaculty = isset($_GET['faculty']) ? $_GET['faculty'] : null;
$filterDepartment = isset($_GET['department']) ? $_GET['department'] : null;
$filterAcademicYear = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
$filterSemester = isset($_GET['semester']) ? $_GET['semester'] : null;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : null;

// Build query - get forms pending QAMS Head approval
$query = "
    SELECT 
        f.id as form_id,
        f.form_number,
        f.current_status,
        f.submitted_at,
        f.dept_approved_at,
        f.dean_approved_at,
        t.id as teacher_id,
        t.full_name AS teacher_name,
        t.employee_id,
        c.course_code,
        c.course_title,
        tc.section,
        ay.year_name,
        s.semester_number,
        d.department_name,
        d.id as department_id,
        fac.faculty_name,
        fac.id as faculty_id,
        h.full_name as dept_head_name,
        de.full_name as dean_name
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
    WHERE 1=1
";

$params = [];
$types = "";

// Apply filters
if ($filterFaculty && $filterFaculty !== '') {
    $query .= " AND fac.faculty_name = ?";
    $params[] = $filterFaculty;
    $types .= "s";
}
if ($filterDepartment && $filterDepartment !== '') {
    $query .= " AND d.department_name = ?";
    $params[] = $filterDepartment;
    $types .= "s";
}
if ($filterAcademicYear && $filterAcademicYear !== '') {
    $query .= " AND ay.year_name = ?";
    $params[] = $filterAcademicYear;
    $types .= "s";
}
if ($filterSemester && $filterSemester !== '') {
    $query .= " AND s.semester_number = ?";
    $params[] = $filterSemester;
    $types .= "i";
}
if ($filterStatus && $filterStatus !== '') {
    $query .= " AND f.current_status = ?";
    $params[] = $filterStatus;
    $types .= "s";
} else {
    // Default to pending_qams
    $query .= " AND f.current_status = 'pending_qams'";
}

$query .= " ORDER BY f.submitted_at ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$forms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode([
    'success' => true,
    'forms' => $forms,
    'total_pending' => count($forms)
]);
?>