<?php
require 'config/app.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'smtp_host'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN smtp_host VARCHAR(255) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN smtp_port INT DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN smtp_user VARCHAR(255) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN smtp_pass VARCHAR(255) DEFAULT NULL");
}

echo "Migration 5 completed.";
