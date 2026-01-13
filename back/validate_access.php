<?php
require 'db_connect.php';
header('Content-Type: application/json');

$input = $_POST['curp'] ?? '';
$ticket_json = $_POST['ticket_data'] ?? '';

// --- VALIDATE TICKET LOGIC ---
if (!empty($ticket_json)) {
    $data = json_decode($ticket_json, true);
    if (!$data || !isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Código QR inválido (Formato incorrecto).']);
        exit();
    }

    $ticket_id = intval($data['id']);

    // Fetch Ticket + Event + Student
    // Also check if Event is today? Optional.
    $sql = "SELECT b.*, e.nombre as evento, e.fecha as fecha_evento, u.nombre_completo, u.curp, u.estado as status_alumno
            FROM finanzas_boletos b 
            JOIN finanzas_eventos e ON b.evento_id = e.id 
            JOIN Usuarios u ON b.alumno_id = u.id 
            WHERE b.id = $ticket_id LIMIT 1";

    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) {
        $photo = null;
        // Fetch User Photo
        $uid = $row['alumno_id'];
        $res_pic = $conn->query("SELECT archivo_path FROM Documentos_Alumno WHERE alumno_id = $uid AND tipo_documento = 'Fotografía de Perfil' ORDER BY id DESC LIMIT 1");
        if ($p = $res_pic->fetch_assoc())
            $photo = $p['archivo_path'];

        // Validation Rules
        if ($row['estado_uso'] == 'Usado') {
            echo json_encode([
                'success' => false,
                'message' => 'Este boleto YA FUE UTILIZADO.',
                'student' => [
                    'nombre' => $row['nombre_completo'],
                    'foto' => $photo,
                    'vigencia' => 'Folio #' . str_pad($row['folio_asiento'], 4, '0', STR_PAD_LEFT),
                    'status_text' => 'USADO',
                    'evento' => $row['evento'], // Added
                    'folio_display' => str_pad($row['folio_asiento'], 4, '0', STR_PAD_LEFT) // Added
                ]
            ]);
            exit();
        }

        // Success - Mark as Used? 
        // Ideally we should have a "Check-in" button confirmation or auto-checkin.
        // For security speed, auto-checkin is common.
        // Let's Auto-Checkin for now.
        $conn->query("UPDATE finanzas_boletos SET estado_uso = 'Usado' WHERE id = $ticket_id");

        echo json_encode([
            'success' => true,
            'message' => 'Acceso Permitido',
            'is_ticket' => true,
            'data' => [
                'evento' => $row['evento'],
                'fecha' => date('d M Y', strtotime($row['fecha_evento'])),
                'folio' => str_pad($row['folio_asiento'], 4, '0', STR_PAD_LEFT),
                'alumno' => $row['nombre_completo'],
                'foto' => $photo,
                'status' => 'CHECK-IN EXITOSO'
            ],
            'student' => [
                'nombre' => $row['nombre_completo'],
                'foto' => $photo,
                'vigencia' => 'Folio #' . str_pad($row['folio_asiento'], 4, '0', STR_PAD_LEFT),
                'status_text' => 'BOLETO OK'
            ]
        ]);
        exit();

    } else {
        echo json_encode(['success' => false, 'message' => 'Boleto no encontrado en el sistema.']);
        exit();
    }
}

// --- EXISTING CURP LOGIC ---
if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'Entrada vacía.']);
    exit();
}

$input = $conn->real_escape_string($input);

// 1. Search User (By CURP OR Name)
// Priority: Exact CURP -> Similar Name
$sql = "SELECT id, nombre_completo, curp, estado FROM Usuarios WHERE (curp = '$input' OR nombre_completo LIKE '%$input%') AND perfil_id = 3";
$res = $conn->query($sql);

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Alumno no encontrado.']);
    exit();
} elseif ($res->num_rows > 1) {
    // MULTIPLE MATCHES - Return list
    $candidates = [];
    while ($row = $res->fetch_assoc()) {
        $candidates[] = [
            'nombre' => $row['nombre_completo'],
            'curp' => $row['curp'],
            'estado' => $row['estado']
        ];
    }
    echo json_encode(['success' => false, 'match_type' => 'multiple', 'candidates' => $candidates]);
    exit();
}

// Single match found
$user = $res->fetch_assoc();
$alumno_id = $user['id'];

// --- EVENT MODE: MANUAL LOOKUP ---
$mode = $_POST['mode'] ?? 'access';
if ($mode === 'event') {
    // Return ALL 'Disponible' tickets for this user
    $sql_tickets = "SELECT b.id, b.folio_asiento, e.nombre as evento, e.fecha as fecha_evento
                   FROM finanzas_boletos b 
                   JOIN finanzas_eventos e ON b.evento_id = e.id 
                   WHERE b.alumno_id = $alumno_id 
                   AND b.estado_uso = 'Disponible'
                   ORDER BY e.fecha ASC";

    $res_t = $conn->query($sql_tickets);
    $tickets = [];

    while ($row_t = $res_t->fetch_assoc()) {
        $tickets[] = [
            'id' => $row_t['id'],
            'evento' => $row_t['evento'],
            'fecha' => date('d M Y', strtotime($row_t['fecha_evento'])),
            'folio' => str_pad($row_t['folio_asiento'], 4, '0', STR_PAD_LEFT)
        ];
    }

    if (count($tickets) > 0) {
        // Fetch Photo for UI
        $photo = null;
        $res_pic = $conn->query("SELECT archivo_path FROM Documentos_Alumno WHERE alumno_id = $alumno_id AND tipo_documento = 'Fotografía de Perfil' ORDER BY id DESC LIMIT 1");
        if ($p = $res_pic->fetch_assoc())
            $photo = $p['archivo_path'];

        echo json_encode([
            'success' => false, // Not success yet, need selection
            'match_type' => 'ticket_selection',
            'student' => [
                'nombre' => $user['nombre_completo'],
                'foto' => $photo
            ],
            'tickets' => $tickets
        ]);
        exit();
    } else {
        // No ticket found for this student
        echo json_encode([
            'success' => false,
            'message' => 'El alumno ' . $user['nombre_completo'] . ' NO tiene boletos disponibles.'
        ]);
        exit();
    }
}
// --- END EVENT MODE ---

// 2. Check Enrollment Validity
$hoy = date('Y-m-d');
$sql_vig = "SELECT ce.fecha_fin 
            FROM Inscripciones i
            JOIN Clases c ON i.clase_id = c.id
            JOIN Ciclos_Escolares ce ON c.ciclo_id = ce.id
            WHERE i.alumno_id = $alumno_id
            AND ce.fecha_fin >= '$hoy'
            ORDER BY ce.fecha_fin DESC LIMIT 1";
$res_vig = $conn->query($sql_vig);
$is_enrolled = ($res_vig->num_rows > 0);
$vigencia_date = '-';

if ($row_vig = $res_vig->fetch_assoc()) {
    $vigencia_date = date('d/m/Y', strtotime($row_vig['fecha_fin']));
}

// 3. Get Photo
$sql_pic = "SELECT archivo_path FROM Documentos_Alumno 
            WHERE alumno_id = $alumno_id AND tipo_documento = 'Fotografía de Perfil' 
            ORDER BY id DESC LIMIT 1";
$res_pic = $conn->query($sql_pic);
$photo = null;
if ($row_pic = $res_pic->fetch_assoc()) {
    $photo = $row_pic['archivo_path'];
}

// 4. Validate Rules
$success = false;
$message = '';
$status_text = 'INACTIVO';

if (strcasecmp($user['estado'], 'activo') !== 0) {
    $message = 'El alumno está marcado como INACTIVO administrativo.';
} elseif (!$is_enrolled) {
    $status_text = 'ACTIVO (S/INS)'; // Active user but no current cycle
    $message = 'El alumno no tiene ciclo escolar vigente.';
} else {
    $success = true;
    $status_text = 'ACTIVO';
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'student' => [
        'nombre' => $user['nombre_completo'],
        'curp' => $user['curp'], // Added CURP to response
        'foto' => $photo,
        'vigencia' => $vigencia_date,
        'status_text' => $status_text
    ]
]);
?>