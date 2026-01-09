<?php
require 'db_connect.php';

echo "<h2>Content of Clases Table (Last 10)</h2>";
$result = $conn->query("SELECT * FROM Clases ORDER BY id DESC LIMIT 10");
if ($result) {
    echo "<table border='1'><tr><th>ID</th><th>Materia</th><th>Profesor</th><th>Ciclo</th><th>Sucursal</th><th>Grupo</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['materia_id'] . "</td>";
        echo "<td>" . $row['profesor_id'] . "</td>";
        echo "<td>" . $row['ciclo_id'] . "</td>";
        echo "<td>" . $row['sucursal_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['grupo']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error selecting from table: " . $conn->error;
}
?>