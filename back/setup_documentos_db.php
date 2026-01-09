<?php
require_once 'db_connect.php';

// Crear tabla de Documentos
$sql = "CREATE TABLE IF NOT EXISTS Documentos_Alumno (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    tipo_documento VARCHAR(50) NOT NULL,
    archivo_path VARCHAR(255) NOT NULL,
    estado ENUM('Pendiente', 'Aprobado', 'Rechazado') DEFAULT 'Pendiente',
    mensaje_rechazo TEXT,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumno_id) REFERENCES Usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doc_alumno (alumno_id, tipo_documento)
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla Documentos_Alumno creada o ya existe.<br>";
} else {
    echo "Error creando tabla: " . $conn->error . "<br>";
}
?>