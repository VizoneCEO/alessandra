<?php
// verify_logs.php
session_start();
// Mock Admin Session
$_SESSION['user_id'] = 1;
$_SESSION['perfil_id'] = 1;

// Helper to simulate POST request to local file
function test_action($action, $params = [])
{
    $_POST = array_merge(['action' => $action], $params);
    ob_start();
    include 'back/admin_actions_logs.php';
    $output = ob_get_clean();
    $json = json_decode($output, true);

    echo "Action: $action\n";
    if ($json && $json['success']) {
        echo "[PASS] Success. ";
        if ($action == 'fetch_logs') {
            echo "Count: " . count($json['data']['logs']) . "\n";
            if (count($json['data']['logs']) > 0) {
                echo "First Log: " . $json['data']['logs'][0]['descripcion'] . "\n";
            }
        } elseif ($action == 'fetch_users') {
            echo "Users: " . count($json['data']) . "\n";
        } elseif ($action == 'fetch_actions') {
            echo "Actions: " . count($json['data']) . "\n";
        }
    } else {
        echo "[FAIL] " . ($json['message'] ?? 'Invalid JSON') . "\n";
        echo "Raw Output: " . substr($output, 0, 100) . "...\n";
    }
    echo "------------------------------------------------\n";
}

echo "Testing Admin Logs Backend...\n";
echo "------------------------------------------------\n";

test_action('fetch_users');
test_action('fetch_actions');
test_action('fetch_logs', ['page' => 1, 'limit' => 5]);
test_action('fetch_logs', ['search' => 'nonexistent_search_term_12345']);

?>