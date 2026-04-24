<?php
// api/dean/revert.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('dean');

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
    SELECT f.*, hm.dept_head_id, f.form_number
    FROM qams_forms f
    JOIN hierarchy_mappings hm ON f.teacher_id = hm.teacher_id 
        AND f.academic_year_id = hm.academic_year_id
    WHERE f.id = ? AND f.current_status = 'pending_dean'
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found or not pending']);
    exit();
}

// Update form status - revert to Dept Head
$updateStmt = $conn->prepare("
    UPDATE qams_forms 
    SET current_status = 'rejected', 
        current_holder_role = 'dept_head',
        current_holder_id = ?,
        rejection_reason = ?,
        rejection_level = 'dean'
    WHERE id = ?
");
$updateStmt->bind_param("isi", $form['dept_head_id'], $reason, $formId);
$updateStmt->execute();

// Insert into approval chain
$chainStmt = $conn->prepare("
    INSERT INTO approval_chain (form_id, actor_id, actor_role, action, comments, next_actor_id, next_actor_role)
    VALUES (?, ?, 'dean', 'reject', ?, ?, 'dept_head')
");
$chainStmt->bind_param("iisi", $formId, $userId, $reason, $form['dept_head_id']);
$chainStmt->execute();

$conn->close();

echo json_encode([
    'success' => true, 
    'message' => 'Form reverted to Department Head. Dept Head will review and forward to Teacher.',
    'form_id' => $formId,
    'form_number' => $form['form_number']
]);
?>