<?php
// includes/notifications.php
require_once __DIR__ . '/../config/database.php';

function sendNotification($userId, $type, $title, $message, $formId = null) {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, form_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $userId, $type, $title, $message, $formId);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

function sendEmail($to, $toName, $subject, $message) {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO email_queue (recipient_email, recipient_name, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $to, $toName, $subject, $message);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

function sendFormNotification($formId, $action, $actorRole, $nextRole = null) {
    $conn = getConnection();
    
    // Get form details
    $stmt = $conn->prepare("
        SELECT f.*, t.full_name as teacher_name, t.email as teacher_email, t.id as teacher_id,
               h.full_name as dept_head_name, h.email as dept_head_email, h.id as dept_head_id,
               d.full_name as dean_name, d.email as dean_email, d.id as dean_id,
               q.full_name as qams_name, q.email as qams_email, q.id as qams_id,
               ay.year_name, s.semester_number
        FROM qams_forms f
        JOIN users t ON f.teacher_id = t.id
        JOIN hierarchy_mappings hm ON t.id = hm.teacher_id AND f.academic_year_id = hm.academic_year_id
        JOIN users h ON hm.dept_head_id = h.id
        JOIN users d ON hm.dean_id = d.id
        JOIN users q ON hm.qams_head_id = q.id
        JOIN academic_years ay ON f.academic_year_id = ay.id
        JOIN semesters s ON f.semester_id = s.id
        WHERE f.id = ?
    ");
    $stmt->bind_param("i", $formId);
    $stmt->execute();
    $form = $stmt->get_result()->fetch_assoc();
    
    if (!$form) {
        $conn->close();
        return false;
    }
    
    $formLink = "http://localhost/qams/";
    $formNumber = $form['form_number'];
    $courseInfo = $form['course_code'] . " - " . $form['course_title'];
    
    switch ($action) {
        case 'submitted':
            // Notify Department Head
            $title = "New Form Submitted";
            $message = "Teacher {$form['teacher_name']} has submitted a QAMS form for {$courseInfo}.";
            sendNotification($form['dept_head_id'], 'approval_needed', $title, $message, $formId);
            sendEmail($form['dept_head_email'], $form['dept_head_name'], "QAMS: New Form Submitted - {$formNumber}", 
                "Dear {$form['dept_head_name']},\n\nTeacher {$form['teacher_name']} has submitted a QAMS form for review.\n\nCourse: {$courseInfo}\nForm: {$formNumber}\n\nPlease login to review and approve.\n\n{$formLink}dept_head/dept_head-panel.html");
            break;
            
        case 'dept_approved':
            // Notify Dean
            $title = "Form Approved by Department Head";
            $message = "Department Head {$form['dept_head_name']} has approved form {$formNumber} for {$courseInfo}. It is now pending your review.";
            sendNotification($form['dean_id'], 'approval_needed', $title, $message, $formId);
            sendEmail($form['dean_email'], $form['dean_name'], "QAMS: Form Approved - {$formNumber}", 
                "Dear {$form['dean_name']},\n\nDepartment Head {$form['dept_head_name']} has approved form {$formNumber}.\n\nCourse: {$courseInfo}\n\nPlease login to review and forward to QAMS Head.\n\n{$formLink}dean/dean-panel.html");
            break;
            
        case 'dept_rejected':
            // Notify Teacher
            $title = "Form Needs Correction";
            $message = "Department Head {$form['dept_head_name']} has requested corrections for form {$formNumber}. Reason: " . ($form['rejection_reason'] ?? 'Please review and correct.');
            sendNotification($form['teacher_id'], 'rejected', $title, $message, $formId);
            sendEmail($form['teacher_email'], $form['teacher_name'], "QAMS: Form Needs Correction - {$formNumber}", 
                "Dear {$form['teacher_name']},\n\nYour form {$formNumber} needs corrections.\n\nReason: " . ($form['rejection_reason'] ?? 'Please review and correct.') . "\n\nPlease login to make corrections and resubmit.\n\n{$formLink}teacher/teacher-panel.html");
            break;
            
        case 'dean_approved':
            // Notify QAMS Head
            $title = "Form Approved by Dean";
            $message = "Dean {$form['dean_name']} has approved form {$formNumber} for {$courseInfo}. It is now pending final review.";
            sendNotification($form['qams_id'], 'approval_needed', $title, $message, $formId);
            sendEmail($form['qams_email'], $form['qams_name'], "QAMS: Form Approved - {$formNumber}", 
                "Dear {$form['qams_name']},\n\nDean {$form['dean_name']} has approved form {$formNumber}.\n\nCourse: {$courseInfo}\n\nPlease login for final approval.\n\n{$formLink}QAMS_Head/QAMS_head-panel.html");
            break;
            
        case 'dean_rejected':
            // Notify Department Head (who will forward to teacher)
            $title = "Form Reverted by Dean";
            $message = "Dean {$form['dean_name']} has requested corrections for form {$formNumber}. Reason: " . ($form['rejection_reason'] ?? 'Please review and forward to teacher.');
            sendNotification($form['dept_head_id'], 'rejected', $title, $message, $formId);
            sendEmail($form['dept_head_email'], $form['dept_head_name'], "QAMS: Form Reverted - {$formNumber}", 
                "Dear {$form['dept_head_name']},\n\nDean {$form['dean_name']} has reverted form {$formNumber}.\n\nReason: " . ($form['rejection_reason'] ?? 'Please review and forward to teacher.') . "\n\nPlease login to review and forward to teacher.\n\n{$formLink}dept_head/dept_head-panel.html");
            break;
            
        case 'qams_approved':
            // Notify all parties
            $title = "Form Final Approved & Archived";
            $message = "QAMS Head {$form['qams_name']} has approved and archived form {$formNumber} for {$courseInfo}.";
            sendNotification($form['teacher_id'], 'approved', $title, $message, $formId);
            sendNotification($form['dept_head_id'], 'approved', $title, $message, $formId);
            sendNotification($form['dean_id'], 'approved', $title, $message, $formId);
            break;
            
        case 'qams_rejected':
            // Notify Dean (cascade down)
            $title = "Form Reverted by QAMS Head";
            $message = "QAMS Head {$form['qams_name']} has requested corrections for form {$formNumber}. Reason: " . ($form['rejection_reason'] ?? 'Please review and forward.');
            sendNotification($form['dean_id'], 'rejected', $title, $message, $formId);
            sendEmail($form['dean_email'], $form['dean_name'], "QAMS: Form Reverted - {$formNumber}", 
                "Dear {$form['dean_name']},\n\nQAMS Head has reverted form {$formNumber}.\n\nReason: " . ($form['rejection_reason'] ?? 'Please review.') . "\n\nPlease login to review and forward to Department Head.\n\n{$formLink}dean/dean-panel.html");
            break;
    }
    
    $conn->close();
    return true;
}
?>