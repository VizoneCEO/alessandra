<?php
// back/migration_logs_schema.php
require 'db_connect.php';

echo "<h2>Checking Log_Actividades Schema...</h2>";

// Check if Log_Actividades exists
$checkTable = $conn->query("SHOW TABLES LIKE 'Log_Actividades'");
if ($checkTable->num_rows == 0) {
    // Create Table if not exists (Basic schema)
    $sql = "CREATE TABLE Log_Actividades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        accion VARCHAR(50),
        descripcion TEXT,
        detalles TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45)
    )";
    if ($conn->query($sql)) {
        echo "<p>✅ Table 'Log_Actividades' created.</p>";
    } else {
        echo "<p>❌ Error creating table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ Table 'Log_Actividades' exists.</p>";
}

// Check for ip_address column
$checkCol = $conn->query("SHOW COLUMNS FROM Log_Actividades LIKE 'ip_address'");
if ($checkCol->num_rows == 0) {
    $sqlAlter = "ALTER TABLE Log_Actividades ADD COLUMN ip_address VARCHAR(45) AFTER fecha";
    if ($conn->query($sqlAlter)) {
        echo "<p>✅ Column 'ip_address' added to Log_Actividades.</p>";
    } else {
        echo "<p>❌ Error adding 'ip_address': " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ Column 'ip_address' already exists.</p>";
}

// Check for detalles column (just in case)
$checkColDet = $conn->query("SHOW COLUMNS FROM Log_Actividades LIKE 'detalles'");
if ($checkColDet->num_rows == 0) {
    $sqlAlterDet = "ALTER TABLE Log_Actividades ADD COLUMN detalles TEXT AFTER descripcion";
    if ($conn->query($sqlAlterDet)) {
        echo "<p>✅ Column 'detalles' added to Log_Actividades.</p>";
    } else {
        echo "<p>❌ Error adding 'detalles': " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ Column 'detalles' already exists.</p>";
}

echo "<h3>Schema check completed.</h3>";
?>