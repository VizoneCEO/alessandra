<?php
session_start();
require 'db_connect.php';

// --- Seguridad: Solo administradores pueden ejecutar estas acciones ---
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Preparamos la URL de redirección
$redirect_url = "../front/admin/dashboard.php?page=sucursales";

if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        // --- ACCIÓN: CREAR UNA NUEVA SUCURSAL ---
        case 'create_sucursal':
            $nombre = trim($_POST['nombre_sucursal']);
            if (!empty($nombre)) {
                $stmt = $conn->prepare("INSERT INTO Sucursales (nombre_sucursal) VALUES (?)");
                $stmt->bind_param("s", $nombre);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Sucursal creada exitosamente.'];
                } else {
                    if ($conn->errno == 1062) {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Ya existe una sucursal con ese nombre.'];
                    } else {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear la sucursal.'];
                    }
                }
                $stmt->close();
            }
            break;

        // --- ACCIÓN: ACTUALIZAR NOMBRE DE SUCURSAL ---
        case 'update_sucursal':
            $nombre = trim($_POST['nombre_sucursal']);
            $sucursal_id = $_POST['sucursal_id'];
            if (!empty($nombre) && !empty($sucursal_id)) {
                $stmt = $conn->prepare("UPDATE Sucursales SET nombre_sucursal = ? WHERE id = ?");
                $stmt->bind_param("si", $nombre, $sucursal_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Sucursal actualizada.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al actualizar la sucursal.'];
                }
                $stmt->close();
            }
            break;

        // --- ACCIÓN: ELIMINAR UNA SUCURSAL ---
        case 'delete_sucursal':
            $sucursal_id = $_POST['sucursal_id'];
            // OJO: La BD (ON DELETE CASCADE) se encargará de borrar las clases asociadas.
            $stmt = $conn->prepare("DELETE FROM Sucursales WHERE id = ?");
            $stmt->bind_param("i", $sucursal_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Sucursal eliminada permanentemente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar la sucursal.'];
            }
            $stmt->close();
            break;
    }
}

// Al terminar, siempre redirigimos de vuelta
header("Location: " . $redirect_url);
exit();
?>