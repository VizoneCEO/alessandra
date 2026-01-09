<?php
// Mock POST request
$_POST['action'] = 'fetch_history';
$_POST['charge_id'] = 66; // Replace with a valid ID from the database if known, otherwise use 0

// Include the action file
require 'admin_actions_finanzas.php';
?>