<?php
require 'db_connect.php';

echo "<h2>Iniciando migración de Audit Log y Soft Delete...</h2>";

// 1. Crear tabla Log_Actividades
$sql_log = "CREATE TABLE IF NOT EXISTS Log_Actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle TEXT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id)
)";

if ($conn->query($sql_log) === TRUE) {
    echo "<p>✅ Tabla 'Log_Actividades' verificada/creada.</p>";
} else {
    echo "<p>❌ Error al crear tabla 'Log_Actividades': " . $conn->error . "</p>";
}

// 2. Agregar columna deleted_at
$tables = [
    'Usuarios',
    'Inscripciones',
    'finanzas_cargos',
    'finanzas_boletos',
    'Finanzas_Cuentas',
    'finanzas_eventos'
];

foreach ($tables as $table) {
    // Verificar si la columna ya existe
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE 'deleted_at'");
    if ($result->num_rows == 0) {
        $sql_alter = "ALTER TABLE $table ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL";
        if ($conn->query($sql_alter) === TRUE) {
            echo "<p>✅ Columna 'deleted_at' agregada a la tabla '$table'.</p>";
        } else {
            echo "<p>❌ Error al agregar columna a '$table': " . $conn->error . "</p>";
        }
    } else {
        echo "<p>ℹ️ La columna 'deleted_at' ya existe en '$table'.</p>";
    }
}

echo "<h3>Migración completada.</h3>";
$conn->close();
?>