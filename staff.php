<?php
// staff.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Only Admins can access
if ($_SESSION['user_role'] !== 'Admin') {
    set_flash_message('danger', 'Unauthorized access.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($_POST['action'] === 'add_staff') {
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = sanitize($_POST['role']);
            
            // Check email uniqueness within tenant
            $check = db_query($conn, "SELECT id FROM users WHERE email = ? AND tenant_id = ?", [$email, $tenant_id]);
            if (mysqli_num_rows($check) > 0) {
                set_flash_message('danger', 'Email already exists.');
            } else {
                db_insert($conn, "INSERT INTO users (tenant_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'Active')", [
                    $tenant_id, $name, $email, $pw, $role
                ]);
                set_flash_message('success', 'Staff member added successfully.');
            }
            redirect('/staff.php');
            
        } elseif ($_POST['action'] === 'edit_staff') {
            $user_id = intval($_POST['user_id']);
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            $status = sanitize($_POST['status']);
            
            $q = "UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ? AND tenant_id = ?";
            $params = [$name, $email, $role, $status, $user_id, $tenant_id];
            
            if (!empty($_POST['password'])) {
                $q = "UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ? AND tenant_id = ?";
                $params = [$name, $email, $role, $status, password_hash($_POST['password'], PASSWORD_DEFAULT), $user_id, $tenant_id];
            }
            
            db_query($conn, $q, $params);
            set_flash_message('success', 'Staff updated successfully.');
            redirect('/staff.php');
        } elseif ($_POST['action'] === 'login_as') {
            $target_user_id = intval($_POST['user_id']);
            $u_res = db_query($conn, "SELECT id, role, name FROM users WHERE id = ? AND tenant_id = ?", [$target_user_id, $tenant_id]);
            $u = mysqli_fetch_assoc($u_res);
            if ($u) {
                $_SESSION['original_user_id'] = $_SESSION['user_id'];
                $_SESSION['original_user_role'] = $_SESSION['user_role'];
                $_SESSION['original_user_name'] = $_SESSION['user_name'];
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['user_role'] = $u['role'];
                $_SESSION['user_name'] = $u['name'];
                redirect('/dashboard.php');
            }
        }
    }
}

$staff_res = db_query($conn, "SELECT * FROM users WHERE tenant_id = ? ORDER BY created_at DESC", [$tenant_id]);
?>

<div class="page-header">
    <h2><i class="bi bi-people-fill" style="color:var(--primary);margin-right:8px;"></i>Staff Directory</h2>
    <button class="btn btn-primary" onclick="openModal('addStaffModal')"><i class="bi bi-person-plus"></i> Add Staff Member</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($staff_res) === 0): ?>
                        <tr><td colspan="5"><div class="empty-state"><p>No staff found.</p></div></td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($staff_res)): ?>
                            <tr>
                                <td><strong><?= $row['name'] ?></strong></td>
                                <td><?= $row['email'] ?></td>
                                <td><span class="badge primary"><?= $row['role'] ?></span></td>
                                <td>
                                    <?php if($row['status'] == 'Active'): ?>
                                        <span class="badge success">Active</span>
                                    <?php else: ?>
                                        <span class="badge danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-secondary btn-sm" onclick="editStaff(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                                    <?php if ($row['id'] !== $_SESSION['user_id']): ?>
                                    <form action="staff.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="login_as">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-info-soft btn-sm" onclick="return confirm('Login as <?= htmlspecialchars($row['name']) ?>?')"><i class="bi bi-box-arrow-in-right"></i> Login As</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addStaffModal" class="modal-backdrop">
    <div class="modal">
        <form action="staff.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_staff">
            <div class="modal-header">
                <h3><i class="bi bi-person-plus" style="color:var(--primary);margin-right:6px;"></i>Add Staff</h3>
                <button type="button" class="modal-close" onclick="closeModal('addStaffModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Temporary Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="Admin">Admin</option>
                        <option value="Counsellor">Counsellor</option>
                        <option value="Teacher">Teacher</option>
                        <option value="Cashier">Cashier</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStaffModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editStaffModal" class="modal-backdrop">
    <div class="modal">
        <form action="staff.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="user_id" id="e_user_id">
            <div class="modal-header">
                <h3><i class="bi bi-pencil-square" style="color:var(--primary);margin-right:6px;"></i>Edit Staff</h3>
                <button type="button" class="modal-close" onclick="closeModal('editStaffModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" id="e_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="e_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control" minlength="6">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="e_role" class="form-control" required>
                            <option value="Admin">Admin</option>
                            <option value="Counsellor">Counsellor</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Cashier">Cashier</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="e_status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStaffModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStaff(data) {
    document.getElementById('e_user_id').value = data.id;
    document.getElementById('e_name').value = data.name;
    document.getElementById('e_email').value = data.email;
    document.getElementById('e_role').value = data.role;
    document.getElementById('e_status').value = data.status;
    openModal('editStaffModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
