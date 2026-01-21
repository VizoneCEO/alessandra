<?php
session_start();
require 'db_connect.php';

// --- Seguridad ---
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Preparamos la URL de redirección
$redirect_url = "../front/admin/dashboard.php?page=usuarios";

if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        case 'create_user':
            $nombre = trim($_POST['nombre_completo']);
            $curp = trim($_POST['curp']);
            $perfil_id = $_POST['perfil_id'];
            $forma = $_POST['forma'] ?? 'presencial'; // Default to presencial if missing

            if (empty($nombre) || empty($curp) || empty($perfil_id)) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Todos los campos son requeridos.'];
                break;
            }

            $stmt = $conn->prepare("SELECT id FROM Usuarios WHERE curp = ?");
            $stmt->bind_param("s", $curp);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: El CURP ya está registrado.'];
            } else {
                $stmt = $conn->prepare("INSERT INTO Usuarios (nombre_completo, curp, perfil_id, forma, password_hash, estado) VALUES (?, ?, ?, ?, NULL, 'activo')");
                $stmt->bind_param("ssis", $nombre, $curp, $perfil_id, $forma);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Usuario creado exitosamente.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear el usuario: ' . $stmt->error];
                }
            }
            $stmt->close();
            break;

        case 'reset_password':
            $user_id = $_POST['user_id'];
            $stmt = $conn->prepare("UPDATE Usuarios SET password_hash = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Contraseña liberada.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al liberar contraseña.'];
            }
            $stmt->close();
            break;

        // --- ACCIÓN MODIFICADA: Ahora también actualiza el nombre ---
        case 'change_profile':
            $user_id = $_POST['user_id'];
            $new_perfil_id = $_POST['perfil_id'];
            $forma = $_POST['forma'] ?? 'presencial';
            $nombre_completo = trim($_POST['nombre_completo']); // Obtenemos el nombre

            if (empty($nombre_completo)) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: El nombre no puede estar vacío.'];
                break;
            }

            $stmt = $conn->prepare("UPDATE Usuarios SET perfil_id = ?, nombre_completo = ?, forma = ? WHERE id = ?");
            $stmt->bind_param("issi", $new_perfil_id, $nombre_completo, $forma, $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Usuario actualizado correctamente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al actualizar el usuario: ' . $stmt->error];
            }
            $stmt->close();
            break;

        // --- NUEVA ACCIÓN: Cambiar estado (activo/inactivo) ---
        case 'toggle_status':
            $user_id = $_POST['user_id'];
            // Obtenemos el estado actual para invertirlo
            $stmt = $conn->prepare("SELECT estado FROM Usuarios WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_status = $stmt->get_result()->fetch_assoc()['estado'];
            $new_status = ($current_status == 'activo') ? 'inactivo' : 'activo';

            // Actualizamos el estado
            $stmt_update = $conn->prepare("UPDATE Usuarios SET estado = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_status, $user_id);
            if ($stmt_update->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Estado del usuario cambiado a ' . $new_status . '.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al cambiar el estado.'];
            }
            $stmt->close();
            $stmt_update->close();
            break;

        // --- DEPOSIT ACCOUNTS ---
        case 'create_account':
            $banco = trim($_POST['banco']);
            $titular = trim($_POST['titular']);
            $clabe = trim($_POST['clabe']);
            $cuenta = trim($_POST['numero_cuenta']);

            if (empty($banco) || empty($titular)) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Banco y Titular son obligatorios.'];
                break;
            }

            $stmt = $conn->prepare("INSERT INTO Finanzas_Cuentas (banco, titular, clabe, numero_cuenta) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $banco, $titular, $clabe, $cuenta);

            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Cuenta de depósito agregada exitosamente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al agregar cuenta: ' . $stmt->error];
            }
            $stmt->close();
            break;

        case 'assign_account':
            $user_id = $_POST['user_id'];
            $account_id = $_POST['account_id']; // Can be 'NULL' string if unassigning, handled below

            if ($account_id === 'NULL' || $account_id === '') {
                $stmt = $conn->prepare("UPDATE Usuarios SET cuenta_deposito_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE Usuarios SET cuenta_deposito_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $account_id, $user_id);
            }

            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Cuenta asignada al alumno correctamente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al asignar cuenta.'];
            }
            $stmt->close();
            break;

        case 'delete_account':
            $account_id = $_POST['account_id'];

            // 1. Double check usage
            $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM Usuarios WHERE cuenta_deposito_id = ?");
            $stmt_check->bind_param("i", $account_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result()->fetch_assoc();
            $users_count = $result['count'];
            $stmt_check->close();

            if ($users_count > 0) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: No se puede eliminar una cuenta que tiene alumnos asignados.'];
            } else {
                // 2. Delete
                $stmt_del = $conn->prepare("DELETE FROM Finanzas_Cuentas WHERE id = ?");
                $stmt_del->bind_param("i", $account_id);
                if ($stmt_del->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Cuenta eliminada exitosamente.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar la cuenta: ' . $stmt_del->error];
                }
                $stmt_del->close();
            }
            break;
    }
}

header("Location: " . $redirect_url);
exit();
?>