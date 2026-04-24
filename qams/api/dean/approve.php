<?php
// api/dean/approve.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dean');

$data = json_decode(file_get_contents('php://input'), true);
$formId = $data['form_id'] ?? 0;
$comments = $data['comments'] ?? '';

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID required']);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get faculty_id of this dean
$deptStmt = $conn->prepare("SELECT faculty_id FROM users WHERE id = ?");
$deptStmt->bind_param("i", $userId);
$deptStmt->execute();
$dean = $deptStmt->get_result()->fetch_assoc();

if (!$dean || !$dean['faculty_id']) {
    echo json_encode(['success' => false, 'message' => 'You are not assigned to any faculty']);
    exit();
}

$facultyId = $dean['faculty_id'];

// Verify form belongs to dean's faculty and is pending
$stmt = $conn->prepare("
    SELECT f.*, t.department_id, d.faculty_id, f.form_number
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    JOIN departments d ON t.department_id = d.id
    WHERE f.id = ? AND f.current_status = 'pending_dean'
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found or not pending']);
    exit();
}

if ($form['faculty_id'] != $facultyId) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to approve this form']);
    exit();
}

// Get the qams_head_id from hierarchy_mappings
$hierarchyStmt = $conn->prepare("
    SELECT qams_head_id 
    FROM hierarchy_mappings 
    WHERE dean_id = ? AND academic_year_id = ? AND is_active = 1
");
$hierarchyStmt->bind_param("ii", $userId, $form['academic_year_id']);
$hierarchyStmt->execute();
$mapping = $hierarchyStmt->get_result()->fetch_assoc();

if (!$mapping) {
    echo json_encode(['success' => false, 'message' => 'No QAMS Head assigned. Please contact admin.']);
    exit();
}

$qamsHeadId = $mapping['qams_head_id'];

// Update form status
$updateStmt = $conn->prepare("
    UPDATE qams_forms 
    SET current_status = 'pending_qams', 
        current_holder_role = 'qams_head',
        current_holder_id = ?,
        dean_approved_at = NOW(),
        rejection_reason = NULL
    WHERE id = ?
");
$updateStmt->bind_param("ii", $qamsHeadId, $formId);
$updateStmt->execute();

// Insert into approval chain
$chainStmt = $conn->prepare("
    INSERT INTO approval_chain (form_id, actor_id, actor_role, action, comments, next_actor_id, next_actor_role)
    VALUES (?, ?, 'dean', 'approve', ?, ?, 'qams_head')
");
$chainStmt->bind_param("iisi", $formId, $userId, $comments, $qamsHeadId);
$chainStmt->execute();

$conn->close();

echo json_encode([
    'success' => true, 
    'message' => 'Form approved and forwarded to QAMS Head',
    'form_id' => $formId,
    'form_number' => $form['form_number']
]);
?>