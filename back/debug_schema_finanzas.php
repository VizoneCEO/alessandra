<?php
require 'db_connect.php';

echo "<h2>Table: finanzas_cargos</h2>";
$result = $conn->query("DESCRIBE finanzas_cargos");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . $val . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Table: finanzas_pagos_parciales (Check if exists)</h2>";
$result = $conn->query("DESCRIBE finanzas_pagos_parciales");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . $val . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Table does not exist (Expected).";
}
?>