<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_exists' => isset($_SESSION),
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_role' => $_SESSION['user_role'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'full_name' => $_SESSION['full_name'] ?? null,
    'department_id' => $_SESSION['department_id'] ?? null,
    'faculty_id' => $_SESSION['faculty_id'] ?? null,
    'session_data' => $_SESSION
]);
?>