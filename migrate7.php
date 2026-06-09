<?php
require 'config/app.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'address'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants 
        ADD COLUMN address TEXT DEFAULT NULL,
        ADD COLUMN phone VARCHAR(50) DEFAULT NULL,
        ADD COLUMN email VARCHAR(100) DEFAULT NULL,
        ADD COLUMN receipt_notes TEXT DEFAULT NULL,
        ADD COLUMN signature_path VARCHAR(255) DEFAULT NULL
    ");
}

echo "Migration 7 completed.";
