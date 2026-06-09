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

// Course Popularity
$cp_query = "SELECT c.name, COUNT(s.id) as student_count 
             FROM courses c 
             LEFT JOIN students s ON c.id = s.course_id AND s.status = 'Active' 
             WHERE c.tenant_id = ? 
             GROUP BY c.id ORDER BY student_count DESC LIMIT 5";
$cp_res = db_query($conn, $cp_query, [$tenant_id]);
$course_popularity = [];
$max_students = 0;
while($row = mysqli_fetch_assoc($cp_res)) {
    $course_popularity[] = $row;
    if ($row['student_count'] > $max_students) $max_students = $row['student_count'];
}

// Recent Enquiries
$re_res = db_query($conn, "SELECT name, mobile, created_at FROM enquiries WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 5", [$tenant_id]);
$recent_enquiries = [];
while($row = mysqli_fetch_assoc($re_res)) $recent_enquiries[] = $row;

// Recent Admissions
$ra_res = db_query($conn, "SELECT s.name, c.name as course_name, s.admission_date 
                            FROM students s 
                            JOIN courses c ON s.course_id = c.id 
                            WHERE s.tenant_id = ? 
                            ORDER BY s.id DESC LIMIT 5", [$tenant_id]);
$recent_admissions = [];
while($row = mysqli_fetch_assoc($ra_res)) $recent_admissions[] = $row;
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
    <?php if ($_SESSION['user_role'] === 'Admin'): ?>
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
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 24px; background: linear-gradient(135deg, var(--brand-600), var(--brand-800)); color: white; border: none; box-shadow: var(--shadow-lg);">
    <div class="card-body" style="padding: 40px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div>
            <h2 style="font-family: var(--font-display); font-size: 2rem; margin-bottom: 8px;">Welcome back, <?= htmlspecialchars($user_name) ?>!</h2>
            <p style="font-size: 1.1rem; opacity: 0.9; max-width: 500px;">Here is what's happening at <?= htmlspecialchars($tenant_name) ?> today. Manage your admissions, follow-ups, and student records effortlessly.</p>
            <div style="margin-top: 20px; display: flex; gap: 12px;">
                <a href="enquiries.php" class="btn" style="background: white; color: var(--brand-700); font-weight: 600;">View Enquiries</a>
                <a href="admissions.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4);">Recent Admissions</a>
            </div>
        </div>
        <div style="font-size: 8rem; opacity: 0.2;">
            <i class="bi bi-building"></i>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top: 24px; gap: 24px;">
    <!-- Course Popularity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-bar-chart-fill" style="color:var(--primary);margin-right:8px;"></i>Course Popularity</h3>
        </div>
        <div class="card-body">
            <?php if(empty($course_popularity)): ?>
                <div class="empty-state"><p>No data available.</p></div>
            <?php else: foreach($course_popularity as $cp): 
                $percent = $max_students > 0 ? ($cp['student_count'] / $max_students) * 100 : 0;
            ?>
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem;">
                        <strong><?= htmlspecialchars($cp['name']) ?></strong>
                        <span class="text-muted"><?= $cp['student_count'] ?> students</span>
                    </div>
                    <div style="background: var(--ink-100); height: 8px; border-radius: 4px; overflow: hidden;">
                        <div style="background: var(--primary); width: <?= $percent ?>%; height: 100%; border-radius: 4px; transition: width 1s ease-out;"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Conversion Rate -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-pie-chart-fill" style="color:var(--success);margin-right:8px;"></i>Conversion Rate</h3>
        </div>
        <div class="card-body" style="display:flex; align-items:center; justify-content:center; flex-direction:column; padding: 40px 20px;">
            <?php $conv_rate = $total_enquiries > 0 ? round(($total_students / $total_enquiries) * 100, 1) : 0; ?>
            <div style="position:relative; width: 160px; height: 160px; border-radius: 50%; background: conic-gradient(var(--success) <?= $conv_rate ?>%, var(--ink-100) 0); display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-sm);">
                <div style="width: 130px; height: 130px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-direction: column; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                    <span style="font-size: 2.2rem; font-weight: 800; color: var(--ink-900); font-family: var(--font-display);"><?= $conv_rate ?>%</span>
                </div>
            </div>
            <p class="text-muted" style="margin-top: 24px; text-align: center; max-width: 250px;">Percentage of leads that successfully enrolled as active students.</p>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top: 24px; gap: 24px;">
    <!-- Recent Enquiries -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-person-plus-fill" style="color:var(--secondary);margin-right:8px;"></i>Recent Leads</h3>
            <a href="enquiries.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <tbody>
                    <?php if(empty($recent_enquiries)): ?>
                        <tr><td><div class="text-muted" style="padding:16px;">No recent leads.</div></td></tr>
                    <?php else: foreach($recent_enquiries as $re): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($re['name']) ?></strong><br>
                                <span style="font-size: 0.8rem; color: var(--ink-500);"><i class="bi bi-telephone"></i> <?= htmlspecialchars($re['mobile']) ?></span>
                            </td>
                            <td style="text-align: right; font-size: 0.8rem; color: var(--ink-500);">
                                <?= date('d M', strtotime($re['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Admissions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-mortarboard-fill" style="color:var(--primary);margin-right:8px;"></i>Recent Admissions</h3>
            <a href="admissions.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <tbody>
                    <?php if(empty($recent_admissions)): ?>
                        <tr><td><div class="text-muted" style="padding:16px;">No recent admissions.</div></td></tr>
                    <?php else: foreach($recent_admissions as $ra): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ra['name']) ?></strong><br>
                                <span style="font-size: 0.8rem; color: var(--primary);"><i class="bi bi-book"></i> <?= htmlspecialchars($ra['course_name']) ?></span>
                            </td>
                            <td style="text-align: right; font-size: 0.8rem; color: var(--ink-500);">
                                <?= date('d M Y', strtotime($ra['admission_date'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
