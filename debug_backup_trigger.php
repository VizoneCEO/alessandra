<?php
// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['perfil_id'] = 1;

// Mock POST
$_POST['action'] = 'trigger_backup';

// Run backup_actions.php
// We expect JSON output.
require 'back/backup_actions.php';
?>