<?php
require 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM Usuarios");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>