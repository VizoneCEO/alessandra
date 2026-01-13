<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Debug: Tickets Check</h1>";

// 1. Check Charges with 'boletos' concept or event_id
echo "<h2>1. Charges linked to Events OR contain 'Boletos'</h2>";
$sql = "SELECT id, alumno_id, concepto, monto_original, estado, evento_id, cantidad_boletos 
        FROM finanzas_cargos 
        WHERE concepto LIKE '%Boletos%' OR evento_id IS NOT NULL";
$res = $conn->query($sql);

if ($res) {
    echo "<table border='1'><tr><th>ID</th><th>Alumno</th><th>Concepto</th><th>Estado</th><th>EventoID</th><th>Qty</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['alumno_id'] . "</td>";
        echo "<td>" . $row['concepto'] . "</td>";
        echo "<td>" . $row['estado'] . "</td>";
        echo "<td>" . ($row['evento_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['cantidad_boletos'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Query failed: " . $conn->error;
}

// 2. Check Generated Tickets
echo "<h2>2. Existing Tickets in finanzas_boletos</h2>";
$sql_t = "SELECT * FROM finanzas_boletos";
$res_t = $conn->query($sql_t);
if ($res_t && $res_t->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Evento</th><th>Alumno</th><th>Cargo</th><th>Folio</th></tr>";
    while ($r = $res_t->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $r['id'] . "</td>";
        echo "<td>" . $r['evento_id'] . "</td>";
        echo "<td>" . $r['alumno_id'] . "</td>";
        echo "<td>" . $r['cargo_id'] . "</td>";
        echo "<td>" . $r['folio_asiento'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No tickets found in table 'finanzas_boletos'.";
}
?>