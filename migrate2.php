<?php
require 'config/app.php';

$res1 = mysqli_query($conn, "SHOW COLUMNS FROM batches LIKE 'end_date'");
if (mysqli_num_rows($res1) == 0) {
    mysqli_query($conn, "ALTER TABLE batches ADD COLUMN end_date DATE DEFAULT NULL AFTER start_date");
}

$res2 = mysqli_query($conn, "SHOW COLUMNS FROM batches LIKE 'capacity'");
if (mysqli_num_rows($res2) == 0) {
    mysqli_query($conn, "ALTER TABLE batches ADD COLUMN capacity INT NOT NULL DEFAULT 30 AFTER timing");
}

echo "Batches table updated successfully.";
