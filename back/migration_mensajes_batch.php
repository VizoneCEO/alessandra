<?php
require 'db_connect.php';

// Add batch_id column if it doesn't exist
$check_col = $conn->query("SHOW COLUMNS FROM Mensajes LIKE 'batch_id'");
if ($check_col->num_rows == 0) {
    // Column doesn't exist, add it
    $sql = "ALTER TABLE Mensajes ADD COLUMN batch_id VARCHAR(50) NULL AFTER id";
    if ($conn->query($sql) === TRUE) {
        echo "Columna 'batch_id' agregada exitosamente.<br>";

        // Optional: Backfill existing messages with a unique batch_id per row or group?
        // Let's just give each existing row a unique batch? Or leave NULL.
        // Leaving NULL is fine for now, they will show as "Sin Agrupar".
    } else {
        echo "Error agregando columna: " . $conn->error;
    }
} else {
    echo "La columna 'batch_id' ya existe.<br>";
}

// Ensure deleted_at is there (it should be from previous step)
?>