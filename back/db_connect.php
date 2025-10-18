<?php
// Configuración de la base de datos
$db_host = 'localhost'; // O la IP de tu servidor de base de datos
$db_user = 'root';      // Tu usuario de la base de datos
$db_pass = '';          // Tu contraseña de la base de datos
$db_name = 'calificaciones'; // El nombre de tu base de datos

// Crear la conexión
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar si hay errores en la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Opcional: Establecer el juego de caracteres a UTF-8
$conn->set_charset("utf8");
?>