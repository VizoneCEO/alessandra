<?php
require 'back/db_connect.php';
$id = 750;
$sql = "SELECT * FROM Mensajes WHERE id = $id";
$res = $conn->query($sql);
print_r($res->fetch_assoc());
?>