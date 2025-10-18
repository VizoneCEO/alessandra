<?php
session_start();
require 'db_connect.php';

// --- Seguridad: Solo administradores pueden ejecutar estas acciones ---
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        // --- ACCIÓN: CREAR UN NUEVO CICLO ---
        case 'create_ciclo':
            $nombre = $_POST['nombre_ciclo'];
            $inicio = $_POST['fecha_inicio'];
            $fin = $_POST['fecha_fin'];

            $stmt = $conn->prepare("INSERT INTO Ciclos_Escolares (nombre_ciclo, fecha_inicio, fecha_fin) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nombre, $inicio, $fin);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Ciclo escolar creado exitosamente.'];
            } else {
                // Capturamos un posible error de 'nombre_ciclo' duplicado
                if ($conn->errno == 1062) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Ya existe un ciclo con ese nombre.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear el ciclo.'];
                }
            }
            $stmt->close();
            break;

        // --- ACCIÓN: ACTUALIZAR ESTADO DE UN CICLO ---
        case 'update_status':
            $ciclo_id = $_POST['ciclo_id'];
            $nuevo_estado = $_POST['nuevo_estado'];

            // Opcional pero recomendado: Si se pone un ciclo como 'activo', poner los demás como 'inactivos'.
            if ($nuevo_estado == 'activo') {
                $conn->query("UPDATE Ciclos_Escolares SET estado = 'inactivo' WHERE estado = 'activo'");
            }

            $stmt = $conn->prepare("UPDATE Ciclos_Escolares SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_estado, $ciclo_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Estado del ciclo actualizado.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al actualizar el estado.'];
            }
            $stmt->close();
            break;
    }
}

// Al terminar, siempre redirigimos de vuelta a la página de ciclos
header("Location: ../front/admin/dashboard.php?page=ciclos");
exit();
?>