<?php
require 'db_connect.php';
echo "--- Tables ---\n";
$res = $conn->query("SHOW TABLES LIKE 'Finanzas_Cuentas'");
if ($res->num_rows > 0)
    echo "Finanzas_Cuentas exists\n";
else
    echo "Finanzas_Cuentas MISSING\n";

echo "\n--- Usuarios Columns ---\n";
$res = $conn->query("SHOW COLUMNS FROM Usuarios LIKE 'cuenta_deposito_id'");
if ($res->num_rows > 0)
    echo "cuenta_deposito_id exists\n";
else
    echo "cuenta_deposito_id MISSING\n";
?>