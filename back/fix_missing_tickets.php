<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

echo "<h1>Repair: Generate Missing Tickets</h1>";

// Find PAID charges with EVENT_ID that have NO TICKETS
$sql = "SELECT c.id, c.evento_id, c.cantidad_boletos, c.alumno_id 
        FROM finanzas_cargos c 
        LEFT JOIN finanzas_boletos b ON c.id = b.cargo_id 
        WHERE c.estado = 'Pagado' 
        AND c.evento_id IS NOT NULL 
        AND c.evento_id > 0
        AND b.id IS NULL";

$res = $conn->query($sql);
$count = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $charge_id = $row['id'];
        $evt_id = $row['evento_id'];
        $qty = intval($row['cantidad_boletos']) ?: 1;
        $alumno_id = $row['alumno_id'];
        $pago_id = 0;

        echo "Generating $qty tickets for Charge #$charge_id (Event $evt_id)...<br>";

        for ($i = 0; $i < $qty; $i++) {
            // Get Next Folio
            $res_folio = $conn->query("SELECT COALESCE(MAX(folio_asiento), 0) + 1 as next_folio FROM finanzas_boletos WHERE evento_id = $evt_id");
            $next_folio = ($res_folio && $row_f = $res_folio->fetch_assoc()) ? $row_f['next_folio'] : 1;

            $stmt = $conn->prepare("INSERT INTO finanzas_boletos (evento_id, alumno_id, cargo_id, pago_id, folio_asiento, fecha_emision) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiii", $evt_id, $alumno_id, $charge_id, $pago_id, $next_folio);
            if ($stmt->execute()) {
                echo " - Ticket #$next_folio created.<br>";
            } else {
                echo " - FATAL: " . $stmt->error . "<br>";
            }
        }
        $count++;
    }
}

echo "<h3>Repair Complete. Fixed charges: $count</h3>";
?>