<?php
require 'db_connect.php';
echo "<h2>Columnas de finanzas_boletos</h2>";
$res = $conn->query("SHOW COLUMNS FROM finanzas_boletos");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo $r['Field'] . " - " . $r['Type'] . "<br>";
    }
} else {
    echo "Error: " . $conn->error;
}
?>