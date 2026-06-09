<?php
// dashboard.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

/* ══════════════════════════════════════════════════
   FETCH DATA
══════════════════════════════════════════════════ */
$total_enquiries       = mysqli_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM enquiries WHERE tenant_id = ?", [$tenant_id]))['count'];
$total_students        = mysqli_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM students WHERE tenant_id = ? AND status = 'Active'", [$tenant_id]))['count'];
$pending_followups     = mysqli_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM followups WHERE tenant_id = ? AND status = 'Pending' AND DATE(followup_date) <= CURDATE()", [$tenant_id]))['count'];
$total_fees_collected  = mysqli_fetch_assoc(db_query($conn, "SELECT SUM(amount_paid) as total FROM fee_payments WHERE tenant_id = ?", [$tenant_id]))['total'] ?? 0;
?>

<!-- ── Metric Cards ──────────────────────────────────── -->
<div class="metrics-grid">
    <div class="metric-card primary">
        <div class="metric-top">
            <div>
                <div class="metric-label">Total Enquiries</div>
                <div class="metric-value"><?= $total_enquiries ?></div>
                <div class="metric-label" style="margin-top:4px;">Leads received</div>
            </div>
            <div class="metric-icon primary"><i class="bi bi-telephone-inbound"></i></div>
        </div>
    </div>
    <div class="metric-card success">
        <div class="metric-top">
            <div>
                <div class="metric-label">Active Students</div>
                <div class="metric-value"><?= $total_students ?></div>
                <div class="metric-label" style="margin-top:4px;">Currently enrolled</div>
            </div>
            <div class="metric-icon success"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>
    <div class="metric-card warning">
        <div class="metric-top">
            <div>
                <div class="metric-label">Pending Follow-ups</div>
                <div class="metric-value"><?= $pending_followups ?></div>
                <div class="metric-label" style="margin-top:4px;">Due today or overdue</div>
            </div>
            <div class="metric-icon warning"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="metric-card danger">
        <div class="metric-top">
            <div>
                <div class="metric-label">Fees Collected</div>
                <div class="metric-value" style="font-size:1.4rem;">₹<?= number_format($total_fees_collected, 0) ?></div>
                <div class="metric-label" style="margin-top:4px;">Total revenue</div>
            </div>
            <div class="metric-icon danger"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
</div>

<div class="empty-state" style="margin-top: 40px; background: transparent; border: 1px dashed var(--ink-200);">
    <i class="bi bi-speedometer2" style="font-size: 3rem; color: var(--ink-300);"></i>
    <p style="color: var(--ink-500); max-width: 400px; margin: 16px auto 0;">Welcome to your Dashboard. Navigate using the sidebar to manage enquiries, follow-ups, and admissions. Course and Batch management have been moved to the <a href="settings.php">Settings</a> hub.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
