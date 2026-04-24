<?php
// api/admin/logs.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('admin');

$conn = getConnection();

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$role = isset($_GET['role']) && $_GET['role'] !== 'all' ? $_GET['role'] : null;
$action = isset($_GET['action']) && $_GET['action'] !== 'all' ? $_GET['action'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Build the query
$query = "
    SELECT 
        ac.id,
        ac.form_id,
        ac.actor_id,
        ac.actor_role,
        ac.action,
        ac.comments,
        ac.created_at,
        u.full_name, 
        u.username, 
        u.email, 
        ut.type_name as role
    FROM approval_chain ac
    JOIN users u ON ac.actor_id = u.id
    JOIN user_types ut ON u.user_type_id = ut.id
    WHERE 1=1
";

$params = [];
$types = "";

// Date filter
if ($days !== 'all' && is_numeric($days)) {
    $query .= " AND ac.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $days;
    $types .= "i";
}

// Role filter
if ($role) {
    $query .= " AND ut.type_name = ?";
    $params[] = $role;
    $types .= "s";
}

// Action filter
if ($action) {
    $query .= " AND ac.action = ?";
    $params[] = $action;
    $types .= "s";
}

// Search filter
if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR ac.comments LIKE ? OR ac.form_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

$query .= " ORDER BY ac.created_at DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics for last 30 days
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN action = 'submit' THEN 1 ELSE 0 END) as submits,
        SUM(CASE WHEN action = 'approve' THEN 1 ELSE 0 END) as approves,
        SUM(CASE WHEN action = 'reject' THEN 1 ELSE 0 END) as rejects,
        SUM(CASE WHEN action = 'archive' THEN 1 ELSE 0 END) as archives
    FROM approval_chain
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get actions by role for chart
$roleStatsQuery = "
    SELECT ut.type_name, COUNT(*) as count
    FROM approval_chain ac
    JOIN users u ON ac.actor_id = u.id
    JOIN user_types ut ON u.user_type_id = ut.id
    WHERE ac.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY ut.type_name
    ORDER BY count DESC
";
$roleStats = $conn->query($roleStatsQuery)->fetch_all(MYSQLI_ASSOC);

// Get daily activity for timeline (last 7 days)
$dailyQuery = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM approval_chain
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$dailyStats = $conn->query($dailyQuery)->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode([
    'success' => true, 
    'logs' => $logs,
    'stats' => $stats,
    'role_stats' => $roleStats,
    'daily_stats' => $dailyStats
]);
?>