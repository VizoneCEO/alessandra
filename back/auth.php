<?php
// Iniciamos la sesión para poder guardar variables de usuario
session_start();

// Incluimos la conexión a la base de datos
require 'db_connect.php';

// Solo procedemos si los datos se envían por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Obtenemos y limpiamos los datos del formulario para seguridad
    $curp = $conn->real_escape_string($_POST['curp']);
    $password = $_POST['password'];

    // Preparamos la consulta SQL para evitar inyecciones
    // ===== MODIFICACIÓN 1: Añadimos 'estado' a la consulta =====
    $sql = "SELECT id, password_hash, perfil_id, nombre_completo, estado FROM Usuarios WHERE curp = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $curp);
    $stmt->execute();
    $result = $stmt->get_result();

    // Verificamos si encontramos un usuario
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verificamos que la contraseña sea correcta usando password_verify
        if (password_verify($password, $user['password_hash'])) {

            // ===== MODIFICACIÓN 2: Verificamos si el usuario está ACTIVO =====
            if ($user['estado'] == 'activo') {
                // ¡Login correcto! Guardamos los datos del usuario en la sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre_completo'];
                $_SESSION['perfil_id'] = $user['perfil_id'];

                // Usamos un SWITCH para redirigir según el perfil_id
                $redirect_url = '';
                switch ($_SESSION['perfil_id']) {
                    case 1: // Administrador
                        $redirect_url = '../front/admin/dashboard.php';
                        break;
                    case 2: // Profesor
                        $redirect_url = '../front/profesor/dashboard.php';
                        break;
                    case 3: // Alumno
                        $redirect_url = '../front/alumno/dashboard.php';
                        break;
                    default:
                        // Si por alguna razón no tiene un perfil válido, lo mandamos al index
                        $redirect_url = '../index.php';
                        $_SESSION['login_error'] = "Perfil de usuario no reconocido.";
                        break;
                }
                header("Location: " . $redirect_url);
                exit();

            } else {
                // ===== Si el usuario está INACTIVO, mostramos error =====
                $_SESSION['login_error'] = "Este usuario se encuentra inactivo. Contacta al administrador.";
                header("Location: ../index.php");
                exit();
            }
            // ===== FIN DE LA MODIFICACIÓN =====

        } else {
            // Contraseña incorrecta
            $_SESSION['login_error'] = "La contraseña es incorrecta.";
            header("Location: ../index.php");
            exit();
        }
    } else {
        // Usuario no encontrado
        $_SESSION['login_error'] = "El usuario con ese CURP no fue encontrado.";
        header("Location: ../index.php");
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    // Redirigimos si se intenta acceder al archivo directamente
    header("Location: ../index.php");
    exit();
}
?>