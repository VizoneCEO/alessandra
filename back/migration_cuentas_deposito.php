<?php
require 'db_connect.php';

// 1. Create Finanzas_Cuentas table
$sql_table = "CREATE TABLE IF NOT EXISTS Finanzas_Cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    banco VARCHAR(100) NOT NULL,
    titular VARCHAR(150) NOT NULL,
    clabe VARCHAR(20),
    numero_cuenta VARCHAR(20),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_table)) {
    echo "Table Finanzas_Cuentas created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// 2. Add column to Usuarios
$sql_alter = "ALTER TABLE Usuarios ADD COLUMN cuenta_deposito_id INT DEFAULT NULL";
if ($conn->query($sql_alter)) {
    echo "Column cuenta_deposito_id added to Usuarios.\n";
} else {
    // Check if duplicate column error (ignore)
    if ($conn->errno == 1060) {
        echo "Column cuenta_deposito_id already exists.\n";
    } else {
        echo "Error altering table: " . $conn->error . "\n";
    }
}
?>