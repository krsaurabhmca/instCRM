<?php
// config/db.php
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'instcrm';

// Establish connection to MySQL server
$conn = mysqli_connect($db_host, $db_user, $db_pass);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Create database if not exists
if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name`")) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select database
if (!mysqli_select_db($conn, $db_name)) {
    die("Error selecting database: " . mysqli_error($conn));
}

// Auto-generate tables if they do not exist
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'tenants'");
if (mysqli_num_rows($table_check) == 0) {
    // Read schema.sql and execute
    $schema_path = dirname(__DIR__) . '/schema.sql';
    if (file_exists($schema_path)) {
        $schema_sql = file_get_contents($schema_path);
        // Split SQL statements by semicolon (simple check)
        $queries = explode(';', $schema_sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!mysqli_query($conn, $query)) {
                    // Ignore errors if DB already exists or tables already exist
                }
            }
        }
    }
}

// Procedural helpers to execute parameterized queries safely
function db_query($conn, $query, $params = [], $types = "") {
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        die("Query preparation failed: " . mysqli_error($conn) . " | Query: " . $query);
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            // Auto-detect types
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= "i";
                } elseif (is_double($param)) {
                    $types .= "d";
                } else {
                    $types .= "s";
                }
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        die("Query execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function db_insert($conn, $query, $params = [], $types = "") {
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        die("Insert query preparation failed: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= "i";
                } elseif (is_double($param)) {
                    $types .= "d";
                } else {
                    $types .= "s";
                }
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        die("Insert execution failed: " . mysqli_stmt_error($stmt));
    }
    
    $insert_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $insert_id;
}
?>
