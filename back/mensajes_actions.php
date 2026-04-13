<?php
session_start();
header('Content-Type: application/json');
require 'db_connect.php';
require 'log_helper.php';

// Helper for JSON response
function jsonResponse($success, $message, $data = [])
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// --- CHECK AUTH ---
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado.');
}

$action = $_POST['action_type'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'send_message':
        try {
            $remitente_id = $_SESSION['user_id'];
            $target_type = $_POST['target_type'] ?? '';
            $asunto = trim($_POST['asunto'] ?? '');
            $cuerpo = trim($_POST['cuerpo'] ?? '');

            if (empty($asunto) || empty($cuerpo)) {
                jsonResponse(false, 'El asunto y el cuerpo del mensaje son obligatorios.');
            }

            $recipient_ids = [];

            // Determine recipients based on target_type
            if ($target_type === 'all') {
                $sql = "SELECT id FROM Usuarios WHERE estado = 'activo' AND deleted_at IS NULL";
                $res = $conn->query($sql);
                while ($row = $res->fetch_assoc()) {
                    $recipient_ids[] = $row['id'];
                }
            } elseif ($target_type === 'alumnos') {
                $sql = "SELECT id FROM Usuarios WHERE perfil_id = 3 AND estado = 'activo' AND deleted_at IS NULL";
                $res = $conn->query($sql);
                while ($row = $res->fetch_assoc()) {
                    $recipient_ids[] = $row['id'];
                }
            } elseif ($target_type === 'profesores') {
                $sql = "SELECT id FROM Usuarios WHERE perfil_id = 2 AND estado = 'activo' AND deleted_at IS NULL";
                $res = $conn->query($sql);
                while ($row = $res->fetch_assoc()) {
                    $recipient_ids[] = $row['id'];
                }
            } elseif ($target_type === 'administrativos') {
                // Perfiles 1, 4, 5, 6 based on requirements/knowledge
                $sql = "SELECT id FROM Usuarios WHERE perfil_id IN (1, 4, 5, 6) AND estado = 'activo' AND deleted_at IS NULL";
                $res = $conn->query($sql);
                while ($row = $res->fetch_assoc()) {
                    $recipient_ids[] = $row['id'];
                }
            } elseif ($target_type === 'specific') {
                $specific_ids = $_POST['destinatarios'] ?? [];
                if (empty($specific_ids)) {
                    jsonResponse(false, 'Debe seleccionar al menos un destinatario específico.');
                }
                // Validate IDs are integers
                $recipient_ids = array_unique(array_filter($specific_ids, 'is_numeric'));
            } else {
                jsonResponse(false, 'Tipo de destinatario no válido.');
            }

            // Remove self if included (optional logic, but usually good)
            // $recipient_ids = array_diff($recipient_ids, [$remitente_id]);

            if (empty($recipient_ids)) {
                jsonResponse(false, 'No se encontraron destinatarios activos para el criterio seleccionado.');
            }

            // Insert messages
            $conn->begin_transaction();
            // Added batch_id to INSERT
            $stmt = $conn->prepare("INSERT INTO Mensajes (remitente_id, destinatario_id, asunto, cuerpo, fecha, batch_id) VALUES (?, ?, ?, ?, NOW(), ?)");

            $batch_id = uniqid('msg_', true);

            $count = 0;
            foreach ($recipient_ids as $dest_id) {
                // Skip if somehow dest_id is invalid/empty
                if (empty($dest_id))
                    continue;

                // Added batch_id param
                $stmt->bind_param("iisss", $remitente_id, $dest_id, $asunto, $cuerpo, $batch_id);
                if ($stmt->execute()) {
                    $count++;
                }
            }
            $stmt->close();
            $conn->commit();

            if ($count > 0) {
                registrar_log($conn, $remitente_id, 'ENVIO_MENSAJE', "Enviado a $count destinatarios ($target_type). Asunto: $asunto");
                jsonResponse(true, "Mensaje enviado correctamente a $count usuarios.");
            } else {
                jsonResponse(false, 'No se pudo enviar el mensaje a ningún destinatario.');
            }

        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $t) {
            }
            error_log("Error send_message: " . $e->getMessage());
            jsonResponse(false, 'Error del servidor: ' . $e->getMessage());
        }
        break;

    case 'mark_as_read':
        $mensaje_id = $_POST['mensaje_id'] ?? 0;
        $user_id = $_SESSION['user_id'];

        if ($mensaje_id > 0) {
            $stmt = $conn->prepare("UPDATE Mensajes SET leido = 1 WHERE id = ? AND destinatario_id = ?");
            $stmt->bind_param("ii", $mensaje_id, $user_id);
            if ($stmt->execute()) {
                jsonResponse(true, 'Marcado como leído');
            } else {
                jsonResponse(false, 'Error al actualizar');
            }
            $stmt->close();
        } else {
            jsonResponse(false, 'ID de mensaje inválido');
        }
        break;

    case 'delete_batch':
        $batch_id = $_POST['batch_id'] ?? '';
        $remitente_id = $_SESSION['user_id'];

        if (!empty($batch_id)) {
            $stmt = $conn->prepare("UPDATE Mensajes SET deleted_at = NOW() WHERE batch_id = ? AND remitente_id = ?");
            $stmt->bind_param("si", $batch_id, $remitente_id);
            if ($stmt->execute()) {
                jsonResponse(true, 'Mensaje eliminado correctamente.');
            } else {
                jsonResponse(false, 'Error al eliminar el mensaje.');
            }
            $stmt->close();
        } else {
            jsonResponse(false, 'ID de mensaje no válido.');
        }
        break;

        break;

    case 'get_batch_readers':
        $batch_id = $_POST['batch_id'] ?? '';
        $remitente_id = $_SESSION['user_id'];

        if (empty($batch_id)) {
            jsonResponse(false, 'ID de lote no proporcionado.');
        }

        // Fetch all recipients for this batch and their read status
        $sql = "SELECT m.leido, m.fecha as fecha_envio, u.nombre_completo, p.nombre_perfil
                FROM Mensajes m
                JOIN Usuarios u ON m.destinatario_id = u.id
                LEFT JOIN Perfiles p ON u.perfil_id = p.id
                WHERE m.batch_id = ? AND m.remitente_id = ? AND m.deleted_at IS NULL AND m.leido = 1
                ORDER BY m.fecha DESC, u.nombre_completo ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $batch_id, $remitente_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $readers = [];
        if ($res) {
            $readers = $res->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();

        jsonResponse(true, 'Lectores recuperados', ['readers' => $readers]);
        break;

    case 'get_users_list':
        // Helper for specific selector
        $sql = "SELECT id, nombre_completo, perfil_id FROM Usuarios WHERE estado = 'activo' AND deleted_at IS NULL ORDER BY nombre_completo ASC";
        $res = $conn->query($sql);
        $users = [];
        if ($res) {
            $users = $res->fetch_all(MYSQLI_ASSOC);
        }
        jsonResponse(true, 'Lista de usuarios', ['users' => $users]);
        break;

    default:
        jsonResponse(false, 'Acción no válida');
}
?>