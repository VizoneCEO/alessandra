<?php
require 'db_connect.php';

// 1. Insertar Perfil 'seguridad' ID = 4
$sql_perfil = "INSERT IGNORE INTO Perfiles (id, nombre_perfil) VALUES (4, 'seguridad')";
if ($conn->query($sql_perfil) === TRUE) {
    echo "Perfil 'Seguridad' (ID 4) verificado/creado.<br>";
} else {
    echo "Error perfil: " . $conn->error . "<br>";
}

// 2. Crear un usuario de prueba (Opcional, pero util para login inmediato)
// CURP: SEGURIDAD01, Pass: 123456
$pass = password_hash('123456', PASSWORD_DEFAULT);
$sql_user = "INSERT IGNORE INTO Usuarios (nombre_completo, curp, password_hash, perfil_id, estado)
             VALUES ('Guardia Acceso', 'SEGURIDAD01', '$pass', 4, 'Activo')";

if ($conn->query($sql_user) === TRUE) {
    echo "Usuario 'Guardia Acceso' (SEGURIDAD01) creado. Pass: 123456<br>";
} else {
    echo "Info usuario: " . $conn->error . "<br>";
}
?>