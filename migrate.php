<?php
require 'config/app.php';

$res1 = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'logo_path'");
if (mysqli_num_rows($res1) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL");
}

$res2 = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'prefix'");
if (mysqli_num_rows($res2) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN prefix VARCHAR(50) DEFAULT 'INST'");
}

echo "Migration Complete.";
