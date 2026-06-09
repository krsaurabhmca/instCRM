<?php
// install.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$error = '';
$success = '';

if (file_exists(__DIR__ . '/config/db.php')) {
    die("InstCRM is already installed. If you wish to reinstall, please delete config/db.php first.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['db_host'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? '';

    if (empty($host) || empty($user) || empty($name)) {
        $error = "Host, Username, and Database Name are required.";
    } else {
        // Attempt connection
        $conn = @mysqli_connect($host, $user, $pass);
        if (!$conn) {
            $error = "Connection failed: " . mysqli_connect_error();
        } else {
            // Create DB if not exists
            mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$name`");
            if (!mysqli_select_db($conn, $name)) {
                $error = "Could not select database: " . mysqli_error($conn);
            } else {
                // Import SQL
                $sql_file = __DIR__ . '/instcrm.sql';
                if (!file_exists($sql_file)) {
                    $error = "instcrm.sql file not found. Cannot import schema.";
                } else {
                    $sql_content = file_get_contents($sql_file);
                    // Extremely basic SQL import
                    if (mysqli_multi_query($conn, $sql_content)) {
                        while (mysqli_next_result($conn)) {;} // flush multi_queries
                        
                        // Create config/db.php
                        $config_content = "<?php\n// config/db.php\n\$db_host = '$host';\n\$db_user = '$user';\n\$db_pass = '$pass';\n\$db_name = '$name';\n\n\$conn = mysqli_connect(\$db_host, \$db_user, \$db_pass, \$db_name);\nif (!\$conn) { die(\"Database connection failed: \" . mysqli_connect_error()); }\nmysqli_set_charset(\$conn, \"utf8mb4\");\n?>";
                        
                        if (file_put_contents(__DIR__ . '/config/db.php', $config_content)) {
                            // Success!
                            $_SESSION['flash'] = ['type' => 'success', 'message' => 'InstCRM Installed Successfully! Please register your first institution.'];
                            header("Location: index.php");
                            exit;
                        } else {
                            $error = "Failed to write to config/db.php. Please check file permissions.";
                        }
                    } else {
                        $error = "Failed to import database schema: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstCRM Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: var(--bg-body); font-family: var(--font-base); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .install-card { background: white; border-radius: var(--radius-lg); padding: 40px; box-shadow: var(--shadow-lg); width: 100%; max-width: 500px; }
        .install-header { text-align: center; margin-bottom: 30px; }
        .install-header h1 { font-family: var(--font-display); color: var(--ink-900); font-size: 1.8rem; margin-bottom: 8px; }
        .install-header p { color: var(--ink-500); }
    </style>
</head>
<body>

<div class="install-card">
    <div class="install-header">
        <i class="bi bi-rocket-takeoff-fill" style="font-size: 3rem; color: var(--primary); margin-bottom: 16px; display: inline-block;"></i>
        <h1>Install InstCRM</h1>
        <p>Set up your database connection to get started</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Database Host</label>
            <input type="text" name="db_host" class="form-control" value="localhost" required>
        </div>
        <div class="form-group">
            <label class="form-label">Database Username</label>
            <input type="text" name="db_user" class="form-control" value="root" required>
        </div>
        <div class="form-group">
            <label class="form-label">Database Password</label>
            <input type="password" name="db_pass" class="form-control" placeholder="Leave blank if none">
        </div>
        <div class="form-group">
            <label class="form-label">Database Name</label>
            <input type="text" name="db_name" class="form-control" value="instcrm" required>
            <div class="text-muted" style="font-size: 12px; margin-top: 4px;">If this database does not exist, it will be created.</div>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Run Installer</button>
    </form>
</div>

</body>
</html>
