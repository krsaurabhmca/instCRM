<?php
require_once 'config/db.php';

$tables = [];
$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}

$sql = "CREATE DATABASE IF NOT EXISTS `instcrm`;\nUSE `instcrm`;\n\n";

foreach ($tables as $table) {
    $sql .= "-- Table structure for `$table`\n";
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $res = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
    $row = mysqli_fetch_row($res);
    $sql .= $row[1] . ";\n\n";
}

file_put_contents('instcrm.sql', $sql);
echo "Database dumped to instcrm.sql successfully.";
