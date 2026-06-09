<?php
require_once __DIR__ . '/config/app.php';

$tenant_id = intval($_GET['tenant_id'] ?? 0);
$source    = sanitize($_GET['source'] ?? 'QR Code');

if (!$tenant_id) {
    die("Invalid Institute Link.");
}

// Fetch tenant branding
$t_res = db_query($conn, "SELECT name, logo_path FROM tenants WHERE id = ?", [$tenant_id]);
if (mysqli_num_rows($t_res) == 0) {
    die("Institute not found.");
}
$tenant = mysqli_fetch_assoc($t_res);

// Fetch courses for dropdown
$c_res = db_query($conn, "SELECT id, name FROM courses WHERE tenant_id = ? ORDER BY name ASC", [$tenant_id]);
$courses = [];
while ($row = mysqli_fetch_assoc($c_res)) {
    $courses[] = $row;
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $mobile = sanitize($_POST['mobile']);
    $email = sanitize($_POST['email']);
    $course_id = intval($_POST['course_id']);
    $notes = "Lead from QR. Course ID: " . $course_id;

    // Ensure the source exists in the tenant's enquiry_sources list so it appears in dropdowns later
    $src_res = db_query($conn, "SELECT id FROM enquiry_sources WHERE tenant_id = ? AND name = ?", [$tenant_id, $source]);
    if (mysqli_num_rows($src_res) == 0) {
        db_query($conn, "INSERT INTO enquiry_sources (tenant_id, name) VALUES (?, ?)", [$tenant_id, $source]);
    }

    db_insert($conn, "INSERT INTO enquiries (tenant_id, date, name, mobile, email, source, status, notes) VALUES (?, CURDATE(), ?, ?, ?, ?, 'New', ?)", [
        $tenant_id, $name, $mobile, $email, $source, $notes
    ]);
    
    $success = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry Form - <?= htmlspecialchars($tenant['name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: var(--ink-50); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .public-card { background: #fff; max-width: 480px; width: 100%; border-radius: var(--r-xl); box-shadow: var(--shadow-xl); padding: 32px; border: 1px solid var(--ink-100); }
        .public-logo { max-height: 60px; margin-bottom: 16px; }
        .public-title { font-family: var(--font-display); font-size: 1.4rem; font-weight: 700; color: var(--ink-900); margin-bottom: 24px; text-align: center; }
        .success-box { background: var(--success-light); border: 1px solid #a7f3d0; padding: 16px; border-radius: var(--r-md); color: var(--success); text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="public-card">
    <div style="text-align: center;">
        <?php if ($tenant['logo_path'] && file_exists(__DIR__ . '/' . $tenant['logo_path'])): ?>
            <img src="<?= BASE_URL ?>/<?= $tenant['logo_path'] ?>" alt="Logo" class="public-logo">
        <?php else: ?>
            <div style="font-size: 2rem; color: var(--primary);"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <div class="public-title">Registration Form</div>
    </div>

    <?php if ($success): ?>
        <div class="success-box">
            <i class="bi bi-check-circle-fill" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            <strong>Thank You!</strong><br>Your enquiry has been received. Our team will contact you shortly.
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="Enter your name">
            </div>
            <div class="form-group">
                <label class="form-label">Mobile Number *</label>
                <input type="text" name="mobile" class="form-control" required placeholder="Enter 10-digit mobile number">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Optional">
            </div>
            <div class="form-group">
                <label class="form-label">Interested Course *</label>
                <select name="course_id" class="form-control" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 12px; font-size: 1rem;">Submit Details</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
