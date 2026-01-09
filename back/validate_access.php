<?php
require 'db_connect.php';
header('Content-Type: application/json');

$input = $_POST['curp'] ?? '';

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