<?php
// back/log_helper.php

function registrar_log($conn, $usuario_id, $accion, $descripcion, $detalles = null)
{
    // Capture IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    // Schema check revealed: Columns are (id, usuario_id, accion, detalle, fecha, ip_address)
    // We must adapt to this. We will combine description and details into 'detalle'.

    $full_detail = $descripcion;
    if (!empty($detalles)) {
        $full_detail .= " | " . (is_array($detalles) ? json_encode($detalles) : $detalles);
    }

    $stmt = $conn->prepare("INSERT INTO Log_Actividades (usuario_id, accion, detalle, ip_address, fecha) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        // Types: i (int), s (string), s (string), s (string)
        $stmt->bind_param("isss", $usuario_id, $accion, $full_detail, $ip);

        if (!$stmt->execute()) {
            error_log("Failed to execute log insert: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare log insert: " . $conn->error);
    }
}
?>