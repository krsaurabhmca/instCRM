<?php
require 'config/app.php';

mysqli_query($conn, "ALTER TABLE enquiries MODIFY COLUMN source VARCHAR(100) NOT NULL DEFAULT 'Walk-in'");

$res = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'show_logo_receipt'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN show_logo_receipt TINYINT(1) DEFAULT 1");
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN show_logo_id TINYINT(1) DEFAULT 1");
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN show_qr_receipt TINYINT(1) DEFAULT 1");
    mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN show_qr_id TINYINT(1) DEFAULT 1");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS enquiry_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default values for existing tenants
$tenants = mysqli_query($conn, "SELECT id FROM tenants");
while ($t = mysqli_fetch_assoc($tenants)) {
    $tid = $t['id'];
    
    // Check if seeded
    $c = mysqli_query($conn, "SELECT id FROM expense_categories WHERE tenant_id = $tid");
    if (mysqli_num_rows($c) == 0) {
        $cats = ['Rent', 'Electricity', 'Salaries', 'Marketing', 'Printing & Stationery', 'Internet & Telephone', 'Other'];
        foreach ($cats as $cat) {
            mysqli_query($conn, "INSERT INTO expense_categories (tenant_id, name) VALUES ($tid, '$cat')");
        }
    }
    
    $s = mysqli_query($conn, "SELECT id FROM enquiry_sources WHERE tenant_id = $tid");
    if (mysqli_num_rows($s) == 0) {
        $srcs = ['Walk-in', 'Website', 'Call', 'Social Media', 'Other'];
        foreach ($srcs as $src) {
            mysqli_query($conn, "INSERT INTO enquiry_sources (tenant_id, name) VALUES ($tid, '$src')");
        }
    }
}

echo "Migration 4 completed.";
