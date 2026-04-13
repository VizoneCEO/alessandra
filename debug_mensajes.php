<?php
require 'back/db_connect.php';

// Check table structure
$sql = "DESCRIBE Mensajes";
$result = $conn->query($sql);
echo "Structure of Mensajes:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

// Check some data
$sql = "SELECT * FROM Mensajes ORDER BY fecha DESC LIMIT 5";
$result = $conn->query($sql);
echo "\nLast 5 messages:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>