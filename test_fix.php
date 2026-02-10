<?php
// Test Script for External Staff Ticket Recognition
require 'back/db_connect.php';

// 1. Find a ticket belonging to the external user (CURP: EXTERNO0000000000)
$sql = "SELECT b.id 
        FROM finanzas_boletos b
        JOIN Usuarios u ON b.alumno_id = u.id
        WHERE u.curp = 'EXTERNO0000000000'
        ORDER BY b.id DESC LIMIT 1";

$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    $ticketId = $row['id'];
    echo "Found Ticket ID: $ticketId for External Staff.\n";

    // 2. Prepare POST data mimicking the scanner
    $postData = [
        'ticket_data' => json_encode(['id' => $ticketId]),
        'curp' => '' // Not needed when ticket_data is present
    ];

    // 3. Make request to validate_access.php
    // Since we are running cli, we can use curl or simple include override hack?
    // Curl is safer to test actual endpoint logic.

    $url = 'http://localhost/alessandra/alessandra/back/validate_access.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    echo "Response from validate_access.php:\n";
    echo $response . "\n\n";

    $json = json_decode($response, true);

    if ($json && isset($json['is_ticket']) && $json['is_ticket']) {
        $type = $json['data']['tipo_boleto'];
        echo "Detected Ticket Type: " . $type . "\n";

        if ($type === 'STAFF') {
            echo "SUCCESS: Ticket recognized as STAFF.\n";
        } else {
            echo "FAILURE: Ticket recognized as $type (Expected STAFF).\n";
        }
    } else {
        echo "FAILURE: Invalid response or ticket not recognized.\n";
    }

} else {
    echo "No ticket found for EXTERNO0000000000. Cannot verify via existing data.\n";
}
?>