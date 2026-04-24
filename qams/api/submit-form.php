<?php
// api/submit-form.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

requireRole('teacher');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getConnection();
    
    // Define upload directories - using absolute path for clarity
    $baseUploadDir = dirname(__DIR__) . '/uploads/';
    
    $attendancePath = '';
    $midtermPath = '';
    $finalPath = '';
    
    // Debug: Log what we received
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    // Handle Attendance Sheet Upload
    if (isset($_FILES['attendance']) && $_FILES['attendance']['error'] == 0) {
        $attendancePath = uploadFile($_FILES['attendance'], $baseUploadDir . 'attendance/');
        error_log("Attendance uploaded to: " . $attendancePath);
    } else {
        error_log("Attendance upload error: " . ($_FILES['attendance']['error'] ?? 'No file'));
    }
    
    // Handle Midterm Question Upload
    if (isset($_FILES['midterm']) && $_FILES['midterm']['error'] == 0) {
        $midtermPath = uploadFile($_FILES['midterm'], $baseUploadDir . 'midterm/');
        error_log("Midterm uploaded to: " . $midtermPath);
    } else {
        error_log("Midterm upload error: " . ($_FILES['midterm']['error'] ?? 'No file'));
    }
    
    // Handle Final Question Upload
    if (isset($_FILES['final']) && $_FILES['final']['error'] == 0) {
        $finalPath = uploadFile($_FILES['final'], $baseUploadDir . 'final/');
        error_log("Final uploaded to: " . $finalPath);
    } else {
        error_log("Final upload error: " . ($_FILES['final']['error'] ?? 'No file'));
    }
    
    // Get current academic year and semester
    $yearResult = $conn->query("SELECT id FROM academic_years WHERE is_current = 1");
    if ($yearResult && $yearResult->num_rows > 0) {
        $academicYearId = $yearResult->fetch_assoc()['id'];
    } else {
        // Fallback to latest year
        $yearResult = $conn->query("SELECT id FROM academic_years ORDER BY id DESC LIMIT 1");
        $academicYearId = $yearResult->fetch_assoc()['id'];
    }
    
    $semResult = $conn->query("SELECT id FROM semesters WHERE is_current = 1 AND academic_year_id = $academicYearId");
    if ($semResult && $semResult->num_rows > 0) {
        $semesterId = $semResult->fetch_assoc()['id'];
    } else {
        // Fallback to semester 5
        $semResult = $conn->query("SELECT id FROM semesters WHERE academic_year_id = $academicYearId LIMIT 1");
        $semesterId = $semResult->fetch_assoc()['id'];
    }
    
    // Get form data
    $courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $zoomLink = $_POST['zoom_link'] ?? '';
    $classesTaken = $_POST['classes_taken'] ?? 0;
    $classTests = $_POST['class_tests'] ?? 0;
    $midtermConducted = $_POST['midterm_conducted'] ?? 0;
    $finalConducted = $_POST['final_conducted'] ?? 0;
    $assignments = $_POST['assignments'] ?? 0;
    $presentations = $_POST['presentations'] ?? 0;
    $teacherId = $_SESSION['user_id'];
    
    // Check if we're updating an existing form or creating new
    $existingFormId = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    
    if ($existingFormId > 0) {
        // Update existing form
        $stmt = $conn->prepare("UPDATE qams_forms SET 
            zoom_link = ?, classes_taken = ?, class_tests = ?, 
            midterm_conducted = ?, final_conducted = ?, 
            assignments = ?, presentations = ?,
            attendance_sheet = IF(? != '', ?, attendance_sheet),
            midterm_question = IF(? != '', ?, midterm_question),
            final_question = IF(? != '', ?, final_question),
            current_status = 'pending_dept',
            current_holder_role = 'dept_head',
            updated_at = NOW()
            WHERE id = ? AND teacher_id = ?");
        
        $stmt->bind_param("siiiiissssssii", 
            $zoomLink, $classesTaken, $classTests, 
            $midtermConducted, $finalConducted, 
            $assignments, $presentations,
            $attendancePath, $attendancePath,
            $midtermPath, $midtermPath,
            $finalPath, $finalPath,
            $existingFormId, $teacherId);
        
        if ($stmt->execute()) {
            $formId = $existingFormId;
            $response['success'] = true;
            $response['message'] = 'Form updated successfully';
            $response['form_id'] = $formId;
        } else {
            $response['message'] = 'Failed to update form: ' . $stmt->error;
        }
        $stmt->close();
        
    } else {
        // Create new form
        $formNumber = 'QAMS-' . date('Y') . '-' . rand(1000, 9999);
        
        $stmt = $conn->prepare("INSERT INTO qams_forms 
            (form_number, teacher_id, course_id, academic_year_id, semester_id, 
             zoom_link, classes_taken, class_tests, midterm_conducted, final_conducted,
             assignments, presentations, attendance_sheet, midterm_question, final_question,
             current_status, current_holder_role, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_dept', 'dept_head', NOW())");
        
        $stmt->bind_param("siiiisiiiiissss", 
            $formNumber, $teacherId, $courseId, $academicYearId, $semesterId,
            $zoomLink, $classesTaken, $classTests, $midtermConducted, $finalConducted,
            $assignments, $presentations, $attendancePath, $midtermPath, $finalPath);
        
        if ($stmt->execute()) {
            $formId = $stmt->insert_id;
            
            // Get dept head for this teacher
            $deptResult = $conn->query("SELECT dept_head_id FROM hierarchy_mappings 
                                        WHERE teacher_id = $teacherId AND academic_year_id = $academicYearId");
            if ($deptResult && $deptResult->num_rows > 0) {
                $deptHeadId = $deptResult->fetch_assoc()['dept_head_id'];
                
                // Add to approval chain
                $approvalStmt = $conn->prepare("INSERT INTO approval_chain 
                    (form_id, actor_id, actor_role, action, next_actor_id, next_actor_role)
                    VALUES (?, ?, 'teacher', 'submit', ?, 'dept_head')");
                $approvalStmt->bind_param("iii", $formId, $teacherId, $deptHeadId);
                $approvalStmt->execute();
                $approvalStmt->close();
            }
            
            $response['success'] = true;
            $response['message'] = 'Form submitted successfully';
            $response['form_id'] = $formId;
            $response['form_number'] = $formNumber;
        } else {
            $response['message'] = 'Failed to submit form: ' . $stmt->error;
        }
        $stmt->close();
    }
    
    $conn->close();
}

function uploadFile($file, $targetDir) {
    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileType != 'pdf') {
        error_log("Invalid file type: " . $fileType);
        return '';
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10000000) {
        error_log("File too large: " . $file['size']);
        return '';
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $targetPath = $targetDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("File uploaded successfully: " . $targetPath);
        return $targetPath;
    } else {
        error_log("Failed to move uploaded file from: " . $file['tmp_name'] . " to: " . $targetPath);
        return '';
    }
}

echo json_encode($response);
?>