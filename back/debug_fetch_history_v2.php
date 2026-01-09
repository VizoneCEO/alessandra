<?php
require 'db_connect.php';

// 1. Get a valid charge ID
$res = $conn->query("SELECT id FROM finanzas_cargos LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $valid_id = $row['id'];
    echo "Testing with Charge ID: $valid_id\n";

    // 2. Simulate Request
    $_POST['action'] = 'fetch_history';
    $_POST['charge_id'] = $valid_id;

    // We cannot just include the file because it exits.
    // Instead, let's replicate the logic or capture the output.
    ob_start();
    include 'admin_actions_finanzas.php';
    $output = ob_get_clean();
    echo "Output: " . $output . "\n";

} else {
    echo "No charges found in DB.\n";
}
?>