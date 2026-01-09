<?php
require 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM finanzas_cargos LIKE 'estado'");
if ($row = $result->fetch_assoc()) {
    echo "Field: " . $row['Field'] . "\n";
    echo "Type: " . $row['Type'] . "\n";
} else {
    echo "Column not found.";
}
?>