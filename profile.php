<?php
// profile.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();
$user_id = $_SESSION['user_id'];

$user = mysqli_fetch_assoc(db_query($conn, "SELECT name, email, role FROM users WHERE id = ? AND tenant_id = ?", [$user_id, $tenant_id]));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($_POST['action'] === 'update_profile') {
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            
            db_query($conn, "UPDATE users SET name = ?, email = ? WHERE id = ? AND tenant_id = ?", [$name, $email, $user_id, $tenant_id]);
            $_SESSION['user_name'] = $name;
            set_flash_message('success', 'Profile updated successfully.');
            redirect('/profile.php');
            
        } elseif ($_POST['action'] === 'update_password') {
            $current_pw = $_POST['current_password'];
            $new_pw = $_POST['new_password'];
            
            $pw_res = db_query($conn, "SELECT password FROM users WHERE id = ? AND tenant_id = ?", [$user_id, $tenant_id]);
            $row = mysqli_fetch_assoc($pw_res);
            
            if (password_verify($current_pw, $row['password'])) {
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                db_query($conn, "UPDATE users SET password = ? WHERE id = ? AND tenant_id = ?", [$hash, $user_id, $tenant_id]);
                set_flash_message('success', 'Password updated successfully.');
            } else {
                set_flash_message('danger', 'Current password is incorrect.');
            }
            redirect('/profile.php');
        }
    }
}
?>

<div class="page-header">
    <h2><i class="bi bi-person-circle" style="color:var(--primary);margin-right:8px;"></i>My Profile</h2>
</div>

<div class="grid-2">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Personal Details</h3>
        </div>
        <div class="card-body">
            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assigned Role</label>
                    <input type="text" class="form-control" value="<?= $user['role'] ?>" disabled style="background:var(--ink-50);">
                </div>
                
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Change Password</h3>
        </div>
        <div class="card-body">
            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-warning">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
