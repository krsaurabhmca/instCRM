<?php
require 'config/app.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM fee_payments LIKE 'status'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE fee_payments ADD COLUMN status ENUM('Active', 'Cancelled') NOT NULL DEFAULT 'Active' AFTER receipt_number");
}

echo "Fee payments table updated successfully.";
