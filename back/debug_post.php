<?php
header('Content-Type: application/json');
$input = file_get_contents('php://input');
echo json_encode([
    'success' => false, // Fail purpose 
    'message' => 'DEBUG POST',
    'post' => $_POST,
    'input_raw' => $input,
    'files' => $_FILES
]);
?>