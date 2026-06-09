<?php
// admissions.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

$enquiry_id = isset($_GET['enquiry_id']) ? intval($_GET['enquiry_id']) : 0;
$prefill = null;

if ($enquiry_id > 0) {
    $prefill_res = db_query($conn, "SELECT * FROM enquiries WHERE id = ? AND tenant_id = ?", [$enquiry_id, $tenant_id]);
    $prefill = mysqli_fetch_assoc($prefill_res);
}

// Handle Admission Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_student') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $enq_id = !empty($_POST['enquiry_id']) ? intval($_POST['enquiry_id']) : null;
        $name = sanitize($_POST['name']);
        $mobile = sanitize($_POST['mobile']);
        $email = sanitize($_POST['email']);
        $course_id = intval($_POST['course_id']);
        $batch_id = intval($_POST['batch_id']);
        $admission_date = sanitize($_POST['admission_date']);
        $roll_number = sanitize($_POST['roll_number']);
        $initial_payment = floatval($_POST['initial_payment']);

        // Insert Student Record
        $student_id = db_insert($conn, "INSERT INTO students (tenant_id, enquiry_id, name, mobile, email, roll_number, course_id, batch_id, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')", [
            $tenant_id, $enq_id, $name, $mobile, $email, $roll_number, $course_id, $batch_id, $admission_date
        ]);

        // If enquiry was linked, mark it converted
        if ($enq_id) {
            db_query($conn, "UPDATE enquiries SET status = 'Converted' WHERE id = ? AND tenant_id = ?", [$enq_id, $tenant_id]);
        }

        // If initial payment was made, log a fee payment receipt
        if ($initial_payment > 0) {
            $receipt_num = $tenant_prefix . "-REC-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
            db_insert($conn, "INSERT INTO fee_payments (tenant_id, student_id, amount_paid, payment_date, payment_mode, receipt_number, notes) VALUES (?, ?, ?, ?, 'Cash', ?, 'Initial Admission Payment')", [
                $tenant_id, $student_id, $initial_payment, $admission_date, $receipt_num
            ]);
        }

        set_flash_message('success', 'Student registered and admitted successfully!');
        redirect('/admissions.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_student') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $s_id = intval($_POST['student_id']);
        $name = sanitize($_POST['name']);
        $mobile = sanitize($_POST['mobile']);
        $email = sanitize($_POST['email']);
        $course_id = intval($_POST['course_id']);
        $batch_id = intval($_POST['batch_id']);
        $admission_date = sanitize($_POST['admission_date']);
        
        db_query($conn, "UPDATE students SET name=?, mobile=?, email=?, course_id=?, batch_id=?, admission_date=? WHERE id=? AND tenant_id=?", 
            [$name, $mobile, $email, $course_id, $batch_id, $admission_date, $s_id, $tenant_id]);
        set_flash_message('success', 'Student details updated successfully.');
        redirect('/admissions.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $s_id = intval($_POST['student_id']);
        $pay_check = db_query($conn, "SELECT id FROM fee_payments WHERE student_id=? AND tenant_id=?", [$s_id, $tenant_id]);
        if (mysqli_num_rows($pay_check) > 0) {
            set_flash_message('danger', 'Cannot delete student with existing fee payments.');
        } else {
            db_query($conn, "DELETE FROM attendance WHERE student_id=? AND tenant_id=?", [$s_id, $tenant_id]);
            db_query($conn, "DELETE FROM students WHERE id=? AND tenant_id=?", [$s_id, $tenant_id]);
            set_flash_message('success', 'Student deleted successfully.');
        }
        redirect('/admissions.php');
    }
}

// Fetch Courses and Batches for dropdowns
$courses = [];
$res = db_query($conn, "SELECT id, name, code, total_fee FROM courses WHERE tenant_id = ?", [$tenant_id]);
while ($r = mysqli_fetch_assoc($res)) { $courses[] = $r; }

$batches = [];
$res = db_query($conn, "SELECT b.*, c.name as course_name FROM batches b JOIN courses c ON b.course_id = c.id WHERE b.tenant_id = ? AND b.status = 'Active'", [$tenant_id]);
while ($r = mysqli_fetch_assoc($res)) { $batches[] = $r; }

// Fetch admitted students list
$search = sanitize($_GET['search'] ?? '');
$query = "SELECT s.*, c.name as course_name, b.name as batch_name 
          FROM students s 
          JOIN courses c ON s.course_id = c.id 
          JOIN batches b ON s.batch_id = b.id 
          WHERE s.tenant_id = ?";
$params = [$tenant_id];

if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR s.roll_number LIKE ? OR s.mobile LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY s.id DESC";
$students = db_query($conn, $query, $params);
?>

<div class="page-header">
    <h2><i class="bi bi-person-check-fill" style="color:var(--primary);margin-right:8px;"></i>Admission & Registration</h2>
    <button class="btn btn-primary" onclick="openModal('admissionFormModal')"><i class="bi bi-person-plus-fill"></i> New Admission Form</button>
</div>

<!-- Search box -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" action="admissions.php" style="display: flex; gap: 16px;">
            <input type="text" name="search" class="form-control" placeholder="Search by student name, roll number, mobile..." value="<?= $search ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="admissions.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Admitted Students Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-people-fill"></i> Admitted Students</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Roll Number</th>
                        <th>Student Name</th>
                        <th>Course & Batch</th>
                        <th>Admission Date</th>
                        <th>Contact</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($students) === 0): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-person-x"></i>
                                <p>No active admissions found. Register your first student.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($students)): ?>
                            <tr>
                                <td><span class="badge dark"><?= $row['roll_number'] ?></span></td>
                                <td><strong><?= $row['name'] ?></strong></td>
                                <td>
                                    <div><?= $row['course_name'] ?></div>
                                    <div class="text-muted"><?= $row['batch_name'] ?></div>
                                </td>
                                <td><?= date('d M Y', strtotime($row['admission_date'])) ?></td>
                                <td><?= $row['mobile'] ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn btn-secondary btn-sm" onclick='openIdCard(<?= htmlspecialchars(json_encode($row)) ?>)' title="ID Card"><i class="bi bi-card-image"></i> ID</button>
                                        <button class="btn btn-primary-soft btn-sm btn-icon" onclick='editAdmission(<?= htmlspecialchars(json_encode($row)) ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                                        <?php
                                        $conf_msg = urlencode("Dear " . $row['name'] . ", Welcome to " . $_SESSION['tenant_name'] . "! Your admission for " . $row['course_name'] . " is confirmed. Roll No: " . $row['roll_number'] . ".");
                                        ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['mobile']) ?>?text=<?= $conf_msg ?>" target="_blank" class="btn btn-success btn-sm btn-icon" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger-soft btn-sm btn-icon" onclick="return confirm('Delete this admission? This action cannot be undone.')" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
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

<!-- Modal: New Admission Form -->
<div id="admissionFormModal" class="modal-backdrop" <?= ($prefill ? 'style="display:flex;"' : '') ?>>
    <div class="modal" style="max-width: 600px;">
        <form action="admissions.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="register_student">
            <?php if ($prefill): ?>
                <input type="hidden" name="enquiry_id" value="<?= $prefill['id'] ?>">
            <?php endif; ?>

            <div class="modal-header">
                <h3><i class="bi bi-person-plus-fill" style="color:var(--primary);margin-right:6px;"></i>Student Admission Form</h3>
                <a href="admissions.php" class="modal-close"><i class="bi bi-x-lg"></i></a>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= $prefill ? $prefill['name'] : '' ?>" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="mobile" class="form-control" value="<?= $prefill ? $prefill['mobile'] : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= $prefill ? $prefill['email'] : '' ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Course Allocation</label>
                        <select name="course_id" id="courseSelect" class="form-control" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($prefill && $prefill['course_id'] == $c['id'] ? 'selected' : '') ?> data-fee="<?= $c['total_fee'] ?>" data-code="<?= $c['code'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Allocation</label>
                        <select name="batch_id" class="form-control" required>
                            <option value="">Select Batch</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($prefill && $prefill['course_id'] == $b['course_id'] ? 'selected' : '') ?>><?= $b['name'] ?> (<?= $b['course_name'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Roll Number (Generated)</label>
                        <input type="text" name="roll_number" id="rollNumberField" class="form-control" readonly required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admission Date</label>
                        <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Initial Fee Paid (INR)</label>
                    <input type="number" name="initial_payment" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <a href="admissions.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Submit Admission</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Admission -->
<div id="editAdmissionModal" class="modal-backdrop">
    <div class="modal" style="max-width: 600px;">
        <form action="admissions.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_student">
            <input type="hidden" name="student_id" id="e_student_id">

            <div class="modal-header">
                <h3><i class="bi bi-pencil-square" style="color:var(--primary);margin-right:6px;"></i>Edit Admission Details</h3>
                <button type="button" class="modal-close" onclick="closeModal('editAdmissionModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student Full Name</label>
                    <input type="text" name="name" id="e_name" class="form-control" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="mobile" id="e_mobile" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="e_email" class="form-control">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <select name="course_id" id="e_course_id" class="form-control" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch</label>
                        <select name="batch_id" id="e_batch_id" class="form-control" required>
                            <option value="">Select Batch</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['name'] ?> (<?= $b['course_name'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Admission Date</label>
                    <input type="date" name="admission_date" id="e_admission_date" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAdmissionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: ID Card View -->
<div id="idCardModal" class="modal-backdrop">
    <div class="modal" style="max-width: 400px; background-color: var(--slate-100);">
            <div class="modal-header">
                <h3><i class="bi bi-card-image" style="color:var(--primary);margin-right:6px;"></i>Student ID Card</h3>
                <button type="button" class="modal-close" onclick="closeModal('idCardModal')"><i class="bi bi-x-lg"></i></button>
            </div>
        <div class="modal-body" style="display: flex; justify-content: center; align-items: center; padding: 32px 0;">
            <!-- Beautiful Printable ID Card -->
            <div id="idCardContainer" style="width:280px;background:#fff;border-radius:16px;box-shadow:var(--shadow-md);border:2px solid var(--primary);text-align:center;font-family:var(--font-ui);overflow:hidden;">
                <div style="background:var(--primary);color:#fff;padding:20px 10px;font-family:var(--font-display);">
                    <?php if ($show_logo_id && $tenant_logo && file_exists(dirname(__DIR__) . '/' . $tenant_logo)): ?>
                        <img src="<?= BASE_URL ?>/<?= $tenant_logo ?>" alt="Logo" style="max-height:40px; margin-bottom:8px;">
                    <?php elseif ($show_logo_id): ?>
                        <h3 style="margin:0;font-size:1.1rem;text-transform:uppercase;"><?= htmlspecialchars($_SESSION['tenant_name'] ?? '') ?></h3>
                    <?php endif; ?>
                    <div style="font-size:0.7rem;opacity:.85;margin-top:4px;">STUDENT IDENTIFICATION CARD</div>
                </div>
                <div style="padding:22px 16px;">
                    <div style="width:72px;height:72px;border-radius:50%;background:var(--ink-100);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--ink-500);font-weight:700;border:3px solid var(--brand-100);">
                        <span id="idCardInitials">S</span>
                    </div>
                    <h4 id="idCardName" style="font-size:1.05rem;font-weight:700;margin-bottom:4px;color:var(--ink-900);">Student Name</h4>
                    <p id="idCardRoll" style="font-size:0.82rem;font-weight:600;color:var(--primary);margin-bottom:14px;">Roll: INST-0001</p>
                    <div style="text-align:left;font-size:0.78rem;border-top:1px solid var(--ink-200);padding-top:10px;color:var(--ink-700);">
                        <div style="margin-bottom:4px;"><strong>Course:</strong> <span id="idCardCourse">N/A</span></div>
                        <div style="margin-bottom:4px;"><strong>Batch:</strong> <span id="idCardBatch">N/A</span></div>
                        <div><strong>Phone:</strong> <span id="idCardPhone">N/A</span></div>
                    </div>
                    <?php if ($show_qr_id): ?>
                    <div style="margin-top: 12px;">
                        <img id="idCardQR" src="" alt="QR Code" style="width: 70px; height: 70px; border: 2px solid var(--ink-200); padding: 2px; border-radius: 4px;">
                    </div>
                    <?php endif; ?>
                </div>
                <div style="background:var(--ink-900);color:#fff;padding:6px;font-size:0.68rem;">Valid until completion of study</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('idCardModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="printReceipt('idCardContainer')"><i class="bi bi-printer"></i> Print</button>
            <button type="button" class="btn btn-success" onclick="downloadPDF('idCardContainer', 'ID_Card')"><i class="bi bi-file-pdf"></i> Download PDF</button>
        </div>
    </div>
</div>

<script>
// Auto generation of Roll Number
const courseSelect = document.getElementById('courseSelect');
const rollNumberField = document.getElementById('rollNumberField');

function generateRoll() {
    if (courseSelect.selectedIndex > 0) {
        const option = courseSelect.options[courseSelect.selectedIndex];
        const code = option.getAttribute('data-code');
        const rand = Math.floor(1000 + Math.random() * 9000);
        rollNumberField.value = `<?= $tenant_prefix ?>-${code}-${rand}`;
    } else {
        rollNumberField.value = '';
    }
}

if (courseSelect) {
    courseSelect.addEventListener('change', generateRoll);
    // trigger on load if editing/prefilled
    generateRoll();
}

function editAdmission(student) {
    document.getElementById('e_student_id').value = student.id;
    document.getElementById('e_name').value = student.name;
    document.getElementById('e_mobile').value = student.mobile;
    document.getElementById('e_email').value = student.email;
    document.getElementById('e_course_id').value = student.course_id;
    document.getElementById('e_batch_id').value = student.batch_id;
    document.getElementById('e_admission_date').value = student.admission_date.split(' ')[0];
    openModal('editAdmissionModal');
}

function openIdCard(student) {
    document.getElementById('idCardInitials').textContent = student.name.charAt(0).toUpperCase();
    document.getElementById('idCardName').textContent = student.name;
    document.getElementById('idCardRoll').textContent = 'Roll: ' + student.roll_number;
    document.getElementById('idCardCourse').textContent = student.course_name;
    document.getElementById('idCardBatch').textContent = student.batch_name;
    document.getElementById('idCardPhone').textContent = student.mobile;
    
    const qrEl = document.getElementById('idCardQR');
    if (qrEl) {
        qrEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(student.roll_number);
    }
    
    openModal('idCardModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
