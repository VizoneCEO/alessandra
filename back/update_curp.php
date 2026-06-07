<?php
require 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_POST['usuario_id'] ?? '';
    $curp = strtoupper(trim($_POST['curp'] ?? ''));

    if (empty($usuario_id) || strlen($curp) !== 18) {
        echo json_encode(['success' => false, 'message' => 'CURP inválido o faltan datos.']);
        exit;
    }

    try {
        // Actualización en Contacto_Alumnos según el requerimiento
        $stmt = $conn->prepare("UPDATE Contacto_Alumnos SET curp = ? WHERE usuario_id = ?");
        $stmt->bind_param("si", $curp, $usuario_id);
        $stmt->execute();
        $stmt->close();

        // Actualizamos en Usuarios para mantener consistencia en la vista de la tabla actual
        $stmt2 = $conn->prepare("UPDATE Usuarios SET curp = ? WHERE id = ?");
        $stmt2->bind_param("si", $curp, $usuario_id);
        $stmt2->execute();
        $stmt2->close();

        echo json_encode(['success' => true, 'message' => 'CURP actualizado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
