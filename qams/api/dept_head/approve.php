<?php
// api/dept_head/approve.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dept_head');

$data = json_decode(file_get_contents('php://input'), true);
$formId = $data['form_id'] ?? 0;
$comments = $data['comments'] ?? '';

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID required']);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get department_id of this dept head
$deptStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
$deptStmt->bind_param("i", $userId);
$deptStmt->execute();
$deptHead = $deptStmt->get_result()->fetch_assoc();

// Verify this form belongs to dept head's department and is pending
$stmt = $conn->prepare("
    SELECT f.*, t.department_id, t.id AS teacher_id, f.academic_year_id
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    WHERE f.id = ? AND f.current_status = 'pending_dept'
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found or not pending']);
    exit();
}

if ($form['department_id'] != $deptHead['department_id']) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to approve this form']);
    exit();
}

// Get the dean_id for this department from hierarchy_mappings
$hierarchyStmt = $conn->prepare("
    SELECT dean_id 
    FROM hierarchy_mappings 
    WHERE dept_head_id = ? AND academic_year_id = ? AND is_active = 1
");
$hierarchyStmt->bind_param("ii", $userId, $form['academic_year_id']);
$hierarchyStmt->execute();
$mapping = $hierarchyStmt->get_result()->fetch_assoc();

if (!$mapping) {
    echo json_encode(['success' => false, 'message' => 'No dean assigned for this department. Please contact admin.']);
    exit();
}

$deanId = $mapping['dean_id'];

// Update form status
$updateStmt = $conn->prepare("
    UPDATE qams_forms 
    SET current_status = 'pending_dean', 
        current_holder_role = 'dean',
        current_holder_id = ?,
        dept_approved_at = NOW(),
        rejection_reason = NULL
    WHERE id = ?
");
$updateStmt->bind_param("ii", $deanId, $formId);
$updateStmt->execute();

// Insert into approval chain
$chainStmt = $conn->prepare("
    INSERT INTO approval_chain (form_id, actor_id, actor_role, action, comments, next_actor_id, next_actor_role)
    VALUES (?, ?, 'dept_head', 'approve', ?, ?, 'dean')
");
$chainStmt->bind_param("iisi", $formId, $userId, $comments, $deanId);
$chainStmt->execute();

// Insert notification for dean
$notifyStmt = $conn->prepare("
    INSERT INTO notifications (user_id, type, title, message, form_id)
    VALUES (?, 'approval_needed', 'New Form Pending Approval', 
            CONCAT('Form #', ?, ' has been approved by Department Head and needs your review'), ?)
");
$notifyStmt->bind_param("isi", $deanId, $form['form_number'], $formId);
$notifyStmt->execute();

$conn->close();

echo json_encode([
    'success' => true, 
    'message' => 'Form approved and forwarded to Dean',
    'form_id' => $formId,
    'form_number' => $form['form_number']
]);
?>