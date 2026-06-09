<?php
// settings.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Only Admins can access
if ($_SESSION['user_role'] !== 'Admin') {
    set_flash_message('danger', 'Unauthorized access.');
    redirect('/dashboard.php');
}

$tab = $_GET['tab'] ?? 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'];
        
        // --- PROFILE & BRANDING ---
        if ($action === 'update_profile') {
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $prefix = sanitize($_POST['prefix']);
            
            db_query($conn, "UPDATE tenants SET name = ?, email = ?, phone = ?, address = ?, prefix = ? WHERE id = ?", [
                $name, $email, $phone, $address, $prefix, $tenant_id
            ]);
            
            // Handle Logo Upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/tenants/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'svg'];
                if (in_array($ext, $allowed)) {
                    $filename = "logo_" . $tenant_id . "_" . time() . "." . $ext;
                    $dest = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        db_query($conn, "UPDATE tenants SET logo_path = ? WHERE id = ?", [$dest, $tenant_id]);
                    }
                } else {
                    set_flash_message('danger', 'Invalid logo file type.');
                }
            }
            
            $_SESSION['tenant_name'] = $name;
            set_flash_message('success', 'Institute profile updated successfully.');
            redirect('/settings.php?tab=profile');
        }
        
        // --- COURSE MANAGEMENT ---
        elseif ($action === 'add_course') {
            $code = sanitize($_POST['code']);
            $name = sanitize($_POST['name']);
            $dur  = intval($_POST['duration']);
            $fee  = floatval($_POST['fee']);
            
            db_insert($conn, "INSERT INTO courses (tenant_id, code, name, duration_months, total_fee, status) VALUES (?, ?, ?, ?, ?, 'Active')", [
                $tenant_id, $code, $name, $dur, $fee
            ]);
            set_flash_message('success', 'Course added.');
            redirect('/settings.php?tab=courses');
        }
        elseif ($action === 'edit_course') {
            $cid  = intval($_POST['course_id']);
            $code = sanitize($_POST['code']);
            $name = sanitize($_POST['name']);
            $dur  = intval($_POST['duration']);
            $fee  = floatval($_POST['fee']);
            
            db_query($conn, "UPDATE courses SET code=?, name=?, duration_months=?, total_fee=? WHERE id=? AND tenant_id=?", [
                $code, $name, $dur, $fee, $cid, $tenant_id
            ]);
            set_flash_message('success', 'Course updated.');
            redirect('/settings.php?tab=courses');
        }
        elseif ($action === 'toggle_course') {
            $cid = intval($_POST['course_id']);
            $new_status = sanitize($_POST['new_status']);
            db_query($conn, "UPDATE courses SET status=? WHERE id=? AND tenant_id=?", [$new_status, $cid, $tenant_id]);
            set_flash_message('success', "Course marked as $new_status.");
            redirect('/settings.php?tab=courses');
        }
        
        // --- BATCH MANAGEMENT ---
        elseif ($action === 'add_batch') {
            $cid  = intval($_POST['course_id']);
            $name = sanitize($_POST['name']);
            $start= sanitize($_POST['start_date']);
            $end  = sanitize($_POST['end_date']);
            $timing = sanitize($_POST['timing']);
            $capacity = intval($_POST['capacity']);
            
            db_insert($conn, "INSERT INTO batches (tenant_id, course_id, name, start_date, end_date, timing, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')", [
                $tenant_id, $cid, $name, $start, $end, $timing, $capacity
            ]);
            set_flash_message('success', 'Batch added.');
            redirect('/settings.php?tab=batches');
        }
        elseif ($action === 'edit_batch') {
            $bid  = intval($_POST['batch_id']);
            $cid  = intval($_POST['course_id']);
            $name = sanitize($_POST['name']);
            $start= sanitize($_POST['start_date']);
            $end  = sanitize($_POST['end_date']);
            $timing = sanitize($_POST['timing']);
            $capacity = intval($_POST['capacity']);
            
            db_query($conn, "UPDATE batches SET course_id=?, name=?, start_date=?, end_date=?, timing=?, capacity=? WHERE id=? AND tenant_id=?", [
                $cid, $name, $start, $end, $timing, $capacity, $bid, $tenant_id
            ]);
            set_flash_message('success', 'Batch updated.');
            redirect('/settings.php?tab=batches');
        }
        elseif ($action === 'toggle_batch') {
            $bid = intval($_POST['batch_id']);
            $new_status = sanitize($_POST['new_status']);
            db_query($conn, "UPDATE batches SET status=? WHERE id=? AND tenant_id=?", [$new_status, $bid, $tenant_id]);
            set_flash_message('success', "Batch marked as $new_status.");
            redirect('/settings.php?tab=batches');
        }
    }
}

// Fetch Data
$t_data = mysqli_fetch_assoc(db_query($conn, "SELECT * FROM tenants WHERE id = ?", [$tenant_id]));

$courses = [];
$c_res = db_query($conn, "SELECT * FROM courses WHERE tenant_id = ? ORDER BY created_at DESC", [$tenant_id]);
while($r = mysqli_fetch_assoc($c_res)) $courses[] = $r;

$batches = [];
$b_res = db_query($conn, "SELECT b.*, c.name as course_name FROM batches b JOIN courses c ON b.course_id = c.id WHERE b.tenant_id = ? ORDER BY b.created_at DESC", [$tenant_id]);
while($r = mysqli_fetch_assoc($b_res)) $batches[] = $r;
?>

<div class="page-header">
    <h2><i class="bi bi-gear-fill" style="color:var(--primary);margin-right:8px;"></i>Institute Settings</h2>
</div>

<!-- Tabs -->
<div style="margin-bottom: 24px; border-bottom: 1px solid var(--ink-200); display: flex; gap: 16px;">
    <a href="settings.php?tab=profile" class="btn <?= $tab === 'profile' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Institute Profile</a>
    <a href="settings.php?tab=courses" class="btn <?= $tab === 'courses' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Courses</a>
    <a href="settings.php?tab=batches" class="btn <?= $tab === 'batches' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Batches</a>
</div>

<?php if ($tab === 'profile'): ?>
<div class="card" style="max-width:800px;">
    <div class="card-header">
        <h3 class="card-title">Branding & Details</h3>
    </div>
    <div class="card-body">
        <form action="settings.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Institute Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($t_data['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ID/Receipt Prefix</label>
                    <input type="text" name="prefix" class="form-control" value="<?= htmlspecialchars($t_data['prefix'] ?? 'INST') ?>" required>
                    <small class="text-muted">E.g., INST (Will generate INST-001)</small>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($t_data['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($t_data['phone'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($t_data['address'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Institute Logo</label>
                <?php if (!empty($t_data['logo_path'])): ?>
                    <div style="margin-bottom:10px;">
                        <img src="<?= BASE_URL ?>/<?= $t_data['logo_path'] ?>" alt="Current Logo" style="height:60px; border:1px solid var(--ink-200); border-radius:4px;">
                    </div>
                <?php endif; ?>
                <input type="file" name="logo" class="form-control" accept="image/*">
                <small class="text-muted">Leave blank to keep current logo. Max 2MB.</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Profile Settings</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'courses'): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Course Management</h3>
        <button class="btn btn-primary btn-sm" onclick="openModal('courseAddModal')"><i class="bi bi-plus"></i> Add Course</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Course Name</th>
                        <th>Duration</th>
                        <th>Fee (₹)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($courses)): ?>
                        <tr><td colspan="6"><div class="empty-state"><p>No courses found.</p></div></td></tr>
                    <?php else: foreach($courses as $c): ?>
                        <tr class="<?= $c['status'] === 'Inactive' ? 'row-disabled' : '' ?>">
                            <td><span class="badge dark"><?= $c['code'] ?></span></td>
                            <td><strong><?= $c['name'] ?></strong></td>
                            <td><?= $c['duration_months'] ?> months</td>
                            <td>₹<?= number_format($c['total_fee'], 0) ?></td>
                            <td><span class="badge <?= $c['status'] === 'Active' ? 'success' : 'warning' ?>"><?= $c['status'] ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-secondary btn-sm" onclick="openEditCourse(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="settings.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle_course">
                                        <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $c['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                        <button type="submit" class="btn btn-sm <?= $c['status'] === 'Active' ? 'btn-secondary' : 'btn-success' ?>">
                                            <i class="bi bi-<?= $c['status'] === 'Active' ? 'x-circle' : 'check-circle' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'batches'): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Batch Management</h3>
        <button class="btn btn-primary btn-sm" onclick="openModal('batchAddModal')"><i class="bi bi-plus"></i> Add Batch</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Batch Name</th>
                        <th>Course</th>
                        <th>Duration</th>
                        <th>Timing</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($batches)): ?>
                        <tr><td colspan="7"><div class="empty-state"><p>No batches found.</p></div></td></tr>
                    <?php else: foreach($batches as $b): ?>
                        <tr class="<?= $b['status'] === 'Inactive' ? 'row-disabled' : '' ?>">
                            <td><strong><?= $b['name'] ?></strong></td>
                            <td><?= $b['course_name'] ?></td>
                            <td>
                                <span style="font-size:0.8rem;color:var(--ink-500);">
                                    <?= date('M y', strtotime($b['start_date'] ?? 'now')) ?><?= !empty($b['end_date']) ? ' - ' . date('M y', strtotime($b['end_date'])) : '' ?>
                                </span>
                            </td>
                            <td><?= $b['timing'] ?: 'N/A' ?></td>
                            <td><?= $b['capacity'] ?></td>
                            <td><span class="badge <?= $b['status'] === 'Active' ? 'success' : 'warning' ?>"><?= $b['status'] ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-secondary btn-sm" onclick="openEditBatch(<?= htmlspecialchars(json_encode($b)) ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="settings.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle_batch">
                                        <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $b['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                        <button type="submit" class="btn btn-sm <?= $b['status'] === 'Active' ? 'btn-secondary' : 'btn-success' ?>">
                                            <i class="bi bi-<?= $b['status'] === 'Active' ? 'x-circle' : 'check-circle' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Course Modals -->
<div id="courseAddModal" class="modal-backdrop">
    <div class="modal">
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_course">
            <div class="modal-header">
                <h3>Add New Course</h3>
                <button type="button" class="modal-close" onclick="closeModal('courseAddModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Course Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. FSD-01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Full Stack Dev" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Duration (Months) <span class="text-danger">*</span></label>
                        <input type="number" name="duration" class="form-control" required min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Fee (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="fee" class="form-control" required min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('courseAddModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Course</button>
            </div>
        </form>
    </div>
</div>

<div id="courseEditModal" class="modal-backdrop">
    <div class="modal">
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_course">
            <input type="hidden" name="course_id" id="ec_id">
            <div class="modal-header">
                <h3>Edit Course</h3>
                <button type="button" class="modal-close" onclick="closeModal('courseEditModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="code" id="ec_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Name</label>
                        <input type="text" name="name" id="ec_name" class="form-control" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Duration (Months)</label>
                        <input type="number" name="duration" id="ec_dur" class="form-control" required min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Fee (₹)</label>
                        <input type="number" step="0.01" name="fee" id="ec_fee" class="form-control" required min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('courseEditModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Batch Modals -->
<div id="batchAddModal" class="modal-backdrop">
    <div class="modal">
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_batch">
            <div class="modal-header">
                <h3>Add New Batch</h3>
                <button type="button" class="modal-close" onclick="closeModal('batchAddModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-control" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach($courses as $c): if($c['status']=='Active'): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?> (<?= $c['code'] ?>)</option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Batch Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. FSD-Morning-Jan26" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Timing</label>
                        <input type="text" name="timing" class="form-control" placeholder="e.g. 10 AM - 12 PM">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Capacity <span class="text-danger">*</span></label>
                        <input type="number" name="capacity" class="form-control" value="30" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('batchAddModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Batch</button>
            </div>
        </form>
    </div>
</div>

<div id="batchEditModal" class="modal-backdrop">
    <div class="modal">
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_batch">
            <input type="hidden" name="batch_id" id="eb_id">
            <div class="modal-header">
                <h3>Edit Batch</h3>
                <button type="button" class="modal-close" onclick="closeModal('batchEditModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select name="course_id" id="eb_course" class="form-control" required>
                        <?php foreach($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Batch Name</label>
                    <input type="text" name="name" id="eb_name" class="form-control" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="eb_start" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="eb_end" class="form-control" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Timing</label>
                        <input type="text" name="timing" id="eb_timing" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" id="eb_cap" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('batchEditModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Batch</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCourse(c) {
    document.getElementById('ec_id').value = c.id;
    document.getElementById('ec_code').value = c.code;
    document.getElementById('ec_name').value = c.name;
    document.getElementById('ec_dur').value = c.duration_months;
    document.getElementById('ec_fee').value = c.total_fee;
    openModal('courseEditModal');
}
function openEditBatch(b) {
    document.getElementById('eb_id').value = b.id;
    document.getElementById('eb_course').value = b.course_id;
    document.getElementById('eb_name').value = b.name;
    document.getElementById('eb_start').value = b.start_date;
    document.getElementById('eb_end').value = b.end_date;
    document.getElementById('eb_timing').value = b.timing;
    document.getElementById('eb_cap').value = b.capacity;
    openModal('batchEditModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
