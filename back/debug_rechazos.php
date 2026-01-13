<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Debug: Historial de Cargos</h1>";

// 1. Check ENUM
echo "<h2>1. ENUM Definition</h2>";
$res_col = $conn->query("SHOW COLUMNS FROM finanzas_cargos_historial LIKE 'tipo_evento'");
if ($res_col) {
    $row_col = $res_col->fetch_assoc();
    echo "Type: " . htmlspecialchars($row_col['Type']) . "<br>";
} else {
    echo "Error showing columns: " . $conn->error;
}

// 2. Try Dummy Insert (if we have a valid cargo_id)
echo "<h2>2. Test Insert</h2>";
// Get a valid cargo ID
$c_res = $conn->query("SELECT id FROM finanzas_cargos LIMIT 1");
if ($c_res && $c_row = $c_res->fetch_assoc()) {
    $test_id = $c_row['id'];
    echo "Testing insert for Cargo ID: " . $test_id . "<br>";

    $stmt = $conn->prepare("INSERT INTO finanzas_cargos_historial (cargo_id, tipo_evento, descripcion) VALUES (?, 'RECHAZADO', 'Test Rejection Debug')");
    $stmt->bind_param("i", $test_id);
    if ($stmt->execute()) {
        echo "Insert SUCCESS for 'RECHAZADO'.<br>";
    } else {
        echo "Insert FAILED: " . $stmt->error . "<br>";
    }
} else {
    echo "No charges found to test insert.<br>";
}

// 3. Show Last 10
echo "<h2>3. Last 10 Events</h2>";
$sql = "SELECT h.* FROM finanzas_cargos_historial h ORDER BY h.id DESC LIMIT 10";
$res = $conn->query($sql);

echo "<table border='1'><tr><th>ID</th><th>Cargo</th><th>Tipo</th><th>Desc</th><th>Fecha</th></tr>";
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['cargo_id'] . "</td>";
        echo "<td>" . $row['tipo_evento'] . "</td>";
        echo "<td>" . $row['descripcion'] . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "</tr>";
    }
}
echo "</table>";
?>