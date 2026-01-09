<?php
require 'db_connect.php';

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
} else {
    echo "Error describing table: " . $conn->error;
}

echo "<h2>Create Table Statement</h2>";
$result = $conn->query("SHOW CREATE TABLE Clases");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
} else {
    echo "Error showing create table: " . $conn->error;
}
?>
