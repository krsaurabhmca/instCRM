<?php
// includes/header.php
require_once dirname(__DIR__) . '/config/app.php';
require_login();

$current_page = basename($_SERVER['PHP_SELF']);

// Handle Return to Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_to_admin') {
    if (isset($_SESSION['original_user_id'])) {
        $_SESSION['user_id'] = $_SESSION['original_user_id'];
        $_SESSION['user_role'] = $_SESSION['original_user_role'];
        $_SESSION['user_name'] = $_SESSION['original_user_name'];
        unset($_SESSION['original_user_id'], $_SESSION['original_user_role'], $_SESSION['original_user_name']);
        header("Location: " . BASE_URL . "/staff.php");
        exit;
    }
}

$tenant_id    = $_SESSION['tenant_id']   ?? 0;
$tenant_name  = $_SESSION['tenant_name'] ?? 'Institution';
$user_id      = $_SESSION['user_id']     ?? 0;
$user_name    = $_SESSION['user_name']   ?? 'User';
$user_role    = $_SESSION['user_role']   ?? 'Staff';

// Fetch specific tenant settings
$t_res = db_query($conn, "SELECT logo_path, prefix, show_logo_receipt, show_logo_id, show_qr_receipt, show_qr_id, theme_color, address, phone, email, receipt_notes, signature_path, subscription_plan, subscription_status, trial_ends_at, subscription_ends_at FROM tenants WHERE id = ?", [$tenant_id]);
$t_data = mysqli_fetch_assoc($t_res);
$tenant_logo = $t_data['logo_path'] ?? null;
$tenant_prefix = $t_data['prefix'] ?? 'INST';
$show_logo_receipt = $t_data['show_logo_receipt'] ?? 1;
$show_logo_id = $t_data['show_logo_id'] ?? 1;
$show_qr_receipt = $t_data['show_qr_receipt'] ?? 1;
$show_qr_id = $t_data['show_qr_id'] ?? 1;
$theme_color = $t_data['theme_color'] ?? '#4f46e5';
$tenant_address = $t_data['address'] ?? '';
$tenant_phone = $t_data['phone'] ?? '';
$tenant_email = $t_data['email'] ?? '';
$receipt_notes = $t_data['receipt_notes'] ?? '';
$signature_path = $t_data['signature_path'] ?? null;

$sub_plan   = $t_data['subscription_plan'] ?? 'Free';
$sub_status = $t_data['subscription_status'] ?? 'Trial';
$trial_ends = $t_data['trial_ends_at'] ?? null;
$sub_ends   = $t_data['subscription_ends_at'] ?? null;

// Check expiry
if ($sub_status === 'Trial' && $trial_ends && strtotime($trial_ends) < time()) {
    $sub_status = 'Expired';
    db_query($conn, "UPDATE tenants SET subscription_status = 'Expired' WHERE id = ?", [$tenant_id]);
} elseif ($sub_status === 'Active' && $sub_ends && strtotime($sub_ends) < time()) {
    $sub_status = 'Expired';
    db_query($conn, "UPDATE tenants SET subscription_status = 'Expired' WHERE id = ?", [$tenant_id]);
}

if ($sub_status === 'Expired' && !in_array($current_page, ['billing.php', 'logout.php'])) {
    if ($user_role !== 'Admin') {
        die('<!DOCTYPE html><html><head><title>Account Expired</title><style>body{font-family:sans-serif;background:#fef2f2;color:#991b1b;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;}div{max-width:400px;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,0.1);}</style></head><body><div><h2 style="margin-top:0;">Subscription Expired</h2><p>Your institution\'s subscription has expired. Please contact your administrator to renew access.</p></div></body></html>');
    } else {
        redirect('/billing.php');
    }
}

$page_titles = [
    'dashboard.php'  => 'Dashboard',
    'enquiries.php'  => 'Enquiry Management',
    'followups.php'  => 'Follow-up Management',
    'admissions.php' => 'Admissions',
    'materials.php'  => 'Study Materials',
    'attendance.php' => 'Attendance',
    'fees.php'       => 'Fees & Receipts',
    'accounts.php'   => 'Accounts & Expense',
    'reports.php'    => 'Reports & Analytics',
    'staff.php'      => 'Staff Management',
    'settings.php'   => 'Institute Settings',
    'billing.php'    => 'Billing & Subscription',
    'profile.php'    => 'My Profile',
];
$page_title = $page_titles[$current_page] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> · <?= APP_NAME ?></title>
    <meta name="description" content="<?= APP_NAME ?> — Modern Institution Management SaaS">
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js')
                    .then(reg => console.log('Service Worker registered'))
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
        }
    </script>
    <style>
        :root {
            --brand-600: <?= $theme_color ?>;
            --brand-500: <?= $theme_color ?>;
            --primary: <?= $theme_color ?>;
        }
    </style>
</head>
<body>
<?php if (isset($_SESSION['original_user_id'])): ?>
<div style="background: var(--danger); color: #fff; padding: 10px; text-align: center; font-weight: 600; z-index: 9999; position: relative; box-shadow: var(--shadow-sm);">
    <i class="bi bi-exclamation-triangle-fill" style="margin-right:6px;"></i> You are currently impersonating <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>.
    <form method="POST" style="display:inline; margin-left: 15px;">
        <input type="hidden" name="action" value="return_to_admin">
        <button type="submit" class="btn btn-sm" style="background: #fff; color: var(--danger); padding: 4px 10px; border-radius: 4px;">Return to Admin</button>
    </form>
</div>
<?php endif; ?>
<script>
    // Restore sidebar state immediately to prevent flicker
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.body.classList.add('sidebar-is-collapsed');
    }
    if (localStorage.getItem('theme_dark') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>
<div class="app-container">

    <!-- ── Sidebar ───────────────────────────────────────────── -->
    <aside class="sidebar" id="appSidebar">

        <!-- Brand -->
        <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-logo">
            <?php if ($tenant_logo && file_exists(dirname(__DIR__) . '/' . $tenant_logo)): ?>
                <img src="<?= BASE_URL ?>/<?= $tenant_logo ?>" alt="Logo" style="max-height: 32px; max-width: 100%;">
            <?php else: ?>
                <div class="sidebar-logo-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <span class="sidebar-logo-text">Inst<span>CRM</span></span>
            <?php endif; ?>
        </a>

        <!-- Core -->
        <div class="sidebar-section-label">Core</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/dashboard.php">
                    <i class="bi bi-speedometer2" style="color:var(--brand-600);"></i> <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Leads & Admissions -->
        <div class="sidebar-section-label">Leads & Admissions</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'enquiries.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/enquiries.php">
                    <i class="bi bi-telephone-inbound" style="color:#ec4899;"></i> <span>Enquiries</span>
                </a>
            </li>
            <li class="<?= $current_page == 'followups.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/followups.php">
                    <i class="bi bi-arrow-repeat" style="color:var(--warning);"></i> <span>Follow-ups</span>
                </a>
            </li>
            <li class="<?= $current_page == 'admissions.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admissions.php">
                    <i class="bi bi-person-check" style="color:var(--success);"></i> <span>Admissions</span>
                </a>
            </li>
        </ul>

        <!-- Academic -->
        <div class="sidebar-section-label">Academic</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'materials.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/materials.php">
                    <i class="bi bi-book" style="color:#8b5cf6;"></i> <span>Study Materials</span>
                </a>
            </li>
            <li class="<?= $current_page == 'attendance.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/attendance.php">
                    <i class="bi bi-calendar2-check" style="color:#06b6d4;"></i> <span>Attendance</span>
                </a>
            </li>
        </ul>

        <!-- Finance -->
        <div class="sidebar-section-label">Finance</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'fees.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/fees.php">
                    <i class="bi bi-credit-card" style="color:#10b981;"></i> <span>Fees & Receipts</span>
                </a>
            </li>
            <li class="<?= $current_page == 'accounts.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/accounts.php">
                    <i class="bi bi-bar-chart-line" style="color:#f59e0b;"></i> <span>Accounts & Expense</span>
                </a>
            </li>
        </ul>

        <!-- Analytics -->
        <div class="sidebar-section-label">Analytics</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/reports.php">
                    <i class="bi bi-pie-chart-fill" style="color:#3b82f6;"></i> <span>Reports</span>
                </a>
            </li>
        </ul>

        <!-- Administration -->
        <?php if ($user_role === 'Admin'): ?>
        <div class="sidebar-section-label">Administration</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'staff.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/staff.php">
                    <i class="bi bi-people-fill" style="color:#6366f1;"></i> <span>Staff Directory</span>
                </a>
            </li>
            <li class="<?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/settings.php">
                    <i class="bi bi-gear-fill" style="color:#64748b;"></i> <span>Settings</span>
                </a>
            </li>
            <li class="<?= $current_page == 'billing.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/billing.php">
                    <i class="bi bi-credit-card-fill" style="color:#f59e0b;"></i> <span>Billing</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

    </aside>

    <!-- ── Main Wrapper ───────────────────────────────────────── -->
    <div class="main-wrapper" id="appMainWrapper">

        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-secondary btn-icon" id="sidebarToggle" style="margin-right:8px; border:none; box-shadow:none; color:var(--ink-500); background:transparent;"><i class="bi bi-list" style="font-size:20px;"></i></button>
                <div class="topbar-breadcrumb">
                    <span class="page-name"><?= $page_title ?></span>
                </div>
                <div class="topbar-divider"></div>
                <span class="topbar-institution">
                    <i class="bi bi-building" style="color:var(--primary);margin-right:4px;"></i><?= $tenant_name ?>
                </span>
            </div>
            <div class="topbar-right">
                <a href="<?= BASE_URL ?>/" class="btn btn-secondary btn-icon" style="margin-right: 4px; border:none; background:transparent; color:var(--ink-500); font-size:1.1rem;" title="Landing Page"><i class="bi bi-globe"></i></a>
                <button class="btn btn-secondary btn-icon" onclick="toggleDarkMode()" style="margin-right: 12px; border:none; background:transparent; color:var(--ink-500); font-size:1.1rem;" title="Toggle Dark Mode"><i class="bi bi-moon-fill" id="darkModeIcon"></i></button>
                <button class="btn btn-info-soft btn-sm" onclick="openQrModal('QR Scan', <?= $tenant_id ?>)" style="margin-right: 12px; background: var(--info-light); color: var(--info); border: none;"><i class="bi bi-qr-code"></i> Public QR</button>
                <span class="topbar-time" id="topbarClock"></span>
                <div class="profile-menu">
                    <button class="profile-btn" onclick="document.getElementById('profileDropdown').classList.toggle('show')">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:14px;"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                        <span style="font-weight:600;font-size:0.85rem;color:var(--ink-700);"><?= $user_name ?></span>
                        <i class="bi bi-chevron-down" style="font-size:0.75rem;color:var(--ink-500);"></i>
                    </button>
                    <div id="profileDropdown" class="dropdown-menu">
                        <div class="dropdown-header">
                            <strong><?= $user_name ?></strong>
                            <div class="text-muted" style="font-size:0.75rem;"><?= $user_role ?></div>
                        </div>
                        <a href="<?= BASE_URL ?>/profile.php" class="dropdown-item"><i class="bi bi-person-circle"></i> My Profile</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/auth/logout.php" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="content">
            <?php if ($sub_status === 'Expired' && $current_page === 'billing.php'): ?>
            <div style="background: var(--danger); color: #fff; padding: 12px; text-align: center; font-weight: 600; box-shadow: var(--shadow-sm); margin: -24px -24px 24px -24px;">
                <i class="bi bi-exclamation-triangle-fill" style="margin-right:6px;"></i> Your subscription has expired. Please upgrade your plan to restore full access.
            </div>
            <?php endif; ?>
            
            <?php display_flash_message(); ?>
            <script>
                function toggleDarkMode() {
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    if (isDark) {
                        document.documentElement.removeAttribute('data-theme');
                        localStorage.setItem('theme_dark', 'false');
                        document.getElementById('darkModeIcon').classList.replace('bi-sun-fill', 'bi-moon-fill');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        localStorage.setItem('theme_dark', 'true');
                        document.getElementById('darkModeIcon').classList.replace('bi-moon-fill', 'bi-sun-fill');
                    }
                }
                
                window.addEventListener('DOMContentLoaded', () => {
                    if (localStorage.getItem('theme_dark') === 'true') {
                        const icon = document.getElementById('darkModeIcon');
                        if(icon) icon.classList.replace('bi-moon-fill', 'bi-sun-fill');
                    }
                });
                
                // Apply the initial classes if saved in localStorage
                if (document.body.classList.contains('sidebar-is-collapsed')) {
                    document.getElementById('appSidebar').classList.add('collapsed');
                    document.getElementById('appMainWrapper').classList.add('expanded');
                    document.body.classList.remove('sidebar-is-collapsed');
                }
                
                document.getElementById('sidebarToggle').addEventListener('click', function() {
                    const sidebar = document.getElementById('appSidebar');
                    const mainWrapper = document.getElementById('appMainWrapper');
                    
                    sidebar.classList.toggle('collapsed');
                    mainWrapper.classList.toggle('expanded');
                    
                    if (sidebar.classList.contains('collapsed')) {
                        localStorage.setItem('sidebar_collapsed', 'true');
                    } else {
                        localStorage.setItem('sidebar_collapsed', 'false');
                    }
                });
            </script>
