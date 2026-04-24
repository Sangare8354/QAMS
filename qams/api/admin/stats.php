<?php
// api/admin/stats.php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireRole('admin');

$conn = getConnection();
$stats = [];

// Total users by role
$roleQuery = "
    SELECT ut.type_name, COUNT(*) as count 
    FROM users u 
    JOIN user_types ut ON u.user_type_id = ut.id 
    WHERE u.is_active = 1
    GROUP BY ut.id
";
$roleResult = $conn->query($roleQuery);
$stats['users_by_role'] = $roleResult->fetch_all(MYSQLI_ASSOC);

$stats['total_users'] = array_sum(array_column($stats['users_by_role'], 'count'));

// Forms statistics
$formQuery = "
    SELECT 
        COUNT(*) as total_forms,
        SUM(CASE WHEN current_status = 'pending_dept' THEN 1 ELSE 0 END) as pending_dept,
        SUM(CASE WHEN current_status = 'pending_dean' THEN 1 ELSE 0 END) as pending_dean,
        SUM(CASE WHEN current_status = 'pending_qams' THEN 1 ELSE 0 END) as pending_qams,
        SUM(CASE WHEN current_status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN current_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM qams_forms
";
$formResult = $conn->query($formQuery);
$stats['forms'] = $formResult->fetch_assoc();

// Academic stats
$yearResult = $conn->query("SELECT COUNT(*) as count FROM academic_years");
$stats['total_years'] = $yearResult->fetch_assoc()['count'];

$semResult = $conn->query("SELECT COUNT(*) as count FROM semesters WHERE is_current = 1");
$stats['active_semester'] = $semResult->fetch_assoc()['count'] ? 'Active' : 'None';

// Hierarchy stats
$mappingResult = $conn->query("SELECT COUNT(*) as count FROM hierarchy_mappings WHERE is_active = 1");
$stats['total_mappings'] = $mappingResult->fetch_assoc()['count'];

// Recent activities
$recentQuery = "
    SELECT ac.*, u.full_name, u.username, ut.type_name as role
    FROM approval_chain ac
    JOIN users u ON ac.actor_id = u.id
    JOIN user_types ut ON u.user_type_id = ut.id
    ORDER BY ac.created_at DESC
    LIMIT 5
";
$stats['recent_activities'] = $conn->query($recentQuery)->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode(['success' => true, 'stats' => $stats]);
?>