<?php
require 'back/db_connect.php';

function checkTable($conn, $tableName)
{
    echo "Checking table: $tableName\n";
    $result = $conn->query("DESCRIBE $tableName");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table $tableName not found or error: " . $conn->error . "\n";
    }
    echo "\n";
}

checkTable($conn, 'Log_Actividades');
checkTable($conn, 'finanzas_cargos_historial');
?>