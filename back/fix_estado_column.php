<?php
require 'db_connect.php';
// Alter table to allow longer status strings
if ($conn->query("ALTER TABLE finanzas_cargos MODIFY COLUMN estado VARCHAR(50) DEFAULT 'Pago Pendiente'")) {
    echo "Column 'estado' updated successfully.";
} else {
    echo "Error updating column: " . $conn->error;
}
?>