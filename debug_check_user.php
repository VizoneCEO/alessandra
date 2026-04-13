<?php
require 'back/db_connect.php';
$id = 1;
$sql = "SELECT id, nombre_completo FROM Usuarios WHERE id = $id";
$res = $conn->query($sql);
if ($res) {
    print_r($res->fetch_assoc());
} else {
    echo "Query failed";
}
?>