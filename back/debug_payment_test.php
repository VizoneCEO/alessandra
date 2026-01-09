<?php
// Mock POST data
$_POST['action'] = 'pay_charge';
$_POST['charge_id'] = 0; // Will find one
$_POST['metodo'] = 'Efectivo';
$_POST['referencia'] = 'TEST_REF';
$_POST['monto'] = '500';

require 'db_connect.php';

// Find a pending charge
$res = $conn->query("SELECT id, total, monto_pagado FROM finanzas_cargos WHERE estado != 'Pagado' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $_POST['charge_id'] = $row['id'];
    echo "Testing payment for Charge ID: " . $row['id'] . "\n";
    echo "Current Total: " . $row['total'] . "\n";
    echo "Current Paid: " . $row['monto_pagado'] . "\n";
} else {
    die("No pending charges found to test.");
}

// Capture output
ob_start();
require 'admin_actions_finanzas.php';
$output = ob_get_clean();

echo "\n--- OUTPUT ---\n";
echo $output;
echo "\n--------------\n";
?>