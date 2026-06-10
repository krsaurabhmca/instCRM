<?php
// auth/login.php
require_once dirname(__DIR__) . '/config/app.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "CSRF Token Validation Failed.";
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            // Find user by email
            $result = db_query($conn, "SELECT u.*, t.name as tenant_name FROM users u JOIN tenants t ON u.tenant_id = t.id WHERE u.email = ?", [$email]);
            
            if (mysqli_num_rows($result) === 0) {
                $error = "Invalid email or password.";
            } else {
                $user = mysqli_fetch_assoc($result);
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'Active') {
                        $error = "Your account is deactivated. Contact administrator.";
                    } else {
                        // Set sessions
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['tenant_id'] = $user['tenant_id'];
                        $_SESSION['tenant_name'] = $user['tenant_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        
                        redirect('/dashboard.php');
                    }
                } else {
                    $error = "Invalid email or password.";
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
    <title>Sign In · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="auth-page">
    <script>
        if (localStorage.getItem('theme_dark') === 'true') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        function toggleDarkMode() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme_dark', 'false');
                document.getElementById('darkModeIcon').classList.replace('bi-sun-fill', 'bi-moon-fill');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme_dark', 'true');
                document.getElementById('darkModeIcon').classList.replace('bi-moon-fill', 'bi-sun-fill');
            }
        }
        window.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme_dark') === 'true') {
                const icon = document.getElementById('darkModeIcon');
                if(icon) icon.classList.replace('bi-moon-fill', 'bi-sun-fill');
            }
        });
    </script>
    <button onclick="toggleDarkMode()" style="position: absolute; top: 20px; right: 20px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: var(--text); cursor: pointer; z-index: 10; box-shadow: var(--shadow-sm);">
        <i class="bi bi-moon-fill" id="darkModeIcon"></i>
    </button>
    <div class="auth-bg-pattern"></div>
    <div class="auth-bg-grid"></div>

    <div class="auth-card">
        <div class="auth-header">
            <a href="#" class="auth-logo">
                <div class="auth-logo-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <span class="auth-logo-text">Inst<span>CRM</span></span>
            </a>
            <h1>Welcome back</h1>
            <p>Sign in to your institution portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="auth-alert error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/auth/login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@institution.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:8px;">
                <i class="bi bi-arrow-right-circle-fill"></i>
                Sign In to Portal
            </button>
        </form>

        <div class="auth-footer-link">
            Don't have an account?
            <a href="<?= BASE_URL ?>/auth/register.php">Register Institution</a>
        </div>
    </div>
</body>
</html>
