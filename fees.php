<?php
// fees.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

// Handle New Fee Payment Collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_fee') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $student_id = intval($_POST['student_id']);
        $amount = floatval($_POST['amount']);
        $pay_date = sanitize($_POST['payment_date']);
        $mode = sanitize($_POST['payment_mode']);
        $notes = sanitize($_POST['notes']);
        
        $receipt = $tenant_prefix . "-REC-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        db_insert($conn, "INSERT INTO fee_payments (tenant_id, student_id, amount_paid, payment_date, payment_mode, receipt_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $tenant_id, $student_id, $amount, $pay_date, $mode, $receipt, $notes
        ]);
        
        set_flash_message('success', 'Fee payment recorded successfully! Receipt ' . $receipt . ' generated.');
        redirect('/fees.php');
    }
}

// Handle Receipt Cancellation (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_receipt') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($_SESSION['user_role'] === 'Admin') {
            $payment_id = intval($_POST['payment_id']);
            db_query($conn, "UPDATE fee_payments SET status = 'Cancelled' WHERE id = ? AND tenant_id = ?", [$payment_id, $tenant_id]);
            set_flash_message('success', 'Receipt has been cancelled successfully.');
        } else {
            set_flash_message('danger', 'Unauthorized. Only admins can cancel receipts.');
        }
        redirect('/fees.php');
    }
}

// Fetch stats
$total_received = mysqli_fetch_assoc(db_query($conn, "SELECT SUM(amount_paid) as total FROM fee_payments WHERE tenant_id = ? AND status = 'Active'", [$tenant_id]))['total'] ?? 0;

// Calculate outstanding dues (Courses total fee - payments total)
$total_fees_due_res = mysqli_fetch_assoc(db_query($conn, "
    SELECT 
        (SELECT SUM(c.total_fee) FROM students s JOIN courses c ON s.course_id = c.id WHERE s.tenant_id = ?) as total_expected,
        (SELECT SUM(amount_paid) FROM fee_payments WHERE tenant_id = ? AND status = 'Active') as total_paid", [$tenant_id, $tenant_id]));
$total_expected = $total_fees_due_res['total_expected'] ?? 0;
$total_paid = $total_fees_due_res['total_paid'] ?? 0;
$total_due = max(0, $total_expected - $total_paid);

// Fetch students for dropdown (Active)
$students_res = db_query($conn, "SELECT id, name, roll_number FROM students WHERE tenant_id = ? AND status = 'Active'", [$tenant_id]);
$students = [];
while ($row = mysqli_fetch_assoc($students_res)) { $students[] = $row; }

// Fetch payment transactions
$payments_res = db_query($conn, "
    SELECT p.*, s.name as student_name, s.roll_number as student_roll, c.name as course_name 
    FROM fee_payments p 
    JOIN students s ON p.student_id = s.id 
    JOIN courses c ON s.course_id = c.id 
    WHERE p.tenant_id = ? 
    ORDER BY p.id DESC LIMIT 30", [$tenant_id]);

// Defaulters List: Students who still owe balance
$defaulters_res = db_query($conn, "
    SELECT s.id, s.name, s.roll_number, s.mobile, c.name as course_name, c.total_fee,
           COALESCE((SELECT SUM(amount_paid) FROM fee_payments WHERE student_id = s.id), 0) as paid_fee
    FROM students s
    JOIN courses c ON s.course_id = c.id
    WHERE s.tenant_id = ? AND s.status = 'Active'
    HAVING paid_fee < total_fee", [$tenant_id]);
?>

<div class="page-header">
    <h2><i class="bi bi-credit-card-fill" style="color:var(--primary);margin-right:8px;"></i>Fee & Receipt Management</h2>
    <button class="btn btn-primary" onclick="openModal('collectFeeModal')"><i class="bi bi-wallet2"></i> Collect Fee Payment</button>
</div>

<!-- Metrics summary -->
<div class="metrics-grid">
    <div class="metric-card success">
        <div class="metric-top">
            <div>
                <div class="metric-label">Total Revenue Collected</div>
                <div class="metric-value" style="font-size:1.4rem;">₹<?= number_format($total_received, 0) ?></div>
                <div class="metric-label" style="margin-top:4px;">All payments captured</div>
            </div>
            <div class="metric-icon success"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
    <div class="metric-card danger">
        <div class="metric-top">
            <div>
                <div class="metric-label">Outstanding Dues</div>
                <div class="metric-value" style="font-size:1.4rem;">₹<?= number_format($total_due, 0) ?></div>
                <div class="metric-label" style="margin-top:4px;">Receivables from students</div>
            </div>
            <div class="metric-icon danger"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Transactions List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-receipt"></i> Recent Fee Receipts</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($payments_res) === 0): ?>
                            <tr><td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-receipt"></i>
                                    <p>No payment records found yet.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($payments_res)): ?>
                                <tr>
                                    <td>
                                        <strong><?= $row['receipt_number'] ?></strong>
                                        <?php if (($row['status'] ?? 'Active') === 'Cancelled'): ?>
                                            <span class="badge danger" style="margin-left:4px; font-size:0.65rem;">Cancelled</span>
                                        <?php endif; ?>
                                        <div class="text-muted"><?= date('d M Y', strtotime($row['payment_date'])) ?></div>
                                    </td>
                                    <td>
                                        <strong><?= $row['student_name'] ?></strong>
                                        <div class="text-muted">Roll: <?= $row['student_roll'] ?></div>
                                    </td>
                                    <td class="fw-700 <?= ($row['status'] ?? 'Active') === 'Cancelled' ? 'text-muted' : '' ?>" <?= ($row['status'] ?? 'Active') === 'Cancelled' ? 'style="text-decoration:line-through;"' : '' ?>>₹<?= number_format($row['amount_paid'], 2) ?></td>
                                    <td><span class="badge primary"><?= $row['payment_mode'] ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-secondary btn-sm btn-icon" onclick='viewReceipt(<?= htmlspecialchars(json_encode($row)) ?>)' title="View & Print"><i class="bi bi-printer"></i></button>
                                            
                                            <?php if ($_SESSION['user_role'] === 'Admin' && ($row['status'] ?? 'Active') === 'Active'): ?>
                                            <form method="POST" action="fees.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this receipt? This will remove the payment from total collections.');">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="cancel_receipt">
                                                <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-danger-soft btn-sm btn-icon" title="Cancel Receipt"><i class="bi bi-x-circle"></i></button>
                                            </form>
                                            <?php endif; ?>
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

    <!-- Defaulters List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-exclamation-circle"></i> Fee Defaulters</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Total Fee</th>
                            <th>Balance Due</th>
                            <th>Reminder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($defaulters_res) === 0): ?>
                            <tr><td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-check2-circle"></i>
                                    <p>All student dues are clear! 🎉</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($defaulters_res)): ?>
                                <?php $due_bal = $row['total_fee'] - $row['paid_fee']; ?>
                                <tr>
                                    <td>
                                        <strong><?= $row['name'] ?></strong>
                                        <div class="text-muted">Roll: <?= $row['roll_number'] ?></div>
                                    </td>
                                    <td>₹<?= number_format($row['total_fee'], 2) ?></td>
                                    <td><strong style="color:var(--danger);">₹<?= number_format($due_bal, 2) ?></strong></td>
                                    <td>
                                        <?php
                                        $remind_msg = urlencode("Dear " . $row['name'] . ", this is a friendly reminder that an outstanding balance of ₹" . $due_bal . " is pending for your " . $row['course_name'] . " course fee. Please clear it at the earliest. Thank you!");
                                        ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['mobile']) ?>?text=<?= $remind_msg ?>" target="_blank" class="btn btn-danger btn-sm"><i class="bi bi-bell"></i> Send Alert</a>
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

<!-- Modal: Collect Fee -->
<div id="collectFeeModal" class="modal-backdrop">
    <div class="modal">
        <form action="fees.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="collect_fee">
            <div class="modal-header">
                <h3><i class="bi bi-wallet2" style="color:var(--primary);margin-right:6px;"></i>Collect Course Fee</h3>
                <button type="button" class="modal-close" onclick="closeModal('collectFeeModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['name'] ?> (Roll: <?= $s['roll_number'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Amount Received (INR)</label>
                        <input type="number" name="amount" class="form-control" value="1000" min="1" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Installment details, bank transaction ID, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('collectFeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View/Print Receipt -->
<div id="receiptModal" class="modal-backdrop">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="bi bi-receipt" style="color:var(--primary);margin-right:6px;"></i>Fee Receipt Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('receiptModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div id="receiptContainer" style="padding:24px;border:1px solid var(--ink-200);border-radius:var(--r-md);background:#fff;font-family:var(--font-ui);">
                <!-- HEADER (Logo Left, Details Center, QR Right) -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--primary); padding-bottom: 12px; margin-bottom: 16px;">
                    <!-- Left: Logo -->
                    <div style="width: 25%; text-align: left;">
                        <?php if ($show_logo_receipt && $tenant_logo && file_exists(dirname(__DIR__) . '/' . $tenant_logo)): ?>
                            <img src="<?= BASE_URL ?>/<?= $tenant_logo ?>" alt="Logo" style="max-width: 100%; max-height: 50px;">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Center: Institute Details -->
                    <div style="width: 50%; text-align: center;">
                        <h2 style="font-family: var(--font-heading); margin: 0; color: var(--primary); text-transform: uppercase; font-size: 1.1rem; line-height: 1.2;"><?= htmlspecialchars($_SESSION['tenant_name'] ?? '') ?></h2>
                        <div style="font-size: 0.7rem; color: var(--slate-500); margin-top: 4px; line-height: 1.4;">
                            <?php if (!empty($tenant_address)) echo htmlspecialchars($tenant_address) . '<br>'; ?>
                            <?php if (!empty($tenant_phone)) echo 'Ph: ' . htmlspecialchars($tenant_phone) . ' '; ?>
                            <?php if (!empty($tenant_email)) echo '| ' . htmlspecialchars($tenant_email); ?>
                        </div>
                        <div style="font-size: 0.8rem; font-weight: bold; margin-top: 6px; color: var(--slate-700); letter-spacing: 1px;">FEE RECEIPT</div>
                    </div>
                    
                    <!-- Right: QR Code -->
                    <div style="width: 25%; text-align: right;">
                        <?php if ($show_qr_receipt): ?>
                            <img id="recQR" src="" alt="QR Code" style="width: 60px; height: 60px; border: 1px solid var(--ink-200); padding: 2px; border-radius: 4px; display: inline-block;">
                        <?php endif; ?>
                    </div>
                </div>
                
                <table style="width: 100%; font-size: 0.85rem; line-height: 2;">
                    <tr>
                        <td style="color: var(--slate-600);">Receipt No:</td>
                        <td style="text-align: right;"><strong id="recNo">REC-0000</strong></td>
                    </tr>
                    <tr>
                        <td style="color: var(--slate-600);">Payment Date:</td>
                        <td style="text-align: right;" id="recDate">01 Jan 2026</td>
                    </tr>
                    <tr>
                        <td style="color: var(--slate-600);">Roll Number:</td>
                        <td style="text-align: right;" id="recRoll">INST-101</td>
                    </tr>
                    <tr>
                        <td style="color: var(--slate-600);">Student Name:</td>
                        <td style="text-align: right;"><strong id="recName">John Doe</strong></td>
                    </tr>
                    <tr>
                        <td style="color: var(--slate-600);">Allocated Course:</td>
                        <td style="text-align: right;" id="recCourse">N/A</td>
                    </tr>
                    <tr style="border-top: 1px dashed var(--slate-300); border-bottom: 1px dashed var(--slate-300); font-size: 1.05rem;">
                        <td><strong>Amount Paid:</strong></td>
                        <td style="text-align: right; color: var(--success);"><strong>₹<span id="recAmount">0.00</span></strong></td>
                    </tr>
                    <tr>
                        <td style="color: var(--slate-600);">Payment Mode:</td>
                        <td style="text-align: right;" id="recMode">Cash</td>
                    </tr>
                    <tr>
                        <td style="color: var(--slate-600);">Notes:</td>
                        <td style="text-align: right; font-size: 0.75rem;" id="recNotes">N/A</td>
                    </tr>
                </table>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 24px; padding-top: 12px; border-top: 1px solid var(--slate-200);">
                    <div style="width: 60%; font-size: 0.7rem; color: var(--slate-500); text-align: left;">
                        <?php if (!empty($receipt_notes)): ?>
                            <strong>Terms & Notes:</strong><br>
                            <?= nl2br(htmlspecialchars($receipt_notes)) ?>
                        <?php endif; ?>
                    </div>
                    <div style="width: 40%; text-align: right;">
                        <?php if (!empty($signature_path) && file_exists(dirname(__DIR__) . '/' . $signature_path)): ?>
                            <img src="<?= BASE_URL ?>/<?= $signature_path ?>" alt="Signature" style="max-height: 35px; margin-bottom: 4px;">
                        <?php else: ?>
                            <div style="height: 35px;"></div>
                        <?php endif; ?>
                        <div style="font-size: 0.7rem; color: var(--slate-600); border-top: 1px dashed var(--ink-200); display: inline-block; padding-top: 4px; min-width: 120px; text-align: center;">Authorized Signatory</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('receiptModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="printReceipt('receiptContainer')"><i class="bi bi-printer"></i> Print</button>
            <button type="button" class="btn btn-success" onclick="downloadPDF('receiptContainer', 'Fee_Receipt')"><i class="bi bi-file-pdf"></i> Download PDF</button>
        </div>
    </div>
</div>

<script>
function viewReceipt(data) {
    document.getElementById('recNo').textContent = data.receipt_number;
    document.getElementById('recDate').textContent = data.payment_date;
    document.getElementById('recRoll').textContent = data.student_roll;
    document.getElementById('recName').textContent = data.student_name;
    document.getElementById('recCourse').textContent = data.course_name;
    document.getElementById('recAmount').textContent = Number(data.amount_paid).toFixed(2);
    document.getElementById('recMode').textContent = data.payment_mode;
    document.getElementById('recNotes').textContent = data.notes || 'N/A';
    
    const qrEl = document.getElementById('recQR');
    if (qrEl) {
        qrEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent('Receipt: ' + data.receipt_number);
    }
    
    openModal('receiptModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
