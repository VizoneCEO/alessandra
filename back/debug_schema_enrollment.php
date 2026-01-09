<?php
require 'db_connect.php';

echo "<h2>Table Structure: Inscripciones</h2>";
$result = $conn->query("DESCRIBE Inscripciones");
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

echo "<h2>Table Structure: Clases</h2>";
$result = $conn->query("DESCRIBE Clases");
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
?>