<?php
// attendance.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Fetch Batches for dropdown
$batches_res = db_query($conn, "SELECT b.id, b.name, c.name as course_name FROM batches b JOIN courses c ON b.course_id = c.id WHERE b.tenant_id = ?", [$tenant_id]);
$batches = [];
while ($row = mysqli_fetch_assoc($batches_res)) { $batches[] = $row; }

$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$attendance_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

// Handle Bulk Attendance Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $b_id = intval($_POST['batch_id']);
        $a_date = sanitize($_POST['attendance_date']);
        $statuses = $_POST['status'] ?? []; // student_id => status

        mysqli_begin_transaction($conn);
        try {
            foreach ($statuses as $student_id => $status) {
                $student_id = intval($student_id);
                $status = sanitize($status);
                
                // Use REPLACE INTO or INSERT ... ON DUPLICATE KEY UPDATE
                db_query($conn, "
                    INSERT INTO attendance (tenant_id, student_id, batch_id, attendance_date, status) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)", [
                    $tenant_id, $student_id, $b_id, $a_date, $status
                ]);
            }
            mysqli_commit($conn);
            set_flash_message('success', 'Attendance recorded successfully!');
        } catch (Exception $e) {
            mysqli_rollback($conn);
            set_flash_message('danger', 'Error recording attendance.');
        }
        redirect("/attendance.php?batch_id=$b_id&date=$a_date");
    }
}

// Handle QR Check-in Simulator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'qr_checkin') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $roll = sanitize($_POST['roll_number']);
        $a_date = sanitize($_POST['attendance_date']);
        $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
        
        // Find student
        $student_res = db_query($conn, "SELECT id, batch_id FROM students WHERE roll_number = ? AND tenant_id = ?", [$roll, $tenant_id]);
        $student = mysqli_fetch_assoc($student_res);
        
        if ($student) {
            db_query($conn, "
                INSERT INTO attendance (tenant_id, student_id, batch_id, attendance_date, status, notes) 
                VALUES (?, ?, ?, ?, 'Present', 'Checked in via QR Scanner')
                ON DUPLICATE KEY UPDATE status = 'Present', notes = 'Checked in via QR Scanner'", [
                $tenant_id, $student['id'], $student['batch_id'], $a_date
            ]);
            
            if ($is_ajax) {
                echo json_encode(['status' => 'success', 'message' => "Roll No: $roll checked in successfully!"]);
                exit;
            } else {
                set_flash_message('success', "Roll No: $roll checked in successfully!");
                redirect("/attendance.php?batch_id=" . $student['batch_id'] . "&date=$a_date");
            }
        } else {
            if ($is_ajax) {
                echo json_encode(['status' => 'error', 'message' => "Student with Roll Number $roll not found!"]);
                exit;
            } else {
                set_flash_message('danger', "Student with Roll Number $roll not found!");
                redirect('/attendance.php');
            }
        }
    }
}

// Fetch students of the selected batch
$students = [];
if ($batch_id > 0) {
    // Fetch students along with their attendance status for that date
    $students_res = db_query($conn, "
        SELECT s.id, s.name, s.roll_number, a.status as attendance_status 
        FROM students s 
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ?
        WHERE s.batch_id = ? AND s.tenant_id = ? AND s.status = 'Active'", [$attendance_date, $batch_id, $tenant_id]);
    while ($row = mysqli_fetch_assoc($students_res)) {
        $students[] = $row;
    }
}

// Fetch Monthly attendance summary report
$summary = db_query($conn, "
    SELECT s.name, s.roll_number, b.name as batch_name,
           COUNT(a.id) as total_days,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    LEFT JOIN attendance a ON s.id = a.student_id AND MONTH(a.attendance_date) = MONTH(CURDATE())
    WHERE s.tenant_id = ?
    GROUP BY s.id LIMIT 10", [$tenant_id]);
?>

<div class="page-header">
    <h2><i class="bi bi-calendar-check-fill" style="color:var(--primary);margin-right:8px;"></i>Attendance Management</h2>
    <button class="btn btn-secondary" onclick="startQrScanner()"><i class="bi bi-qr-code-scan"></i> QR / Roll No Check-in</button>
</div>

<!-- Select Batch & Date Form -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form method="GET" action="attendance.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Select Batch</label>
                <select name="batch_id" class="form-control" required>
                    <option value="">Choose Batch...</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= $b['name'] ?> (<?= $b['course_name'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="width: 180px;">
                <label class="form-label">Attendance Date</label>
                <input type="date" name="date" class="form-control" value="<?= $attendance_date ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 11px 20px;"><i class="bi bi-search"></i> Load Roll Call</button>
        </form>
    </div>
</div>

<div class="grid-2">
    <!-- Attendance Entry Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-journal-check"></i> Daily Attendance Sheet</h3>
            <?php if ($batch_id > 0): ?>
                <span class="badge primary"><?= date('d M Y', strtotime($attendance_date)) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($batch_id == 0): ?>
                <div class="empty-state">
                    <i class="bi bi-arrow-up-circle"></i>
                    <p>Select a batch and date above to load the attendance sheet.</p>
                </div>
            <?php elseif (empty($students)): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <p>No active students found in this batch.</p>
                </div>
            <?php else: ?>
                <form action="attendance.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="save_attendance">
                    <input type="hidden" name="batch_id" value="<?= $batch_id ?>">
                    <input type="hidden" name="attendance_date" value="<?= $attendance_date ?>">
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Roll Call</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><strong><?= $s['roll_number'] ?></strong></td>
                                        <td><?= $s['name'] ?></td>
                                        <td>
                                            <div style="display:flex;gap:12px;">
                                                <label style="cursor:pointer;display:flex;align-items:center;gap:4px;color:var(--success);">
                                                    <input type="radio" name="status[<?= $s['id'] ?>]" value="Present" <?= ($s['attendance_status'] === 'Present' || is_null($s['attendance_status'])) ? 'checked' : '' ?>> Present
                                                </label>
                                                <label style="cursor:pointer;display:flex;align-items:center;gap:4px;color:var(--danger);">
                                                    <input type="radio" name="status[<?= $s['id'] ?>]" value="Absent" <?= $s['attendance_status'] === 'Absent' ? 'checked' : '' ?>> Absent
                                                </label>
                                                <label style="cursor:pointer;display:flex;align-items:center;gap:4px;color:var(--warning);">
                                                    <input type="radio" name="status[<?= $s['id'] ?>]" value="Late" <?= $s['attendance_status'] === 'Late' ? 'checked' : '' ?>> Late
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:20px;"><i class="bi bi-save"></i> Save Attendance Sheet</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Summary / Statistics -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-bar-chart-line"></i> Monthly Summary (Current Month)</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Batch</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($summary) === 0): ?>
                            <tr><td colspan="3">
                                <div class="empty-state">
                                    <i class="bi bi-calendar2-x"></i>
                                    <p>No attendance records for this month.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($summary)): ?>
                                <tr>
                                    <td>
                                        <strong><?= $row['name'] ?></strong>
                                        <div class="text-muted">Roll: <?= $row['roll_number'] ?></div>
                                    </td>
                                    <td><?= $row['batch_name'] ?></td>
                                    <td>
                                        <?php
                                        $percent = $row['total_days'] > 0 ? round(($row['present_days'] / $row['total_days']) * 100) : 100;
                                        $color = $percent >= 75 ? 'success' : 'danger';
                                        ?>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span class="badge <?= $color ?>"><?= $percent ?>%</span>
                                            <span class="text-muted">(<?= $row['present_days'] ?>/<?= $row['total_days'] ?> days)</span>
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
</div>

<!-- Modal: QR / Roll Code Check-in Simulator -->
<div id="qrCheckinModal" class="modal-backdrop">
    <div class="modal" style="max-width: 420px;">
        <form id="qrCheckinForm" action="attendance.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="qr_checkin">
            <input type="hidden" name="attendance_date" value="<?= date('Y-m-d') ?>">
            <div class="modal-header">
                <h3><i class="bi bi-qr-code-scan" style="color:var(--primary);margin-right:6px;"></i>QR / Roll Call Scanner</h3>
                <button type="button" class="modal-close" onclick="closeModal('qrCheckinModal'); if(html5QrcodeScanner) html5QrcodeScanner.clear();"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <div id="reader" style="width: 100%; margin-bottom: 15px;"></div>
                <div style="padding: 20px; border: 2px dashed var(--primary); border-radius: 8px; margin-bottom: 20px; background-color: var(--primary-light);">
                    <i class="bi bi-qr-code-scan" style="font-size: 3rem; color: var(--primary);"></i>
                    <p style="font-size: 0.85rem; color: var(--slate-600); margin-top: 8px;">Scan student ID card or manually enter Roll Number below to instantly log attendance as <strong>Present</strong> for today.</p>
                </div>
                <div class="form-group">
                    <label class="form-label" style="text-align: left;">Enter Roll Number</label>
                    <input type="text" name="roll_number" id="roll_number_input" class="form-control" placeholder="E.g., WD-101-2026-4859" required autofocus>
                </div>
                <div id="qrMessageArea" style="margin-top: 15px; font-size: 1rem; min-height: 24px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('qrCheckinModal'); if(html5QrcodeScanner) html5QrcodeScanner.clear(); window.location.reload();">Done</button>
                <button type="submit" class="btn btn-primary" id="btnManualSubmit">Submit Check-in</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
let html5QrcodeScanner = null;
let isScanning = false;

function startQrScanner() {
    openModal('qrCheckinModal');
    document.getElementById('qrMessageArea').innerHTML = '';
    
    if (!html5QrcodeScanner) {
        html5QrcodeScanner = new Html5QrcodeScanner(
            "reader",
            { fps: 10, qrbox: {width: 250, height: 250} },
            /* verbose= */ false);
    }
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
}

function onScanSuccess(decodedText, decodedResult) {
    if(isScanning) return;
    isScanning = true;
    
    document.getElementById('roll_number_input').value = decodedText;
    
    const msgArea = document.getElementById('qrMessageArea');
    msgArea.innerHTML = '<div style="color:var(--primary); font-weight:600;"><i class="bi bi-hourglass-split"></i> Processing ' + decodedText + '...</div>';
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('action', 'qr_checkin');
    formData.append('attendance_date', '<?= date('Y-m-d') ?>');
    formData.append('roll_number', decodedText);
    formData.append('ajax', '1');
    
    fetch('attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            msgArea.innerHTML = '<div style="color:var(--success); font-weight:600;"><i class="bi bi-check-circle-fill"></i> ' + data.message + '</div>';
        } else {
            msgArea.innerHTML = '<div style="color:var(--danger); font-weight:600;"><i class="bi bi-x-circle-fill"></i> ' + data.message + '</div>';
        }
        
        setTimeout(() => {
            isScanning = false;
        }, 2000);
    })
    .catch(error => {
        msgArea.innerHTML = '<div style="color:var(--danger); font-weight:600;"><i class="bi bi-x-circle-fill"></i> Network Error. Try again.</div>';
        setTimeout(() => { isScanning = false; }, 2000);
    });
}

function onScanFailure(error) {
    // Ignore frame failures
}

document.getElementById('qrCheckinForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const roll = document.getElementById('roll_number_input').value;
    if(roll) onScanSuccess(roll, null);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
