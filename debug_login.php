<?php
session_start();
// Simulate Admin Login
$_SESSION['user_id'] = 1; // Assuming ID 1 is admin
$_SESSION['perfil_id'] = 1; // Admin profile
$_SESSION['user_name'] = 'Admin Test';

echo "Session set. <a href='front/admin/dashboard.php?page=mensajes'>Go to Dashboard</a>";
header("Location: front/admin/dashboard.php?page=mensajes");
exit();
?>