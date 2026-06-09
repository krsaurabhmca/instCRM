<?php
// billing.php
require_once __DIR__ . '/includes/header.php';
require_admin();

$tenant_id = get_tenant_id();

// Handle upgrade actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Invalid request.');
    } else {
        $action = $_POST['action'];
        
        if ($action === 'upgrade_monthly') {
            db_query($conn, "UPDATE tenants SET subscription_plan = 'Monthly', subscription_status = 'Active', subscription_ends_at = DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id = ?", [$tenant_id]);
            set_flash_message('success', 'Successfully upgraded to the Monthly plan!');
        } elseif ($action === 'upgrade_yearly') {
            db_query($conn, "UPDATE tenants SET subscription_plan = 'Yearly', subscription_status = 'Active', subscription_ends_at = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ?", [$tenant_id]);
            set_flash_message('success', 'Successfully upgraded to the Yearly plan!');
        }
        redirect('/billing.php');
    }
}

// Fetch current tenant subscription details
$tenant = mysqli_fetch_assoc(db_query($conn, "SELECT subscription_plan, subscription_status, trial_ends_at, subscription_ends_at FROM tenants WHERE id = ?", [$tenant_id]));

$plan = $tenant['subscription_plan'];
$status = $tenant['subscription_status'];
$trial_ends = $tenant['trial_ends_at'];
$sub_ends = $tenant['subscription_ends_at'];

// Calculate days remaining
$days_remaining = 0;
if ($status === 'Trial' && $trial_ends) {
    $days_remaining = max(0, (strtotime($trial_ends) - time()) / (60 * 60 * 24));
} elseif ($status === 'Active' && $sub_ends) {
    $days_remaining = max(0, (strtotime($sub_ends) - time()) / (60 * 60 * 24));
}
$days_remaining = floor($days_remaining);

?>

<div class="card mb-4" style="background: linear-gradient(135deg, var(--brand-600), var(--brand-800)); color: white; border: none; box-shadow: var(--shadow-lg);">
    <div class="card-body" style="padding: 30px 40px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div>
            <div style="text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem; font-weight: 700; margin-bottom: 8px; opacity: 0.8;">Current Plan</div>
            <h2 style="font-family: var(--font-display); font-size: 2.2rem; margin-bottom: 4px; display: flex; align-items: center; gap: 12px;">
                <?= htmlspecialchars($plan) ?> 
                <?php if ($status === 'Active'): ?>
                    <span class="badge" style="background: var(--success); color: white; font-size: 0.8rem; padding: 6px 12px; border-radius: 20px;">Active</span>
                <?php elseif ($status === 'Trial'): ?>
                    <span class="badge" style="background: #f59e0b; color: white; font-size: 0.8rem; padding: 6px 12px; border-radius: 20px;">Trial Period</span>
                <?php else: ?>
                    <span class="badge" style="background: var(--danger); color: white; font-size: 0.8rem; padding: 6px 12px; border-radius: 20px;">Expired</span>
                <?php endif; ?>
            </h2>
            <?php if ($status !== 'Expired'): ?>
                <p style="font-size: 1.05rem; opacity: 0.9; margin-top: 8px;"><i class="bi bi-clock-history"></i> <?= $days_remaining ?> days remaining</p>
            <?php else: ?>
                <p style="font-size: 1.05rem; color: #fca5a5; margin-top: 8px;"><i class="bi bi-exclamation-triangle-fill"></i> Your access has expired. Please upgrade to continue.</p>
            <?php endif; ?>
        </div>
        <div style="font-size: 6rem; opacity: 0.15;">
            <i class="bi bi-credit-card-fill"></i>
        </div>
    </div>
</div>

<h3 style="margin-bottom: 20px; font-family: var(--font-display);">Available Plans</h3>

<div class="grid-2" style="gap: 24px; align-items: stretch;">
    <!-- Monthly Plan -->
    <div class="card" style="border: <?= $plan === 'Monthly' ? '2px solid var(--primary)' : '1px solid var(--ink-200)' ?>;">
        <div class="card-body" style="padding: 30px; display: flex; flex-direction: column; height: 100%;">
            <?php if ($plan === 'Monthly'): ?>
                <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">CURRENT PLAN</div>
            <?php endif; ?>
            
            <h4 style="font-size: 1.2rem; margin-bottom: 8px;">Monthly</h4>
            <div style="font-size: 2.5rem; font-weight: 800; font-family: var(--font-display); color: var(--ink-900); margin-bottom: 16px;">
                ₹499<span style="font-size: 1rem; color: var(--ink-500); font-weight: 500;">/month</span>
            </div>
            <ul style="list-style: none; padding: 0; margin: 0 0 32px 0; flex-grow: 1;">
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Unlimited Students</li>
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> QR Attendance & Receipts</li>
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Priority Email Support</li>
            </ul>
            
            <form method="POST" style="margin-top: auto;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="upgrade_monthly">
                <button type="submit" class="btn <?= $plan === 'Monthly' ? 'btn-outline' : 'btn-primary' ?>" style="width: 100%;" <?= $plan === 'Monthly' ? 'disabled' : '' ?>>
                    <?= $plan === 'Monthly' ? 'Active' : 'Upgrade to Monthly' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Yearly Plan -->
    <div class="card" style="border: <?= $plan === 'Yearly' ? '2px solid var(--primary)' : '1px solid var(--ink-200)' ?>; position: relative; overflow: hidden;">
        <div class="card-body" style="padding: 30px; display: flex; flex-direction: column; height: 100%;">
            <?php if ($plan === 'Yearly'): ?>
                <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">CURRENT PLAN</div>
            <?php else: ?>
                <div style="position: absolute; top: 16px; right: -32px; background: #10b981; color: white; padding: 4px 40px; transform: rotate(45deg); font-size: 0.75rem; font-weight: 700; box-shadow: var(--shadow-sm);">BEST VALUE</div>
            <?php endif; ?>
            
            <h4 style="font-size: 1.2rem; margin-bottom: 8px;">Yearly <span style="background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; margin-left: 8px;">SAVE ₹989</span></h4>
            <div style="font-size: 2.5rem; font-weight: 800; font-family: var(--font-display); color: var(--ink-900); margin-bottom: 16px;">
                ₹4,999<span style="font-size: 1rem; color: var(--ink-500); font-weight: 500;">/year</span>
            </div>
            <ul style="list-style: none; padding: 0; margin: 0 0 32px 0; flex-grow: 1;">
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Everything in Monthly</li>
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> 2 Months Free</li>
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Dedicated Onboarding Call</li>
                <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--ink-700);"><i class="bi bi-check-circle-fill" style="color: var(--success);"></i> WhatsApp Support</li>
            </ul>
            
            <form method="POST" style="margin-top: auto;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="upgrade_yearly">
                <button type="submit" class="btn <?= $plan === 'Yearly' ? 'btn-outline' : 'btn-primary' ?>" style="width: 100%;" <?= $plan === 'Yearly' ? 'disabled' : '' ?>>
                    <?= $plan === 'Yearly' ? 'Active' : 'Upgrade to Yearly' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
