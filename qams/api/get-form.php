<?php
// api/get-form.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

requireLogin();

$response = ['success' => false, 'form' => null];

if (isset($_GET['id'])) {
    $formId = intval($_GET['id']);
    $userId = $_SESSION['user_id'];
    
    $conn = getConnection();
    
    // Get form data - only if it belongs to this teacher
    $stmt = $conn->prepare("SELECT * FROM qams_forms WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $formId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['success'] = true;
        $response['form'] = $result->fetch_assoc();
    }
    
    $stmt->close();
    $conn->close();
}

echo json_encode($response);
?>