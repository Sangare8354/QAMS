<?php
// api/generate-pdf.php - Simplified version
require_once '../config/database.php';
session_start();

$formId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$formId) {
    die('Form ID required');
}

$conn = getConnection();

// Get form details
$query = "
    SELECT 
        f.*,
        t.full_name as teacher_name,
        t.employee_id as teacher_emp_id,
        t.email as teacher_email,
        c.course_code,
        c.course_title,
        ay.year_name,
        s.semester_number,
        fac.faculty_name,
        dept.department_name
    FROM qams_forms f
    JOIN users t ON f.teacher_id = t.id
    JOIN courses c ON f.course_id = c.id
    JOIN academic_years ay ON f.academic_year_id = ay.id
    JOIN semesters s ON f.semester_id = s.id
    JOIN departments dept ON t.department_id = dept.id
    JOIN faculties fac ON dept.faculty_id = fac.id
    WHERE f.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $formId);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();

if (!$form) {
    die('Form not found');
}

// Get approval chain
$chainStmt = $conn->prepare("
    SELECT ac.*, u.full_name as actor_name
    FROM approval_chain ac
    JOIN users u ON ac.actor_id = u.id
    WHERE ac.form_id = ?
    ORDER BY ac.created_at ASC
");
$chainStmt->bind_param("i", $formId);
$chainStmt->execute();
$approvals = $chainStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>QAMS Form - ' . htmlspecialchars($form['form_number']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #0d6efd;
            margin: 0;
            font-size: 24px;
        }
        .header h2 {
            color: #333;
            margin: 10px 0 0;
            font-size: 18px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #0d6efd;
            color: white;
            padding: 8px 12px;
            margin: 15px 0 10px;
            font-weight: bold;
            font-size: 16px;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        td, th {
            border: 1px solid #ddd;
            padding: 10px;
            vertical-align: top;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .info-row {
            background: #f9f9f9;
        }
        .stamp {
            text-align: center;
            margin-top: 30px;
            padding: 15px;
            border: 2px solid green;
            border-radius: 10px;
            background: #f0fff0;
        }
        .stamp p {
            color: green;
            font-weight: bold;
            font-size: 18px;
            margin: 0;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .signature {
            margin-top: 30px;
            text-align: center;
        }
        .signature-line {
            margin-top: 30px;
            width: 250px;
            border-top: 1px solid #000;
            display: inline-block;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 15px;
            }
            .stamp {
                border: 1px solid green;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>UNIVERSITY OF GHANA</h1>
        <p>Quality Assurance Management System</p>
        <h2>QAMS FORM - FINAL APPROVAL RECEIPT</h2>
        <p><strong>Form Number:</strong> ' . htmlspecialchars($form['form_number']) . '</p>
        <p><strong>Generated:</strong> ' . date('F d, Y H:i:s') . '</p>
    </div>
    
    <div class="section">
        <div class="section-title">COURSE INFORMATION</div>
        <table>
            <tr class="info-row">
                <td width="30%"><strong>Course Code:</strong></td>
                <td>' . htmlspecialchars($form['course_code']) . '</td>
                <td width="30%"><strong>Course Title:</strong></td>
                <td>' . htmlspecialchars($form['course_title']) . '</td>
            </tr>
            <tr>
                <td><strong>Academic Year:</strong></td>
                <td>' . htmlspecialchars($form['year_name']) . '</td>
                <td><strong>Semester:</strong></td>
                <td>' . htmlspecialchars($form['semester_number']) . '</td>
            </tr>
            <tr class="info-row">
                <td><strong>Department:</strong></td>
                <td>' . htmlspecialchars($form['department_name']) . '</td>
                <td><strong>Faculty:</strong></td>
                <td>' . htmlspecialchars($form['faculty_name']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">TEACHER INFORMATION</div>
        <table>
            <tr class="info-row">
                <td width="30%"><strong>Name:</strong></td>
                <td>' . htmlspecialchars($form['teacher_name']) . '</td>
                <td width="30%"><strong>Employee ID:</strong></td>
                <td>' . htmlspecialchars($form['teacher_emp_id']) . '</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td colspan="3">' . htmlspecialchars($form['teacher_email']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">QUALITY ASSURANCE DATA</div>
        <table>
            <tr class="info-row">
                <td width="33%"><strong>Classes Taken:</strong></td>
                <td>' . ($form['classes_taken'] ?? 0) . '</td>
                <td width="33%"><strong>Class Tests:</strong></td>
                <td>' . ($form['class_tests'] ?? 0) . '</td>
            </tr>
            <tr>
                <td><strong>Midterm Exam:</strong></td>
                <td>' . ($form['midterm_conducted'] ? 'Conducted ✓' : 'Not Conducted') . '</td>
                <td><strong>Final Exam:</strong></td>
                <td>' . ($form['final_conducted'] ? 'Conducted ✓' : 'Not Conducted') . '</td>
            </tr>
            <tr class="info-row">
                <td><strong>Assignments:</strong></td>
                <td>' . ($form['assignments'] ?? 0) . '</td>
                <td><strong>Presentations:</strong></td>
                <td>' . ($form['presentations'] ?? 0) . '</td>
            </tr>
            <tr>
                <td><strong>Zoom Link:</strong></td>
                <td colspan="3">' . htmlspecialchars($form['zoom_link']) . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">APPROVAL CHAIN</div>
        <table>
            <thead>
                <tr><th>Role</th><th>Name</th><th>Action</th><th>Date</th></thead>
            <tbody>';

foreach ($approvals as $approval) {
    $actionText = '';
    switch($approval['action']) {
        case 'submit': $actionText = 'Submitted'; break;
        case 'approve': $actionText = 'Approved ✓'; break;
        case 'reject': $actionText = 'Rejected'; break;
        case 'archive': $actionText = 'Archived'; break;
        default: $actionText = $approval['action'];
    }
    $html .= '<tr class="info-row">
        <td>' . ucfirst($approval['actor_role']) . '</td>
        <td>' . htmlspecialchars($approval['actor_name']) . '</td>
        <td>' . $actionText . '</td>
        <td>' . date('M d, Y H:i', strtotime($approval['created_at'])) . '</td>
    </tr>';
}

$html .= '
            </tbody>
        </table>
    </div>
    
    <div class="stamp">
        <p>✓ FINALLY APPROVED AND ARCHIVED</p>
        <p style="font-size: 12px; margin-top: 5px;">This document is officially certified by QAMS</p>
    </div>
    
    <div class="signature">
        <div class="signature-line"></div>
        <p><strong>Dr. Grace Mensah</strong><br>QAMS Head (IQAC)</p>
    </div>
    
    <div class="footer">
        <p>This is an official QAMS document. Generated by the Quality Assurance Management System.</p>
        <p>For verification, please contact the QAMS office.</p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🖨️ Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; margin-left: 10px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
            ✖ Close
        </button>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            // Uncomment to auto-print: setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>';

// Output as HTML (user can print to PDF using browser)
header('Content-Type: text/html');
echo $html;
?>