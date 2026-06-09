<?php
require 'config/app.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'theme_color'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN theme_color VARCHAR(50) DEFAULT '#4f46e5'");
}

echo "Migration 6 completed.";
