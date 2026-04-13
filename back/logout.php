<?php
// Iniciamos la sesión para poder acceder a ella
session_start();

// Destruimos todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Log logout action if user was logged in
if (isset($_SESSION['user_id'])) {
    require 'db_connect.php';
    require_once 'log_helper.php';
    registrar_log($conn, $_SESSION['user_id'], 'LOGOUT', 'Cierre de sesión', "Usuario ID: " . $_SESSION['user_id']);
    $conn->close();
}

// Finalmente, destruimos la sesión
session_destroy();

// Redirigimos al usuario a la página de login
header("Location: ../index.php");
exit();
?>