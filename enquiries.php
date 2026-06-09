<?php
// enquiries.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Handle Actions (Add, Edit, Delete, Import, Export)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'CSRF validation failed.');
        redirect('/enquiries.php');
    }

    $action = $_POST['action'] ?? '';

    // ADD ENQUIRY
    if ($action === 'add') {
        $name = sanitize($_POST['name']);
        $mobile = sanitize($_POST['mobile']);
        $email = sanitize($_POST['email']);
        $source = sanitize($_POST['source']);
        $course_id = intval($_POST['course_id']);
        $counsellor_id = !empty($_POST['counsellor_id']) ? intval($_POST['counsellor_id']) : null;
        $status = sanitize($_POST['status']);
        $notes = sanitize($_POST['notes']);

        db_insert($conn, "INSERT INTO enquiries (tenant_id, name, mobile, email, source, course_id, counsellor_id, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $tenant_id, $name, $mobile, $email, $source, $course_id, $counsellor_id, $status, $notes
        ]);

        set_flash_message('success', 'Enquiry added successfully!');
        redirect('/enquiries.php');
    }

    // EDIT ENQUIRY
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['name']);
        $mobile = sanitize($_POST['mobile']);
        $email = sanitize($_POST['email']);
        $source = sanitize($_POST['source']);
        $course_id = intval($_POST['course_id']);
        $counsellor_id = !empty($_POST['counsellor_id']) ? intval($_POST['counsellor_id']) : null;
        $status = sanitize($_POST['status']);
        $notes = sanitize($_POST['notes']);

        db_query($conn, "UPDATE enquiries SET name = ?, mobile = ?, email = ?, source = ?, course_id = ?, counsellor_id = ?, status = ?, notes = ? WHERE id = ? AND tenant_id = ?", [
            $name, $mobile, $email, $source, $course_id, $counsellor_id, $status, $notes, $id, $tenant_id
        ]);

        // If converted, automatically offer to register as student
        if ($status === 'Converted') {
            // Check if student already exists for this enquiry
            $check = db_query($conn, "SELECT id FROM students WHERE enquiry_id = ? AND tenant_id = ?", [$id, $tenant_id]);
            if (mysqli_num_rows($check) === 0) {
                set_flash_message('success', 'Enquiry marked as Converted! Redirecting to Complete Student Registration.');
                redirect('/admissions.php?enquiry_id=' . $id);
            }
        }

        set_flash_message('success', 'Enquiry updated successfully!');
        redirect('/enquiries.php');
    }

    // DELETE ENQUIRY
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        db_query($conn, "DELETE FROM enquiries WHERE id = ? AND tenant_id = ?", [$id, $tenant_id]);
        set_flash_message('success', 'Enquiry deleted successfully!');
        redirect('/enquiries.php');
    }

    // IMPORT CSV
    if ($action === 'import') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            // Skip header row
            fgetcsv($handle);
            
            $imported_count = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($data) >= 3) {
                    $name = sanitize($data[0]);
                    $mobile = sanitize($data[1]);
                    $email = sanitize($data[2]);
                    $source = sanitize($data[3] ?? 'Walk-in');
                    $course_name = sanitize($data[4] ?? '');
                    
                    // Match course or use first available course
                    $course_id = 0;
                    if (!empty($course_name)) {
                        $c_check = db_query($conn, "SELECT id FROM courses WHERE name LIKE ? AND tenant_id = ? LIMIT 1", ["%$course_name%", $tenant_id]);
                        if ($row = mysqli_fetch_assoc($c_check)) {
                            $course_id = $row['id'];
                        }
                    }
                    if ($course_id === 0) {
                        $c_check = db_query($conn, "SELECT id FROM courses WHERE tenant_id = ? LIMIT 1", [$tenant_id]);
                        if ($row = mysqli_fetch_assoc($c_check)) {
                            $course_id = $row['id'];
                        }
                    }

                    if ($course_id > 0 && !empty($name) && !empty($mobile)) {
                        db_insert($conn, "INSERT INTO enquiries (tenant_id, name, mobile, email, source, course_id, status) VALUES (?, ?, ?, ?, ?, ?, 'New')", [
                            $tenant_id, $name, $mobile, $email, $source, $course_id
                        ]);
                        $imported_count++;
                    }
                }
            }
            fclose($handle);
            set_flash_message('success', "$imported_count enquiries imported successfully!");
        } else {
            set_flash_message('danger', 'Error uploading file.');
        }
        redirect('/enquiries.php');
    }
}

// HANDLE CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Clear output buffer
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=enquiries_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Mobile', 'Email', 'Source', 'Course', 'Status', 'Notes', 'Created Date']);
    
    $res = db_query($conn, "SELECT e.*, c.name as course_name FROM enquiries e JOIN courses c ON e.course_id = c.id WHERE e.tenant_id = ?", [$tenant_id]);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($output, [
            $row['name'],
            $row['mobile'],
            $row['email'],
            $row['source'],
            $row['course_name'],
            $row['status'],
            $row['notes'],
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch Courses for select lists
$courses_res = db_query($conn, "SELECT id, name FROM courses WHERE tenant_id = ?", [$tenant_id]);
$courses = [];
while ($row = mysqli_fetch_assoc($courses_res)) { $courses[] = $row; }

// Fetch Counsellors/Users for assignments
$users_res = db_query($conn, "SELECT id, name FROM users WHERE tenant_id = ? AND role IN ('Admin', 'Counsellor')", [$tenant_id]);
$counsellors = [];
while ($row = mysqli_fetch_assoc($users_res)) { $counsellors[] = $row; }

// Filtering / Searching
$search = sanitize($_GET['search'] ?? '');
$filter_status = sanitize($_GET['status'] ?? '');
$filter_source = sanitize($_GET['source'] ?? '');

$query = "SELECT e.*, c.name as course_name, u.name as counsellor_name 
          FROM enquiries e 
          JOIN courses c ON e.course_id = c.id 
          LEFT JOIN users u ON e.counsellor_id = u.id 
          WHERE e.tenant_id = ?";
$params = [$tenant_id];

if (!empty($search)) {
    $query .= " AND (e.name LIKE ? OR e.mobile LIKE ? OR e.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filter_status)) {
    $query .= " AND e.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_source)) {
    $query .= " AND e.source = ?";
    $params[] = $filter_source;
}

$query .= " ORDER BY e.id DESC";
$enquiries = db_query($conn, $query, $params);
?>

<div class="page-header">
    <h2><i class="bi bi-telephone-inbound" style="color:var(--primary);margin-right:8px;"></i>Enquiry Management</h2>
    <div class="page-header-actions">
        <button class="btn btn-secondary" onclick="openModal('importModal')"><i class="bi bi-upload"></i> Import CSV</button>
        <a href="enquiries.php?export=csv" class="btn btn-secondary"><i class="bi bi-download"></i> Export CSV</a>
        <button class="btn btn-primary" onclick="openModal('addModal')"><i class="bi bi-plus"></i> Add Enquiry</button>
    </div>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px;">
        <form method="GET" action="enquiries.php" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name, mobile, email..." value="<?= $search ?>">
            </div>
            <div style="width: 150px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="New" <?= $filter_status === 'New' ? 'selected' : '' ?>>New</option>
                    <option value="Follow-up" <?= $filter_status === 'Follow-up' ? 'selected' : '' ?>>Follow-up</option>
                    <option value="Converted" <?= $filter_status === 'Converted' ? 'selected' : '' ?>>Converted</option>
                    <option value="Lost" <?= $filter_status === 'Lost' ? 'selected' : '' ?>>Lost</option>
                </select>
            </div>
            <div style="width: 150px;">
                <label class="form-label">Source</label>
                <select name="source" class="form-control">
                    <option value="">All</option>
                    <option value="Website" <?= $filter_source === 'Website' ? 'selected' : '' ?>>Website</option>
                    <option value="Call" <?= $filter_source === 'Call' ? 'selected' : '' ?>>Call</option>
                    <option value="Walk-in" <?= $filter_source === 'Walk-in' ? 'selected' : '' ?>>Walk-in</option>
                    <option value="Social Media" <?= $filter_source === 'Social Media' ? 'selected' : '' ?>>Social Media</option>
                    <option value="Other" <?= $filter_source === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="padding: 11px 20px;"><i class="bi bi-funnel"></i> Filter</button>
                <a href="enquiries.php" class="btn btn-secondary" style="padding: 11px 20px;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Enquiries List Card -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="bi bi-list-ul"></i> All Enquiries</span>
        <span class="text-muted" style="font-size:12px;">Showing filtered results</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Source</th>
                        <th>Course Interested</th>
                        <th>Counsellor</th>
                        <th>Status</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($enquiries) === 0): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>No enquiries found. Add one or adjust your filters.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($enquiries)): ?>
                            <tr>
                                <td>
                                    <strong><?= $row['name'] ?></strong>
                                    <div class="text-muted">Registered: <?= date('d M Y', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div><?= $row['mobile'] ?></div>
                                    <div class="text-muted"><?= $row['email'] ?></div>
                                </td>
                                <td><span class="badge primary"><?= $row['source'] ?></span></td>
                                <td><?= $row['course_name'] ?></td>
                                <td><?= $row['counsellor_name'] ?? '<span class="text-muted">Unassigned</span>' ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'primary';
                                    if ($row['status'] === 'Converted') $badge_class = 'success';
                                    if ($row['status'] === 'Follow-up') $badge_class = 'warning';
                                    if ($row['status'] === 'Lost') $badge_class = 'danger';
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= $row['status'] ?></span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn btn-secondary btn-sm btn-icon" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-danger btn-sm btn-icon" onclick="openDeleteModal(<?= $row['id'] ?>)" title="Delete"><i class="bi bi-trash3"></i></button>
                                        <a href="<?= BASE_URL ?>/followups.php?enquiry_id=<?= $row['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Follow-ups"><i class="bi bi-chat-left-text"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Enquiry -->
<div id="addModal" class="modal-backdrop">
    <div class="modal">
        <form action="enquiries.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h3><i class="bi bi-person-plus" style="color:var(--primary);margin-right:6px;"></i>Add New Enquiry</h3>
                <button type="button" class="modal-close" onclick="closeModal('addModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="mobile" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <select name="source" class="form-control">
                            <option value="Walk-in">Walk-in</option>
                            <option value="Website">Website</option>
                            <option value="Call">Call</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Interested In</label>
                        <select name="course_id" class="form-control" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assign Counsellor</label>
                        <select name="counsellor_id" class="form-control">
                            <option value="">Select Counsellor</option>
                            <?php foreach ($counsellors as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= $u['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="New">New</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Converted">Converted</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Conversation Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Enquiry</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Enquiry -->
<div id="editModal" class="modal-backdrop">
    <div class="modal">
        <form action="enquiries.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id" name="id">
            <div class="modal-header">
                <h3><i class="bi bi-pencil-square" style="color:var(--primary);margin-right:6px;"></i>Edit Enquiry</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student Name</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" id="edit_mobile" name="mobile" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" id="edit_email" name="email" class="form-control">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <select id="edit_source" name="source" class="form-control">
                            <option value="Walk-in">Walk-in</option>
                            <option value="Website">Website</option>
                            <option value="Call">Call</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Interested In</label>
                        <select id="edit_course_id" name="course_id" class="form-control" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assign Counsellor</label>
                        <select id="edit_counsellor_id" name="counsellor_id" class="form-control">
                            <option value="">Select Counsellor</option>
                            <?php foreach ($counsellors as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= $u['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="New">New</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Converted">Converted</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Conversation Notes</label>
                    <textarea id="edit_notes" name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Enquiry</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div id="deleteModal" class="modal-backdrop">
    <div class="modal" style="max-width: 400px;">
        <form action="enquiries.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete_id" name="id">
            <div class="modal-header">
                <h3>Delete Enquiry</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this enquiry? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Import CSV -->
<div id="importModal" class="modal-backdrop">
    <div class="modal" style="max-width: 450px;">
        <form action="enquiries.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="import">
            <div class="modal-header">
                <h3><i class="bi bi-upload" style="color:var(--primary);margin-right:6px;"></i>Import Enquiries from CSV</h3>
                <button type="button" class="modal-close" onclick="closeModal('importModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Choose CSV File</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    <p style="font-size: 0.75rem; color: var(--slate-400); margin-top: 8px;">
                        Make sure the CSV has the following headers:<br>
                        <strong>Name, Mobile, Email, Source, Course</strong>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Import Now</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById("edit_id").value = data.id;
    document.getElementById("edit_name").value = data.name;
    document.getElementById("edit_mobile").value = data.mobile;
    document.getElementById("edit_email").value = data.email || '';
    document.getElementById("edit_source").value = data.source;
    document.getElementById("edit_course_id").value = data.course_id;
    document.getElementById("edit_counsellor_id").value = data.counsellor_id || '';
    document.getElementById("edit_status").value = data.status;
    document.getElementById("edit_notes").value = data.notes || '';
    openModal("editModal");
}

function openDeleteModal(id) {
    document.getElementById("delete_id").value = id;
    openModal("deleteModal");
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
