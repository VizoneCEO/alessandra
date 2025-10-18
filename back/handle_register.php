<?php
session_start();
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $curp = $conn->real_escape_string($_POST['curp']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Validar que las contraseñas coincidan
    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Las contraseñas no coinciden.";
        header("Location: ../register.php");
        exit();
    }

    // 2. Verificar si el usuario con ese CURP existe
    $sql_check = "SELECT id, password_hash FROM Usuarios WHERE curp = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $curp);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['register_error'] = "El usuario con este CURP no está registrado en el sistema.";
        header("Location: ../register.php");
        exit();
    }

    $user = $result->fetch_assoc();

    // 3. Verificar que el usuario no tenga ya una contraseña
    if ($user['password_hash'] !== null) {
        $_SESSION['register_error'] = "Este usuario ya tiene una contraseña. Si la olvidaste, contacta al administrador.";
        header("Location: ../register.php");
        exit();
    }

    // 4. Todo correcto: Hashear la nueva contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // 5. Actualizar la base de datos con la nueva contraseña
    $sql_update = "UPDATE Usuarios SET password_hash = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $password_hash, $user['id']);

    if ($stmt_update->execute()) {
        $_SESSION['register_success'] = "¡Contraseña creada con éxito! Ahora puedes iniciar sesión.";
        header("Location: ../index.php"); // Lo mandamos al login con mensaje de éxito
        exit();
    } else {
        $_SESSION['register_error'] = "Error al guardar la contraseña. Inténtalo de nuevo.";
        header("Location: ../register.php");
        exit();
    }

    $stmt_check->close();
    $stmt_update->close();
    $conn->close();

} else {
    header("Location: ../index.php");
    exit();
}
?>