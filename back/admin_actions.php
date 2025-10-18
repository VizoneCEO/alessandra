<?php
session_start();
require 'db_connect.php';

// --- Seguridad: Solo administradores pueden ejecutar estas acciones ---
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    // Si no es admin, no hacemos nada y lo sacamos.
    header("Location: ../index.php");
    exit();
}

// Verificamos qué acción se quiere realizar
if (isset($_POST['action'])) {

    // Usamos un switch para manejar las diferentes acciones
    switch ($_POST['action']) {

        // --- ACCIÓN: CREAR UN NUEVO USUARIO ---
        case 'create_user':
            $nombre = $_POST['nombre_completo'];
            $curp = $_POST['curp'];
            $perfil_id = $_POST['perfil_id'];

            // Validar que el CURP no exista ya
            $stmt = $conn->prepare("SELECT id FROM Usuarios WHERE curp = ?");
            $stmt->bind_param("s", $curp);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: El CURP ya está registrado.'];
            } else {
                // Insertar el nuevo usuario con contraseña NULL
                $stmt = $conn->prepare("INSERT INTO Usuarios (nombre_completo, curp, perfil_id, password_hash) VALUES (?, ?, ?, NULL)");
                $stmt->bind_param("ssi", $nombre, $curp, $perfil_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Usuario creado exitosamente. Ahora puede registrar su contraseña.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear el usuario.'];
                }
            }
            $stmt->close();
            break;

        // --- ACCIÓN: LIBERAR CONTRASEÑA ---
        case 'reset_password':
            $user_id = $_POST['user_id'];
            $stmt = $conn->prepare("UPDATE Usuarios SET password_hash = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Contraseña liberada. El usuario ahora puede crear una nueva.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al liberar la contraseña.'];
            }
            $stmt->close();
            break;

        // --- ACCIÓN: CAMBIAR PERFIL ---
        case 'change_profile':
            $user_id = $_POST['user_id'];
            $new_perfil_id = $_POST['perfil_id'];
            $stmt = $conn->prepare("UPDATE Usuarios SET perfil_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_perfil_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Perfil del usuario actualizado correctamente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al actualizar el perfil.'];
            }
            $stmt->close();
            break;
    }
}

// Al terminar, siempre redirigimos de vuelta a la página de usuarios
header("Location: ../front/admin/dashboard.php?page=usuarios");
exit();
?>