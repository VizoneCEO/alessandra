<?php
require 'back/db_connect.php';
require 'back/log_helper.php';

// Mock session if needed, but registrar_log takes user_id as arg.
// We'll use user_id 1 (Admin usually)
$user_id = 1;
$action = 'TEST_LOGGING';
$desc = 'Verificación de funcionamiento de logs';
$details = ['test' => 'true', 'timestamp' => time()];

echo "Testing registrar_log...\n";
try {
    registrar_log($conn, $user_id, $action, $desc, $details);
    echo "registrar_log called successfully.\n";

    // Convert array detail to json string for search
    $json_detail = json_encode($details);
    // The previous logging concatenated desc and details.
    // detail = "Verificación... | {"test":"true"...}"

    // Verify insertion
    $stmt = $conn->prepare("SELECT id, detalle FROM Log_Actividades WHERE usuario_id = ? AND accion = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo "Log found in DB! ID: " . $row['id'] . "\n";
        echo "Detalle: " . $row['detalle'] . "\n";
    } else {
        echo "ERROR: Log NOT found in DB.\n";
    }

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
?>