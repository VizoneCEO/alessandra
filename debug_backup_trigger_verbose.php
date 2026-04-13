<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['perfil_id'] = 1;

// Mock POST
$_POST['action'] = 'trigger_backup';

echo "Starting debug...\n";
require 'back/backup_actions.php';
echo "\nFinished debug.\n";
?>