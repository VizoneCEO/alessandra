<?php
// Mock session
session_start();
$_SESSION['user_id'] = 1; // Assuming admin ID 1

// Mock POST data
$_POST['action_type'] = 'get_batch_readers';
$_POST['batch_id'] = 'msg_699742a9558548.80769064';

// Capture output
ob_start();
require 'back/mensajes_actions.php';
$output = ob_get_clean();

echo $output;
?>