<?php
// api/qams_head/approve.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('qams_head');

$data = json_decode(file_get_contents('php://input'), true);
$formId = $data['form_id'] ?? 0;
$comments = $data['comments'] ?? '';

if (!$formId) {
    echo json_encode(['success' => false, 'message' => 'Form ID required']);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Verify form exists and is pending
$stmt = $conn->prepare("
    SELECT f.*, f.form_number
    FROM qams_forms f
    WHERE f.id = ? AND f.current_status = 'pending_qams'
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found or not pending']);
    exit();
}

// Update form status to approved
$updateStmt = $conn->prepare("
    UPDATE qams_forms 
    SET current_status = 'approved', 
        current_holder_role = 'qams_head',
        current_holder_id = ?,
        qams_approved_at = NOW(),
        archived_at = NOW()
    WHERE id = ?
");
$updateStmt->bind_param("ii", $userId, $formId);
$updateStmt->execute();

// Insert into approval chain
$chainStmt = $conn->prepare("
    INSERT INTO approval_chain (form_id, actor_id, actor_role, action, comments)
    VALUES (?, ?, 'qams_head', 'approve', ?)
");
$chainStmt->bind_param("iis", $formId, $userId, $comments);
$chainStmt->execute();

$conn->close();

echo json_encode([
    'success' => true, 
    'message' => 'Form approved and archived successfully',
    'form_id' => $formId,
    'form_number' => $form['form_number']
]);
?>