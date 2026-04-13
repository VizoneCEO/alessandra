<?php
require 'back/db_connect.php';

$batch_id = 'msg_699742a9558548.80769064';

// Get the first message id for this batch
$sql = "SELECT id FROM Mensajes WHERE batch_id = '$batch_id' LIMIT 1";
$res = $conn->query($sql);
$row = $res->fetch_assoc();
$id = $row['id'];

if ($id) {
    // Toggle leido
    $conn->query("UPDATE Mensajes SET leido = 1 WHERE id = $id");
    echo "Updated message $id to leido=1\n";
} else {
    echo "No message found for batch $batch_id\n";
}
?>