<?php
session_start();
require 'db_connect.php';
require 'log_helper.php';

// --- Seguridad ---
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil_id'] != 1 && $_SESSION['perfil_id'] != 6)) {
    header("Location: ../index.php");
    exit();
}

// Helper function for JSON response
function jsonResponse($success, $message, $redirect = null)
{
    echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
    exit();
}

if (isset($_POST['action'])) {

    // --- SECURITY CHECK FOR PROFILE 6 ---
    // Moved inside action check to handle specific actions if needed, 
    // but the global check at top of file still applies for access.
    // The destructive check needs to return JSON too.
    if ($_SESSION['perfil_id'] == 6) {
        $destructive_actions = ['toggle_status', 'delete_account', 'delete_user'];
        if (in_array($_POST['action'], $destructive_actions)) {
            jsonResponse(false, 'Permisos insuficientes: El Ayudante no puede eliminar datos.');
        }
    }

    switch ($_POST['action']) {

        case 'create_user':
            $nombre = trim($_POST['nombre_completo']);
            $curp = trim($_POST['curp']);
            $matricula = trim($_POST['matricula'] ?? '');
            $perfil_id = $_POST['perfil_id'];
            $forma = $_POST['forma'] ?? 'presencial';

            if (empty($nombre) || empty($curp) || empty($perfil_id)) {
                jsonResponse(false, 'Error: Todos los campos son requeridos.');
            }

            $stmt = $conn->prepare("SELECT id FROM Usuarios WHERE curp = ? AND deleted_at IS NULL");
            $stmt->bind_param("s", $curp);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                jsonResponse(false, 'Error: El CURP ya está registrado.');
            } else {
                $stmt = $conn->prepare("INSERT INTO Usuarios (nombre_completo, curp, perfil_id, forma, password_hash, estado, matricula) VALUES (?, ?, ?, ?, NULL, 'activo', ?)");
                $stmt->bind_param("ssiss", $nombre, $curp, $perfil_id, $forma, $matricula);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    registrar_log($conn, $_SESSION['user_id'], 'CREACION_USUARIO', "Usuario ID: $new_id, CURP: $curp");
                    jsonResponse(true, 'Usuario creado exitosamente.');
                } else {
                    jsonResponse(false, 'Error al crear el usuario: ' . $stmt->error);
                }
            }
            $stmt->close();
            break;

        case 'reset_password':
            $user_id = $_POST['user_id'];
            $stmt = $conn->prepare("UPDATE Usuarios SET password_hash = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'RESET_PASSWORD', "Contraseña liberada. Usuario ID: $user_id");
                jsonResponse(true, 'Contraseña liberada.');
            } else {
                jsonResponse(false, 'Error al liberar contraseña.');
            }
            $stmt->close();
            break;

        case 'change_profile':
            $user_id = $_POST['user_id'];
            $new_perfil_id = $_POST['perfil_id'];
            $forma = $_POST['forma'] ?? 'presencial';
            $nombre_completo = trim($_POST['nombre_completo']);
            $matricula = trim($_POST['matricula'] ?? '');

            if (empty($nombre_completo)) {
                jsonResponse(false, 'Error: El nombre no puede estar vacío.');
            }

            $stmt = $conn->prepare("UPDATE Usuarios SET perfil_id = ?, nombre_completo = ?, forma = ?, matricula = ? WHERE id = ?");
            $stmt->bind_param("isssi", $new_perfil_id, $nombre_completo, $forma, $matricula, $user_id);
            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'UPDATE_USER', "Usuario actualizado: $nombre_completo", "ID: $user_id, Perfil: $new_perfil_id");
                jsonResponse(true, 'Usuario actualizado correctamente.');
            } else {
                jsonResponse(false, 'Error al actualizar el usuario: ' . $stmt->error);
            }
            $stmt->close();
            break;

        case 'toggle_status':
            $user_id = $_POST['user_id'];
            $stmt = $conn->prepare("SELECT estado FROM Usuarios WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();

            if (!$res)
                jsonResponse(false, 'Usuario no encontrado.');

            $current_status = $res['estado'];
            $new_status = ($current_status == 'activo') ? 'inactivo' : 'activo';

            $stmt_update = $conn->prepare("UPDATE Usuarios SET estado = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_status, $user_id);
            if ($stmt_update->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'TOGGLE_STATUS', "Estado cambiado a $new_status", "Usuario ID: $user_id");
                jsonResponse(true, 'Estado del usuario cambiado a ' . $new_status . '.');
            } else {
                jsonResponse(false, 'Error al cambiar el estado.');
            }
            $stmt->close();
            $stmt_update->close();
            break;

        case 'create_account':
            $banco = trim($_POST['banco']);
            $titular = trim($_POST['titular']);
            $clabe = trim($_POST['clabe']);
            $cuenta = trim($_POST['numero_cuenta']);

            if (empty($banco) || empty($titular)) {
                jsonResponse(false, 'Error: Banco y Titular son obligatorios.');
            }

            $stmt = $conn->prepare("INSERT INTO Finanzas_Cuentas (banco, titular, clabe, numero_cuenta) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $banco, $titular, $clabe, $cuenta);

            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'CREATE_ACCOUNT', "Cuenta agregada: $banco - $titular");
                jsonResponse(true, 'Cuenta inv. agregada exitosamente.');
            } else {
                jsonResponse(false, 'Error al agregar cuenta: ' . $stmt->error);
            }
            $stmt->close();
            break;

        case 'assign_account':
            $user_id = $_POST['user_id'];
            $account_id = $_POST['account_id'];

            if ($account_id === 'NULL' || $account_id === '') {
                $stmt = $conn->prepare("UPDATE Usuarios SET cuenta_deposito_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE Usuarios SET cuenta_deposito_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $account_id, $user_id);
            }

            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'ASSIGN_ACCOUNT', "Cuenta $account_id asignada a Usuario $user_id");
                jsonResponse(true, 'Cuenta asignada correctamente.');
            } else {
                jsonResponse(false, 'Error al asignar cuenta.');
            }
            $stmt->close();
            break;

        case 'delete_account':
            // Security check already applied above via $destructive_actions check? 
            // Wait, I put the check logic at the top of switch, but need to ensure it covers this case.
            // Yes, checking $destructive_actions ('delete_account') at top handles it.

            $account_id = $_POST['account_id'];

            $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM Usuarios WHERE cuenta_deposito_id = ? AND deleted_at IS NULL");
            $stmt_check->bind_param("i", $account_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result()->fetch_assoc();
            $users_count = $result['count'];
            $stmt_check->close();

            if ($users_count > 0) {
                jsonResponse(false, 'Error: No se puede eliminar una cuenta que tiene alumnos asignados.');
            } else {
                $stmt_del = $conn->prepare("UPDATE Finanzas_Cuentas SET deleted_at = NOW() WHERE id = ?");
                $stmt_del->bind_param("i", $account_id);
                if ($stmt_del->execute()) {
                    registrar_log($conn, $_SESSION['user_id'], 'ELIMINACION_CUENTA', "Cuenta ID: $account_id eliminada lógicamente.");
                    jsonResponse(true, 'Cuenta eliminada exitosamente.');
                } else {
                    jsonResponse(false, 'Error al eliminar la cuenta: ' . $stmt_del->error);
                }
                $stmt_del->close();
            }
            break;
    }
}

// Default redirect if no action (shouldn't happen with AJAX but good fallback)
echo json_encode(['success' => false, 'message' => 'Acción no válida']);
exit();
?>