<?php
// followups.php
require_once __DIR__ . '/includes/header.php';
$tenant_id = get_tenant_id();

$enquiry_id = isset($_GET['enquiry_id']) ? intval($_GET['enquiry_id']) : 0;
$enquiry = null;

if ($enquiry_id > 0) {
    $enq_res = db_query($conn, "SELECT e.*, c.name as course_name FROM enquiries e JOIN courses c ON e.course_id = c.id WHERE e.id = ? AND e.tenant_id = ?", [$enquiry_id, $tenant_id]);
    $enquiry = mysqli_fetch_assoc($enq_res);
}

// Handle Add Follow-up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_followup') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $enq_id = intval($_POST['enquiry_id']);
        $followup_date = sanitize($_POST['followup_date']);
        $call_notes = sanitize($_POST['call_notes']);
        $next_date = !empty($_POST['next_followup_date']) ? sanitize($_POST['next_followup_date']) : null;
        $status = sanitize($_POST['status']); // Completed, Pending
        
        // Insert followup record
        db_insert($conn, "INSERT INTO followups (tenant_id, enquiry_id, followup_date, call_notes, next_followup_date, status) VALUES (?, ?, ?, ?, ?, ?)", [
            $tenant_id, $enq_id, $followup_date, $call_notes, $next_date, $status
        ]);
        
        // Update enquiry status to Follow-up if it was New
        $enq_status = ($status == 'Completed' && !empty($next_date)) ? 'Follow-up' : 'Follow-up';
        db_query($conn, "UPDATE enquiries SET status = ? WHERE id = ? AND tenant_id = ?", [$enq_status, $enq_id, $tenant_id]);
        
        set_flash_message('success', 'Follow-up logged successfully!');
        redirect('/followups.php?enquiry_id=' . $enq_id);
    }
}

// Mark pending followup as completed
if (isset($_GET['complete_id'])) {
    $complete_id = intval($_GET['complete_id']);
    db_query($conn, "UPDATE followups SET status = 'Completed' WHERE id = ? AND tenant_id = ?", [$complete_id, $tenant_id]);
    set_flash_message('success', 'Follow-up marked as completed!');
    redirect('/followups.php' . ($enquiry_id > 0 ? "?enquiry_id=$enquiry_id" : ""));
}

// Fetch calendar data (Pending counts per day)
$cal_filter = isset($_GET['cal_date']) ? sanitize($_GET['cal_date']) : '';
$cal_res = db_query($conn, "SELECT DATE(followup_date) as fdate, COUNT(*) as count FROM followups WHERE tenant_id = ? AND status = 'Pending' GROUP BY DATE(followup_date)", [$tenant_id]);
$cal_counts = [];
while ($r = mysqli_fetch_assoc($cal_res)) {
    $cal_counts[$r['fdate']] = $r['count'];
}

// Fetch lists
$pending_sql = "
    SELECT f.*, e.name as student_name, e.mobile as student_mobile, c.name as course_name 
    FROM followups f 
    JOIN enquiries e ON f.enquiry_id = e.id 
    JOIN courses c ON e.course_id = c.id
    WHERE f.tenant_id = ? AND f.status = 'Pending'";
$p_params = [$tenant_id];

if ($cal_filter) {
    $pending_sql .= " AND DATE(f.followup_date) = ?";
    $p_params[] = $cal_filter;
}
$pending_sql .= " ORDER BY f.followup_date ASC";
$pending_followups_list = db_query($conn, $pending_sql, $p_params);

$completed_followups_list = db_query($conn, "
    SELECT f.*, e.name as student_name, e.mobile as student_mobile, c.name as course_name 
    FROM followups f 
    JOIN enquiries e ON f.enquiry_id = e.id 
    JOIN courses c ON e.course_id = c.id
    WHERE f.tenant_id = ? AND f.status = 'Completed' 
    ORDER BY f.followup_date DESC LIMIT 15", [$tenant_id]);
?>

<div class="page-header">
    <h2><i class="bi bi-arrow-repeat" style="color:var(--primary);margin-right:8px;"></i>Follow-up Management</h2>
</div>

<?php if ($enquiry): ?>
    <!-- Inquiry Specific Follow-ups & Notes timeline -->
    <div style="margin-bottom: 16px;">
        <a href="enquiries.php" class="btn btn-secondary" style="padding: 8px 16px;"><i class="bi bi-arrow-left"></i> Back to Enquiries</a>
    </div>

    <div class="grid-2">
        <!-- Enquiry Details & Quick Log -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Enquiry Details</h3>
                    <span class="badge primary"><?= $enquiry['status'] ?></span>
                </div>
                <div class="card-body">
                    <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 12px;"><?= $enquiry['name'] ?></h2>
                    <p style="margin-bottom: 8px;"><i class="bi bi-telephone-fill text-primary"></i> <strong>Mobile:</strong> <?= $enquiry['mobile'] ?></p>
                    <p style="margin-bottom: 8px;"><i class="bi bi-envelope-fill text-primary"></i> <strong>Email:</strong> <?= $enquiry['email'] ?: 'N/A' ?></p>
                    <p style="margin-bottom: 8px;"><i class="bi bi-book-fill text-primary"></i> <strong>Course:</strong> <?= $enquiry['course_name'] ?></p>
                    <p style="margin-bottom: 16px;"><i class="bi bi-journal-text text-primary"></i> <strong>Original Notes:</strong> <?= $enquiry['notes'] ?: 'None' ?></p>
                    
                    <?php
                    // Pre-filled WhatsApp message
                    $wa_message = urlencode("Hello " . $enquiry['name'] . ", this is " . $_SESSION['tenant_name'] . ". We wanted to follow up with you regarding your interest in the " . $enquiry['course_name'] . " course. Let us know if you have any questions!");
                    ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $enquiry['mobile']) ?>?text=<?= $wa_message ?>" target="_blank" class="btn btn-success" style="width: 100%;"><i class="bi bi-whatsapp"></i> WhatsApp Follow-up</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Log New Follow-up</h3>
                </div>
                <div class="card-body">
                    <form action="followups.php?enquiry_id=<?= $enquiry['id'] ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="add_followup">
                        <input type="hidden" name="enquiry_id" value="<?= $enquiry['id'] ?>">

                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Follow-up Date</label>
                                <input type="datetime-local" name="followup_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="Completed">Completed</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Call / Conversation Notes</label>
                            <textarea name="call_notes" class="form-control" rows="3" placeholder="Enter details of conversation..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Next Follow-up Date (Optional)</label>
                            <input type="datetime-local" name="next_followup_date" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Save Follow-up</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History Timeline -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Follow-up History</h3>
            </div>
            <div class="card-body">
                <?php
                $history_res = db_query($conn, "SELECT * FROM followups WHERE enquiry_id = ? AND tenant_id = ? ORDER BY followup_date DESC", [$enquiry_id, $tenant_id]);
                if (mysqli_num_rows($history_res) === 0):
                ?>
                    <div class="empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <p>No follow-up logged yet.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php while ($h = mysqli_fetch_assoc($history_res)): ?>
                            <div class="timeline-item">
                                <div class="timeline-time">
                                    <?= date('d M Y - h:i A', strtotime($h['followup_date'])) ?>
                                    <span class="badge <?= $h['status'] == 'Completed' ? 'success' : 'warning' ?>"><?= $h['status'] ?></span>
                                </div>
                                <div class="timeline-content">
                                    <?= nl2br($h['call_notes']) ?>
                                    <?php if (!empty($h['next_followup_date'])): ?>
                                        <div style="margin-top:6px;font-size:0.8rem;font-weight:600;color:var(--primary);">
                                            Next follow-up: <?= date('d M Y - h:i A', strtotime($h['next_followup_date'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Global Follow-up Lists -->
    
    <!-- Follow-up Calendar -->
    <div class="calendar-wrapper">
        <div class="calendar-header">
            <h3 class="calendar-title"><i class="bi bi-calendar-week" style="color:var(--primary);margin-right:8px;"></i>Follow-up Schedule</h3>
            <?php if ($cal_filter): ?>
                <a href="followups.php" class="btn btn-secondary btn-sm">Clear Filter</a>
            <?php endif; ?>
        </div>
        <div class="calendar-grid">
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>
            <?php
            $first_day = date('Y-m-01');
            $start_dow = date('w', strtotime($first_day));
            $days_in_month = date('t');
            $today_str = date('Y-m-d');
            
            for ($i = 0; $i < $start_dow; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }
            
            for ($d = 1; $d <= $days_in_month; $d++) {
                $date_str = date('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT);
                $is_today = ($date_str === $today_str);
                $is_active = ($date_str === $cal_filter);
                $count = $cal_counts[$date_str] ?? 0;
                
                $classes = ['calendar-day'];
                if ($is_today) $classes[] = 'today';
                if ($is_active) $classes[] = 'active-filter';
                
                $badge_html = $count > 0 ? "<div class='calendar-badge'>$count</div>" : "";
                
                echo "<a href='followups.php?cal_date=$date_str' class='".implode(' ', $classes)."'>";
                echo "<span>$d</span>";
                echo $badge_html;
                echo "</a>";
            }
            ?>
        </div>
    </div>
    
    <div class="grid-2">
        <!-- Pending Follow-ups -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-clock-history" style="color:var(--warning);"></i> Active & Pending Follow-ups</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Scheduled</th>
                                <th style="width:130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($pending_followups_list) === 0): ?>
                                <tr><td colspan="4">
                                    <div class="empty-state">
                                        <i class="bi bi-check2-all"></i>
                                        <p>No pending follow-ups. Great job!</p>
                                    </div>
                                </td></tr>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($pending_followups_list)): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $row['student_name'] ?></strong>
                                            <div class="text-muted"><?= $row['student_mobile'] ?></div>
                                        </td>
                                        <td><?= $row['course_name'] ?></td>
                                        <td><?= date('d M Y · h:i A', strtotime($row['followup_date'])) ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="<?= BASE_URL ?>/followups.php?enquiry_id=<?= $row['enquiry_id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-chat-left-text"></i> Log</a>
                                                <a href="<?= BASE_URL ?>/followups.php?complete_id=<?= $row['id'] ?>" class="btn btn-success btn-sm btn-icon" title="Mark Completed"><i class="bi bi-check-lg"></i></a>
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

        <!-- Completed Follow-ups History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-check2-circle" style="color:var(--success);"></i> Recently Completed</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Notes Summary</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($completed_followups_list) === 0): ?>
                                <tr><td colspan="3">
                                    <div class="empty-state">
                                        <i class="bi bi-chat-square-dots"></i>
                                        <p>No completed follow-ups yet.</p>
                                    </div>
                                </td></tr>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($completed_followups_list)): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $row['student_name'] ?></strong>
                                            <div class="text-muted"><?= $row['course_name'] ?></div>
                                        </td>
                                        <td>
                                            <div style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $row['call_notes'] ?></div>
                                        </td>
                                        <td><?= date('d M Y', strtotime($row['followup_date'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
