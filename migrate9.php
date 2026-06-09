<?php
// migrate9.php
require_once __DIR__ . '/config/db.php';

try {
    mysqli_begin_transaction($conn);

    // 1. Add subscription columns
    $sql_add_cols = "ALTER TABLE tenants 
        ADD COLUMN subscription_plan ENUM('Free', 'Monthly', 'Yearly') DEFAULT 'Free',
        ADD COLUMN subscription_status ENUM('Trial', 'Active', 'Expired') DEFAULT 'Trial',
        ADD COLUMN trial_ends_at DATETIME NULL,
        ADD COLUMN subscription_ends_at DATETIME NULL";
    
    db_query($conn, $sql_add_cols);
    echo "Columns added successfully.\n";

    // 2. Set existing tenants to Active Yearly to prevent lockouts during testing
    $sql_update_existing = "UPDATE tenants 
        SET subscription_plan = 'Yearly', 
            subscription_status = 'Active', 
            trial_ends_at = DATE_ADD(NOW(), INTERVAL 3 DAY),
            subscription_ends_at = DATE_ADD(NOW(), INTERVAL 1 YEAR)";
            
    db_query($conn, $sql_update_existing);
    echo "Existing tenants updated to Active Yearly.\n";

    mysqli_commit($conn);
    echo "Migration 9 completed successfully!\n";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "Migration failed: " . $e->getMessage() . "\n";
}
