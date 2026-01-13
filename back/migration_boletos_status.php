<?php
require 'db_connect.php';

echo "<h2>Migrating finanzas_boletos</h2>";

$sql = "ALTER TABLE finanzas_boletos ADD COLUMN estado_uso ENUM('Disponible', 'Usado') DEFAULT 'Disponible' AFTER folio_asiento";

if ($conn->query($sql)) {
    echo "Column 'estado_uso' added successfully.";
} else {
    echo "Error adding column: " . $conn->error;
}
?>