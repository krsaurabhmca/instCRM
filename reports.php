<?php
// reports.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

$type = $_GET['type'] ?? 'attendance';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Filter params
$status_filter = $_GET['status'] ?? '';
$batch_filter = $_GET['batch_id'] ?? '';

// --- Fetch Data Based on Type ---
$results = null;

if ($type === 'attendance') {
    $q = "SELECT a.attendance_date, a.status, s.name, s.roll_number, b.name as batch_name 
          FROM attendance a 
          JOIN students s ON a.student_id = s.id 
          JOIN batches b ON s.batch_id = b.id 
          WHERE a.tenant_id = ? AND a.attendance_date BETWEEN ? AND ?";
    $params = [$tenant_id, $start_date, $end_date];
    
    if ($batch_filter) {
        $q .= " AND s.batch_id = ?";
        $params[] = $batch_filter;
    }
    $q .= " ORDER BY a.attendance_date DESC";
    $results = db_query($conn, $q, $params);
    
    // Summary
    $present_count = 0; $absent_count = 0; $late_count = 0;
    
} elseif ($type === 'materials') {
    $q = "SELECT m.issue_date, m.return_date, m.status, mat.title as book_title, s.name, s.roll_number 
          FROM material_issues m 
          JOIN materials mat ON m.material_id = mat.id 
          JOIN students s ON m.student_id = s.id 
          WHERE m.tenant_id = ? AND m.issue_date BETWEEN ? AND ?";
    $params = [$tenant_id, $start_date, $end_date];
    
    if ($status_filter) {
        $q .= " AND m.status = ?";
        $params[] = $status_filter;
    }
    $q .= " ORDER BY m.issue_date DESC";
    $results = db_query($conn, $q, $params);
    
} elseif ($type === 'fees') {
    $q = "SELECT f.payment_date, f.amount_paid, f.payment_mode, f.receipt_number, s.name, s.roll_number, c.name as course_name 
          FROM fee_payments f 
          JOIN students s ON f.student_id = s.id 
          JOIN courses c ON s.course_id = c.id 
          WHERE f.tenant_id = ? AND f.payment_date BETWEEN ? AND ?";
    $params = [$tenant_id, $start_date, $end_date];
    $q .= " ORDER BY f.payment_date DESC";
    $results = db_query($conn, $q, $params);
    
    $dues_res = db_query($conn, "
        SELECT s.name, s.roll_number, c.name as course_name, c.total_fee, 
               COALESCE((SELECT SUM(amount_paid) FROM fee_payments WHERE student_id = s.id), 0) as paid_fee 
        FROM students s JOIN courses c ON s.course_id = c.id 
        WHERE s.tenant_id = ? 
        HAVING paid_fee < total_fee", [$tenant_id]);
        
} elseif ($type === 'followups') {
    $q = "SELECT f.followup_date, f.status, f.call_notes, e.name as student_name, e.mobile, c.name as course_name 
          FROM followups f 
          JOIN enquiries e ON f.enquiry_id = e.id 
          JOIN courses c ON e.course_id = c.id 
          WHERE f.tenant_id = ? AND DATE(f.followup_date) BETWEEN ? AND ?";
    $params = [$tenant_id, $start_date, $end_date];
    
    if ($status_filter) {
        $q .= " AND f.status = ?";
        $params[] = $status_filter;
    }
    $q .= " ORDER BY f.followup_date DESC";
    $results = db_query($conn, $q, $params);
}

// Helper query for batch dropdown
$batches = db_query($conn, "SELECT id, name FROM batches WHERE tenant_id = ? AND status='Active'", [$tenant_id]);
?>

<div class="page-header">
    <h2><i class="bi bi-pie-chart-fill" style="color:var(--primary);margin-right:8px;"></i>Reports & Analytics</h2>
</div>

<!-- Tabs -->
<div style="margin-bottom: 24px; border-bottom: 1px solid var(--ink-200); display: flex; gap: 16px;">
    <a href="reports.php?type=attendance" class="btn <?= $type === 'attendance' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Attendance</a>
    <a href="reports.php?type=materials" class="btn <?= $type === 'materials' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Materials</a>
    <a href="reports.php?type=fees" class="btn <?= $type === 'fees' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Fees & Dues</a>
    <a href="reports.php?type=followups" class="btn <?= $type === 'followups' ? 'btn-primary' : 'btn-secondary' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">Follow-ups</a>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form action="reports.php" method="GET" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            
            <div class="form-group" style="margin:0;">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required>
            </div>
            
            <?php if ($type === 'attendance'): ?>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Batch</label>
                    <select name="batch_id" class="form-control">
                        <option value="">All Batches</option>
                        <?php while($b = mysqli_fetch_assoc($batches)): ?>
                            <option value="<?= $b['id'] ?>" <?= $batch_filter == $b['id'] ? 'selected' : '' ?>><?= $b['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if ($type === 'materials'): ?>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="Issued" <?= $status_filter === 'Issued' ? 'selected' : '' ?>>Issued</option>
                        <option value="Returned" <?= $status_filter === 'Returned' ? 'selected' : '' ?>>Returned</option>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if ($type === 'followups'): ?>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div style="margin:0;">
                    <a href="reports.php?type=followups&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>&status=Pending" class="btn btn-warning"><i class="bi bi-clock"></i> Today's Pending</a>
                </div>
            <?php endif; ?>
            
            <div style="margin:0;">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Data -->
<?php if ($type === 'attendance'): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Attendance Report</h3>
            <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Batch</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($results) === 0): ?>
                            <tr><td colspan="4"><div class="empty-state"><p>No attendance data found for this period.</p></div></td></tr>
                        <?php else: ?>
                            <?php while ($r = mysqli_fetch_assoc($results)): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
                                    <td><strong><?= $r['name'] ?></strong> <span class="text-muted">(<?= $r['roll_number'] ?>)</span></td>
                                    <td><?= $r['batch_name'] ?></td>
                                    <td>
                                        <span class="badge <?= $r['status'] === 'Present' ? 'success' : ($r['status'] === 'Absent' ? 'danger' : 'warning') ?>"><?= $r['status'] ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($type === 'materials'): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Material Issue Report</h3>
            <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Book/Material</th>
                            <th>Issue Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($results) === 0): ?>
                            <tr><td colspan="5"><div class="empty-state"><p>No materials issued during this period.</p></div></td></tr>
                        <?php else: ?>
                            <?php while ($r = mysqli_fetch_assoc($results)): ?>
                                <tr>
                                    <td><strong><?= $r['name'] ?></strong> <span class="text-muted">(<?= $r['roll_number'] ?>)</span></td>
                                    <td><?= $r['book_title'] ?></td>
                                    <td><?= date('d M Y', strtotime($r['issue_date'])) ?></td>
                                    <td><?= $r['return_date'] ? date('d M Y', strtotime($r['return_date'])) : '-' ?></td>
                                    <td><span class="badge <?= $r['status'] === 'Returned' ? 'success' : 'warning' ?>"><?= $r['status'] ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($type === 'fees'): ?>
    <div class="grid-2">
        <!-- Collection Report -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Fee Collection</h3>
                <div class="action-btns">
                    <button class="btn btn-secondary btn-sm" onclick="exportTableToCSV('feeCollectionTable', 'fee_collections.csv')"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</button>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table" id="feeCollectionTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Receipt</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total = 0; if (mysqli_num_rows($results) === 0): ?>
                                <tr><td colspan="5"><div class="empty-state"><p>No fees collected during this period.</p></div></td></tr>
                            <?php else: ?>
                                <?php while ($r = mysqli_fetch_assoc($results)): $total += $r['amount_paid']; ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($r['payment_date'])) ?></td>
                                        <td><strong><?= $r['receipt_number'] ?></strong></td>
                                        <td><strong><?= $r['name'] ?></strong> <div class="text-muted"><?= $r['course_name'] ?></div></td>
                                        <td style="color:var(--success);font-weight:700;">₹<?= number_format($r['amount_paid'],2) ?></td>
                                        <td><span class="badge primary"><?= $r['payment_mode'] ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr>
                                    <td colspan="3" style="text-align:right;"><strong>Total Collection:</strong></td>
                                    <td colspan="2"><strong style="font-size:1.1rem;color:var(--success);">₹<?= number_format($total,2) ?></strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Outstanding Dues Report -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title text-danger">Pending Dues</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course Fee</th>
                                <th>Due Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $tot_due = 0; if (mysqli_num_rows($dues_res) === 0): ?>
                                <tr><td colspan="3"><div class="empty-state"><p>No outstanding dues!</p></div></td></tr>
                            <?php else: ?>
                                <?php while ($r = mysqli_fetch_assoc($dues_res)): 
                                    $due = $r['total_fee'] - $r['paid_fee'];
                                    $tot_due += $due;
                                ?>
                                    <tr>
                                        <td><strong><?= $r['name'] ?></strong> <div class="text-muted"><?= $r['course_name'] ?></div></td>
                                        <td>₹<?= number_format($r['total_fee'],2) ?></td>
                                        <td style="color:var(--danger);font-weight:700;">₹<?= number_format($due,2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr>
                                    <td colspan="2" style="text-align:right;"><strong>Total Outstanding:</strong></td>
                                    <td><strong style="font-size:1.1rem;color:var(--danger);">₹<?= number_format($tot_due,2) ?></strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($type === 'followups'): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Follow-ups Report</h3>
            <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Lead Name</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($results) === 0): ?>
                            <tr><td colspan="5"><div class="empty-state"><p>No follow-ups found for this criteria.</p></div></td></tr>
                        <?php else: ?>
                            <?php while ($r = mysqli_fetch_assoc($results)): ?>
                                <tr>
                                    <td><?= date('d M Y h:i A', strtotime($r['followup_date'])) ?></td>
                                    <td><strong><?= $r['student_name'] ?></strong> <div class="text-muted"><?= $r['course_name'] ?></div></td>
                                    <td><?= $r['mobile'] ?></td>
                                    <td><span class="badge <?= $r['status'] === 'Completed' ? 'success' : 'warning' ?>"><?= $r['status'] ?></span></td>
                                    <td><div style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($r['call_notes']) ?>"><?= $r['call_notes'] ?></div></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function downloadCSV(csv, filename) {
    let csvFile = new Blob([csv], {type: "text/csv"});
    let downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

function exportTableToCSV(tableId, filename) {
    let csv = [];
    let rows = document.querySelectorAll("#" + tableId + " tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (let j = 0; j < cols.length; j++) {
            // Get text content and strip extra whitespace/newlines
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
            // Escape double quotes
            data = data.replace(/"/g, '""');
            // Enclose in quotes
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(","));
    }
    
    downloadCSV(csv.join("\n"), filename);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
