<?php
require 'db_connect.php';

echo "<h2>Iniciando migración de seguridad y estructura...</h2>";

// 1. Insertar perfil 'Ayudante Administrativo' (id = 6)
$sql_perfil = "INSERT IGNORE INTO Perfiles (id, nombre_perfil) VALUES (6, 'Ayudante Administrativo')";
if ($conn->query($sql_perfil) === TRUE) {
    echo "<p>✅ Perfil 'Ayudante Administrativo' (ID 6) verificado/insertado.</p>";
} else {
    echo "<p>❌ Error al insertar perfil: " . $conn->error . "</p>";
}

// 2. Agregar columna 'matricula' a la tabla Usuarios
// Primero verificamos si existe
$result = $conn->query("SHOW COLUMNS FROM Usuarios LIKE 'matricula'");
if ($result->num_rows == 0) {
    $sql_alter = "ALTER TABLE Usuarios ADD COLUMN matricula VARCHAR(20) AFTER id";
    if ($conn->query($sql_alter) === TRUE) {
        echo "<p>✅ Columna 'matricula' agregada exitosamente.</p>";
    } else {
        echo "<p>❌ Error al agregar columna 'matricula': " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ La columna 'matricula' ya existe.</p>";
}

// 3. Eliminar usuario con CURP 'xxxx0000xxxxxx'
$curp_to_delete = 'xxxx0000xxxxxx';
$stmt_del = $conn->prepare("DELETE FROM Usuarios WHERE curp = ?");
$stmt_del->bind_param("s", $curp_to_delete);
if ($stmt_del->execute()) {
    if ($stmt_del->affected_rows > 0) {
        echo "<p>✅ Usuario con CURP '$curp_to_delete' eliminado físicamente.</p>";
    } else {
        echo "<p>ℹ️ No se encontró usuario con CURP '$curp_to_delete' para eliminar.</p>";
    }
} else {
    echo "<p>❌ Error al eliminar usuario: " . $stmt_del->error . "</p>";
}
$stmt_del->close();

echo "<h3>Migración completada.</h3>";
$conn->close();
?>