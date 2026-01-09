<?php
require 'db_connect.php';

// Table 1: Finanzas Asignaciones (Setup de Alumnos)
$sql_asignaciones = "CREATE TABLE IF NOT EXISTS finanzas_asignaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    colegiatura_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inscripcion_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    beca_monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_alumno (alumno_id),
    FOREIGN KEY (alumno_id) REFERENCES Usuarios(id) ON DELETE CASCADE
)";

// Table 2: Finanzas Cargos (Cobranza)
$sql_cargos = "CREATE TABLE IF NOT EXISTS finanzas_cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumno_id INT NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    monto_original DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    beca_aplicada DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    recargos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    recargos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) GENERATED ALWAYS AS (monto_original + recargos) STORED,
    estado ENUM('Al corriente', 'Pago Pendiente', 'Vencido', 'Pagado', 'Cancelado') NOT NULL DEFAULT 'Pago Pendiente',
    fecha_vencimiento DATE,
    fecha_pago DATETIME NULL,
    notas_ajuste TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumno_id) REFERENCES Usuarios(id) ON DELETE CASCADE
)";

if ($conn->query($sql_asignaciones) === TRUE) {
    echo "Tabla 'finanzas_asignaciones' creada o verificada correctamente.<br>";
} else {
    echo "Error creando tabla 'finanzas_asignaciones': " . $conn->error . "<br>";
}

if ($conn->query($sql_cargos) === TRUE) {
    echo "Tabla 'finanzas_cargos' creada o verificada correctamente.<br>";

    // Ensure beca_aplicada column exists (migration for existing tables)
    $check_col = $conn->query("SHOW COLUMNS FROM finanzas_cargos LIKE 'beca_aplicada'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE finanzas_cargos ADD COLUMN beca_aplicada DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto_original");
        echo "Columna 'beca_aplicada' agregada a finanzas_cargos.<br>";
    }

    // Ensure notas_ajuste column exists
    $check_col_notas = $conn->query("SHOW COLUMNS FROM finanzas_cargos LIKE 'notas_ajuste'");
    if ($check_col_notas->num_rows == 0) {
        $conn->query("ALTER TABLE finanzas_cargos ADD COLUMN notas_ajuste TEXT AFTER fecha_pago");
        echo "Columna 'notas_ajuste' agregada a finanzas_cargos.<br>";
    }

    // Ensure updated_at column exists
    $check_col_upd = $conn->query("SHOW COLUMNS FROM finanzas_cargos LIKE 'updated_at'");
    if ($check_col_upd->num_rows == 0) {
        $conn->query("ALTER TABLE finanzas_cargos ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "Columna 'updated_at' agregada a finanzas_cargos.<br>";
    }

} else {
    echo "Error creando tabla 'finanzas_cargos': " . $conn->error . "<br>";
}

// Ensure new payment columns exist
$check_pay = $conn->query("SHOW COLUMNS FROM finanzas_cargos LIKE 'metodo_pago'");
if ($check_pay->num_rows == 0) {
    $conn->query("ALTER TABLE finanzas_cargos ADD COLUMN metodo_pago VARCHAR(50) AFTER fecha_pago");
    $conn->query("ALTER TABLE finanzas_cargos ADD COLUMN referencia_pago VARCHAR(255) AFTER metodo_pago");
    $conn->query("ALTER TABLE finanzas_cargos ADD COLUMN comprobante_url VARCHAR(255) AFTER referencia_pago");
    echo "Columnas de pago agregadas a finanzas_cargos.<br>";
}

// Table 3: Finanzas Historial (Events Timeline)
$sql_historial = "CREATE TABLE IF NOT EXISTS finanzas_cargos_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cargo_id INT NOT NULL,
    tipo_evento ENUM('CREACION', 'AJUSTE', 'PAGO', 'VENCIMIENTO', 'RECORDATORIO', 'OTRO') NOT NULL,
    descripcion TEXT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES finanzas_cargos(id) ON DELETE CASCADE
)";

if ($conn->query($sql_historial) === TRUE) {
    echo "Tabla 'finanzas_cargos_historial' creada o verificada correctamente.<br>";

    // Ensure ENUM includes 'CANCELACION'
    // Running this blindly is usually fine for ENUMs if we just append
    $conn->query("ALTER TABLE finanzas_cargos_historial MODIFY COLUMN tipo_evento ENUM('CREACION', 'AJUSTE', 'PAGO', 'VENCIMIENTO', 'RECORDATORIO', 'CANCELACION', 'OTRO') NOT NULL");
    echo "ENUM de historial actualizado.<br>";

} else {
    echo "Error creando tabla 'finanzas_cargos_historial': " . $conn->error . "<br>";
}

$conn->close();
?>