<?php
// api/notifications.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

requireLogin();

$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

switch ($method) {
    case 'GET':
        // Get user notifications
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] == 'true';
        
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        $query .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get unread count
        $countStmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
        $countStmt->bind_param("i", $userId);
        $countStmt->execute();
        $unreadCount = $countStmt->get_result()->fetch_assoc()['unread'];
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
        break;
        
    case 'PUT':
        // Mark notification as read
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['id'] ?? 0;
        
        if ($notificationId === 'all') {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notificationId, $userId);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        }
        break;
        
    case 'DELETE':
        // Delete notification
        $id = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        break;
}

$conn->close();
?>