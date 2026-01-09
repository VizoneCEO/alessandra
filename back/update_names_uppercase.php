<?php
require 'db_connect.php';

// Update names to uppercase for users with profile_id = 3 (Alumnos) based on common logic, 
// or simpler: just update ALL users if that's preferred, but user said "alumnos".
// We will assume perfil_id 3 is alumno as seen in other files, 
// OR we can join with Perfiles to be sure.

$sql = "UPDATE Usuarios u 
        JOIN Perfiles p ON u.perfil_id = p.id
        SET u.nombre_completo = UPPER(u.nombre_completo) 
        WHERE p.nombre_perfil LIKE '%mlumno%' OR u.perfil_id = 3";

if ($conn->query($sql) === TRUE) {
    echo "Nombres actualizados a mayúsculas correctamente. Filas afectadas: " . $conn->affected_rows;
} else {
    echo "Error actualizando nombres: " . $conn->error;
}

$conn->close();
?>