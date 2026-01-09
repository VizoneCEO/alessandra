<?php
require 'db_connect.php';

$table = 'finanzas_cargos_historial';
$check = $conn->query("SHOW TABLES LIKE '$table'");

if ($check->num_rows > 0) {
    echo "Table '$table' EXISTS.\n";
    $cols = $conn->query("DESCRIBE $table");
    while ($row = $cols->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Table '$table' DOES NOT EXIST.\n";
}

$table2 = 'finanzas_pagos';
$check2 = $conn->query("SHOW TABLES LIKE '$table2'");
if ($check2->num_rows > 0) {
    echo "Table '$table2' EXISTS.\n";
} else {
    echo "Table '$table2' DOES NOT EXIST.\n";
}
?>