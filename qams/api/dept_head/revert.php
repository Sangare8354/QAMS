<?php
// api/dept_head/revert.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dept_head');

$data = json_decode(file_get_contents('php://input'), true);
$formId = $data['form_id'] ?? 0;
$reason = $data['reason'] ?? '';

if (!$formId || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Form ID and reason required']);
    exit();
}

if (strlen($reason) < 10) {
    echo json_encode(['success' => false, 'message' => 'Please provide a detailed reason (at least 10 characters)']);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get form details
$stmt = $conn->prepare("
    SELECT f.*, t.id AS teacher_id, f.form_number
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

// Update form status - revert to Teacher
$updateStmt = $conn->prepare("
    UPDATE qams_forms 
    SET current_status = 'rejected', 
        current_holder_role = 'teacher',
        current_holder_id = ?,
        rejection_reason = ?,
        rejection_level = 'dept'
    WHERE id = ?
");
$updateStmt->bind_param("isi", $form['teacher_id'], $reason, $formId);
$updateStmt->execute();

// Insert into approval chain
$chainStmt = $conn->prepare("
    INSERT INTO approval_chain (form_id, actor_id, actor_role, action, comments, next_actor_id, next_actor_role)
    VALUES (?, ?, 'dept_head', 'reject', ?, ?, 'teacher')
");
$chainStmt->bind_param("iisi", $formId, $userId, $reason, $form['teacher_id']);
$chainStmt->execute();

// Insert into rejection cascade
$cascadeStmt = $conn->prepare("
    INSERT INTO rejection_cascade (original_form_id, rejected_at_level, rejected_by, rejection_reason)
    VALUES (?, 'dept', ?, ?)
");
$cascadeStmt->bind_param("iis", $formId, $userId, $reason);
$cascadeStmt->execute();

$conn->close();

echo json_encode([
    'success' => true, 
    'message' => 'Form reverted to Teacher. Teacher can now correct and resubmit.',
    'form_id' => $formId,
    'form_number' => $form['form_number']
]);
?>