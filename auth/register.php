<?php
// auth/register.php
require_once dirname(__DIR__) . '/config/app.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "CSRF Token Validation Failed.";
    } else {
        $inst_name = sanitize($_POST['inst_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $admin_name = sanitize($_POST['admin_name']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($inst_name) || empty($email) || empty($admin_name) || empty($password)) {
            $error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check if tenant email exists
            $check = db_query($conn, "SELECT id FROM tenants WHERE email = ?", [$email]);
            if (mysqli_num_rows($check) > 0) {
                $error = "An institution with this email is already registered.";
            } else {
                // Start transaction
                mysqli_begin_transaction($conn);
                try {
                    // Create tenant
                    $tenant_id = db_insert($conn, "INSERT INTO tenants (name, email, phone, subscription_plan, subscription_status, trial_ends_at) VALUES (?, ?, ?, 'Free', 'Trial', DATE_ADD(NOW(), INTERVAL 3 DAY))", [$inst_name, $email, $phone]);

                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // Create user
                    db_insert($conn, "INSERT INTO users (tenant_id, name, email, password, role) VALUES (?, ?, ?, ?, 'Admin')", [
                        $tenant_id,
                        $admin_name,
                        $email,
                        $hashed_password
                    ]);

                    mysqli_commit($conn);
                    $success = "Institution registered successfully! Please login.";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Institution · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="auth-page">
    <div class="auth-bg-pattern"></div>
    <div class="auth-bg-grid"></div>

    <div class="auth-card" style="max-width: 480px;">
        <div class="auth-header">
            <a href="#" class="auth-logo">
                <div class="auth-logo-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <span class="auth-logo-text">Inst<span>CRM</span></span>
            </a>
            <h1>Create your account</h1>
            <p>Register your institution and get started for free</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="auth-alert error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="auth-alert success">
                <i class="bi bi-check-circle-fill"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/auth/register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label">Institution Name</label>
                <input type="text" name="inst_name" class="form-control" placeholder="E.g., Excel Academy" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@academy.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone / Mobile</label>
                    <input type="text" name="phone" class="form-control" placeholder="+91 9876543210">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Administrator Name</label>
                <input type="text" name="admin_name" class="form-control" placeholder="Your full name" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 8px;">
                <i class="bi bi-building-fill-add"></i>
                Create Institution Account
            </button>
        </form>

        <div class="auth-footer-link">
            Already registered?
            <a href="<?= BASE_URL ?>/auth/login.php">Sign In</a>
        </div>
    </div>
</body>
</html>
