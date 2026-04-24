<?php
// pages/login.php
session_start();

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: /qams/admin/index.php');
            break;
        case 'teacher':
            header('Location: /qams/teacher/index.php');
            break;
        case 'dept_head':
            header('Location: /qams/dept_head/index.php');
            break;
        case 'dean':
            header('Location: /qams/dean/index.php');
            break;
        case 'qams_head':
            header('Location: /qams/qams_head/index.php');
            break;
        default:
            header('Location: dashboard.php');
    }
    exit();
}

// Include database and auth
require_once '../config/database.php';
include_once '../includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Construct full email
    $email = $username . '@edu.gh';
    
    if (login($email, $password)) {
        // Redirect based on role
        switch ($_SESSION['user_role']) {
            case 'admin':
                header('Location: /qams/admin/index.php');
                break;
            case 'teacher':
                header('Location: /qams/teacher/index.php');
                break;
            case 'dept_head':
                header('Location: /qams/dept_head/index.php');
                break;
            case 'dean':
                header('Location: /qams/dean/index.php');
                break;
            case 'qams_head':
                header('Location: /qams/qams_head/index.php');
                break;
            default:
                header('Location: dashboard.php');
        }
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QAMS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #dbdbdd;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border-radius: 16px;
        }
        .card-header {
            border-radius: 16px 16px 0 0 !important;
        }
        .btn-primary {
            border-radius: 12px;
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13,110,253,0.3);
        }
        .input-group-text {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow border-0 rounded-4">
                    <div class="card-header text-center py-4 bg-primary text-white rounded-top-4">
                        <i class="bi bi-shield-lock display-5 mb-3"></i>
                        <h2 class="mb-0 fw-bold">QAMS Login</h2>
                        <p class="mb-0 opacity-75">Quality Assurance Management System</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Username</label>
                                <div class="input-group">
                                    <input type="text" name="username" class="form-control form-control-lg" 
                                           placeholder="username" required autofocus>
                                    <span class="input-group-text bg-white">@edu.gh</span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" 
                                           class="form-control form-control-lg" required>
                                    <span class="input-group-text bg-white" onclick="togglePassword()">
                                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Login to QAMS
                            </button>
                        </form>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <a href="#" class="small text-muted" onclick="alert('Contact IT Support')">Forgot Password?</a>
                            <a href="#" class="small fw-semibold text-primary" onclick="alert('After login, go to Profile > Change Password')">Change Password</a>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="small text-muted text-center">
                            <p class="mb-0 fw-bold">Demo Credentials:</p>
                            <p class="mb-0">👨‍🏫 Teacher: <code>john.teacher</code> / <code>teacher123</code></p>
                            <p class="mb-0">👔 Dept Head: <code>ama.depthead</code> / <code>depthead123</code></p>
                            <p class="mb-0">🎓 Dean: <code>kwame.dean</code> / <code>dean123</code></p>
                            <p class="mb-0">📋 QAMS Head: <code>grace.qams</code> / <code>qamshead123</code></p>
                            <p class="mb-0">⚙️ Admin: <code>admin</code> / <code>admin123</code></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>