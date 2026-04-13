<?php
require 'back/db_connect.php';

$batch_id = 'msg_699742a9558548.80769064';
$remitente_id = 1;

$sql = "SELECT m.leido, u.nombre_completo 
        FROM Mensajes m
        JOIN Usuarios u ON m.destinatario_id = u.id
        WHERE m.batch_id = ? AND m.remitente_id = ? AND m.leido = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $batch_id, $remitente_id);
$stmt->execute();
$res = $stmt->get_result();

echo "Read messages count: " . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>