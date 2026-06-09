<?php
// includes/header.php
require_once dirname(__DIR__) . '/config/app.php';
require_login();

$current_page = basename($_SERVER['PHP_SELF']);
$tenant_id    = get_tenant_id();
$tenant_name  = $_SESSION['tenant_name'] ?? 'Institution';
$user_name    = $_SESSION['user_name']   ?? 'User';
$user_role    = $_SESSION['user_role']   ?? 'Staff';

// Fetch specific tenant settings
$t_res = db_query($conn, "SELECT logo_path, prefix FROM tenants WHERE id = ?", [$tenant_id]);
$t_data = mysqli_fetch_assoc($t_res);
$tenant_logo = $t_data['logo_path'] ?? null;
$tenant_prefix = $t_data['prefix'] ?? 'INST';

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="app-container">

    <!-- ── Sidebar ───────────────────────────────────────────── -->
    <aside class="sidebar">

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
                    <i class="bi bi-squares"></i> Dashboard
                </a>
            </li>
        </ul>

        <!-- Leads & Admissions -->
        <div class="sidebar-section-label">Leads & Admissions</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'enquiries.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/enquiries.php">
                    <i class="bi bi-telephone-inbound"></i> Enquiries
                </a>
            </li>
            <li class="<?= $current_page == 'followups.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/followups.php">
                    <i class="bi bi-arrow-repeat"></i> Follow-ups
                </a>
            </li>
            <li class="<?= $current_page == 'admissions.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admissions.php">
                    <i class="bi bi-person-check"></i> Admissions
                </a>
            </li>
        </ul>

        <!-- Academic -->
        <div class="sidebar-section-label">Academic</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'materials.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/materials.php">
                    <i class="bi bi-book"></i> Study Materials
                </a>
            </li>
            <li class="<?= $current_page == 'attendance.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/attendance.php">
                    <i class="bi bi-calendar2-check"></i> Attendance
                </a>
            </li>
        </ul>

        <!-- Finance -->
        <div class="sidebar-section-label">Finance</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'fees.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/fees.php">
                    <i class="bi bi-credit-card"></i> Fees & Receipts
                </a>
            </li>
            <li class="<?= $current_page == 'accounts.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/accounts.php">
                    <i class="bi bi-bar-chart-line"></i> Accounts & Expense
                </a>
            </li>
        </ul>

        <!-- Analytics -->
        <div class="sidebar-section-label">Analytics</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/reports.php">
                    <i class="bi bi-pie-chart-fill"></i> Reports
                </a>
            </li>
        </ul>

        <!-- Administration -->
        <?php if ($user_role === 'Admin'): ?>
        <div class="sidebar-section-label">Administration</div>
        <ul class="sidebar-menu">
            <li class="<?= $current_page == 'staff.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/staff.php">
                    <i class="bi bi-people-fill"></i> Staff Directory
                </a>
            </li>
            <li class="<?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/settings.php">
                    <i class="bi bi-gear-fill"></i> Settings
                </a>
            </li>
        </ul>
        <?php endif; ?>

    </aside>

    <!-- ── Main Wrapper ───────────────────────────────────────── -->
    <div class="main-wrapper">

        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-breadcrumb">
                    <span class="page-name"><?= $page_title ?></span>
                </div>
                <div class="topbar-divider"></div>
                <span class="topbar-institution">
                    <i class="bi bi-building" style="color:var(--primary);margin-right:4px;"></i><?= $tenant_name ?>
                </span>
            </div>
            <div class="topbar-right">
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
            <?php display_flash_message(); ?>
