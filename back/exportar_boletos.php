<?php
session_start();
require 'db_connect.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

// Allow Admin (1), Finanzas (5), Ayudante (6)
$allowed_profiles = [1, 5, 6];
if (!in_array($_SESSION['perfil_id'], $allowed_profiles)) {
    die("Permisos insuficientes.");
}

// --- HEADERS FOR CSV DOWNLOAD ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_boletos_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Matricula', 'Alumno', 'Folio', 'Estado Uso', 'Evento', 'Fecha Generacion']);

// --- QUERY ---
$sql = "SELECT 
            u.matricula,
            u.nombre_completo as alumno,
            b.folio,
            CASE WHEN b.usado = 1 THEN 'Usado' ELSE 'No Usado' END as estado_uso,
            e.nombre as evento,
            b.fecha_generacion
        FROM finanzas_boletos b
        JOIN Usuarios u ON b.usuario_id = u.id
        JOIN finanzas_eventos e ON b.evento_id = e.id
        WHERE b.deleted_at IS NULL
        ORDER BY e.fecha DESC, b.id ASC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>