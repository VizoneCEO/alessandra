<?php
session_start();
require 'db_connect.php';

// Set JSON header for all responses
header('Content-Type: application/json');

// --- Security Check ---
// Only Admin (1) can access logs.
// Only Admin (1) and Ayudante (6) can access logs.
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil_id'] != 1 && $_SESSION['perfil_id'] != 6)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

function jsonResponse($success, $message, $data = [])
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch_logs') {
    // 1. Get Filters
    $user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? intval($_POST['user_id']) : null;
    $date_start = $_POST['date_start'] ?? null;
    $date_end = $_POST['date_end'] ?? null;
    $action_type = $_POST['action_type'] ?? null;
    $search = $_POST['search'] ?? null;

    // 2. Pagination
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
    if ($page < 1)
        $page = 1;

    // 3. Build Query - First, we construct the WHERE clause
    $whereSQL = "WHERE 1=1";
    $types = "";
    $params = [];

    if ($user_id) {
        $whereSQL .= " AND l.usuario_id = ?";
        $types .= "i";
        $params[] = $user_id;
    }

    if ($date_start) {
        $whereSQL .= " AND DATE(l.fecha) >= ?";
        $types .= "s";
        $params[] = $date_start;
    }

    if ($date_end) {
        $whereSQL .= " AND DATE(l.fecha) <= ?";
        $types .= "s";
        $params[] = $date_end;
    }

    if ($action_type && $action_type !== 'all') {
        $whereSQL .= " AND l.accion = ?";
        $types .= "s";
        $params[] = $action_type;
    }

    if ($search) {
        // Search in Detalle or Username
        $whereSQL .= " AND (l.detalle LIKE ? OR u.nombre_completo LIKE ?)";
        $types .= "ss";
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
    }

    // 4. Count Total Records (for Pagination)
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM Log_Actividades l 
                 LEFT JOIN Usuarios u ON l.usuario_id = u.id 
                 $whereSQL";

    $stmtCount = $conn->prepare($sqlCount);
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    $totalRecords = $resCount->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $limit);
    $stmtCount->close();

    // 5. Fetch Actual Data
    $offset = ($page - 1) * $limit;
    $sqlData = "SELECT l.*, u.nombre_completo as usuario_nombre, u.perfil_id 
                FROM Log_Actividades l 
                LEFT JOIN Usuarios u ON l.usuario_id = u.id 
                $whereSQL 
                ORDER BY l.fecha DESC 
                LIMIT ? OFFSET ?";

    // Add limit/offset to params
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sqlData);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $logs = [];
    while ($r = $res->fetch_assoc()) {
        $logs[] = $r;
    }
    $stmt->close();

    jsonResponse(true, 'Logs loaded', [
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ]
    ]);

} elseif ($action === 'fetch_users') {
    // Return list of users for dropdown
    $res = $conn->query("SELECT id, nombre_completo, perfil_id FROM Usuarios ORDER BY nombre_completo ASC");
    $users = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $users[] = $r;
        }
    }
    jsonResponse(true, 'Users loaded', $users);

} elseif ($action === 'fetch_actions') {
    // Return distinct actions for dropdown
    $res = $conn->query("SELECT DISTINCT accion FROM Log_Actividades ORDER BY accion ASC");
    $actions = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $actions[] = $r['accion'];
        }
    }
    jsonResponse(true, 'Actions loaded', $actions);

} else {
    jsonResponse(false, 'Invalid Action');
}
?>