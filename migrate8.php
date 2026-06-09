<?php
require 'config/app.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'receipt_notes'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants 
        ADD COLUMN receipt_notes TEXT DEFAULT NULL,
        ADD COLUMN signature_path VARCHAR(255) DEFAULT NULL
    ");
}

echo "Migration 8 completed.";
