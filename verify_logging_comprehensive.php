<?php
// verify_logging_comprehensive.php
require 'back/db_connect.php';
require 'back/log_helper.php';

echo "<h2>Verifying Comprehensive Logging</h2>";

// 1. Simulate an Action Log
$test_user_id = 1; // Assuming Admin exists
$action = 'TEST_LOG_VERIFICATION';
$desc = 'Verifying if log is recorded with IP';
$details = 'Details of verification: ' . date('Y-m-d H:i:s');

registrar_log($conn, $test_user_id, $action, $desc, $details);

echo "<p>✅ Log inserted via `registrar_log`.</p>";

// 2. Fetch Last 5 Logs
$sql = "SELECT * FROM Log_Actividades ORDER BY id DESC LIMIT 5";
$result = $conn->query($sql);

echo "<h3>Last 5 Logs:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>User</th><th>Action</th><th>Desc</th><th>Details</th><th>IP</th><th>Date</th></tr>";

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['usuario_id']}</td>";
        echo "<td>{$row['accion']}</td>";
        echo "<td>{$row['descripcion']}</td>";
        echo "<td>" . ($row['detalles'] ?? '-') . "</td>";
        echo "<td>" . ($row['ip_address'] ?? 'NULL') . "</td>";
        echo "<td>{$row['fecha']}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7'>Error fetching logs: " . $conn->error . "</td></tr>";
}
echo "</table>";
?>