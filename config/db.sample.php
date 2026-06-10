<?php
// config/db.sample.php
// Rename this file to db.php and update with your database details

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'instcrm';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

function db_query($conn, $query, $params = [], $types = "") {
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) { die("Query preparation failed: " . mysqli_error($conn) . " | Query: " . $query); }
    if (!empty($params)) {
        if (empty($types)) {
            foreach ($params as $param) {
                if (is_int($param)) { $types .= "i"; }
                elseif (is_double($param)) { $types .= "d"; }
                else { $types .= "s"; }
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) { die("Query execution failed: " . mysqli_stmt_error($stmt)); }
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function db_insert($conn, $query, $params = [], $types = "") {
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) { die("Insert query preparation failed: " . mysqli_error($conn)); }
    if (!empty($params)) {
        if (empty($types)) {
            foreach ($params as $param) {
                if (is_int($param)) { $types .= "i"; }
                elseif (is_double($param)) { $types .= "d"; }
                else { $types .= "s"; }
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) { die("Insert execution failed: " . mysqli_stmt_error($stmt)); }
    $insert_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $insert_id;
}
?>
