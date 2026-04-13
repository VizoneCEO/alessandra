<?php
require 'db_connect.php';

// --- Create Mensajes Table v2 ---
$table_name = "Mensajes";

// Check if table exists and drop it to ensure clean state as per requirements
$check = $conn->query("SHOW TABLES LIKE '$table_name'");
if ($check->num_rows > 0) {
    $conn->query("DROP TABLE $table_name");
    echo "Tabla '$table_name' anterior eliminada.<br>";
}

$sql = "CREATE TABLE $table_name (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remitente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    asunto VARCHAR(255) NOT NULL,
    cuerpo TEXT NOT NULL,
    leido TINYINT(1) DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (remitente_id) REFERENCES Usuarios(id),
    FOREIGN KEY (destinatario_id) REFERENCES Usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Tabla '$table_name' creada exitosamente con la nueva estructura.<br>";
} else {
    echo "Error creando tabla '$table_name': " . $conn->error . "<br>";
}

echo "Migración de mensajería v2 completada.";
?>