<?php
session_start();
require_once 'db_connect.php';

// Security Check: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    header("Location: ../../index.php");
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'approve') {
    $doc_id = intval($_POST['doc_id']);
    $alumno_id = intval($_POST['alumno_id']);

    $stmt = $conn->prepare("UPDATE Documentos_Alumno SET estado = 'Aprobado', mensaje_rechazo = NULL, fecha_actualizacion = NOW() WHERE id = ?");
    $stmt->bind_param("i", $doc_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Documento aprobado exitosamente.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al aprobar documento.'];
    }
    header("Location: ../front/admin/dashboard.php?page=alumno_setup&user_id=$alumno_id");
    exit();

} elseif ($action === 'reject') {
    $doc_id = intval($_POST['doc_id']);
    $alumno_id = intval($_POST['alumno_id']); // For redirection
    $motivo = $_POST['motivo_rechazo'] ?? 'Sin motivo especificado';

    $stmt = $conn->prepare("UPDATE Documentos_Alumno SET estado = 'Rechazado', mensaje_rechazo = ?, fecha_actualizacion = NOW() WHERE id = ?");
    $stmt->bind_param("si", $motivo, $doc_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Documento rechazado. Se ha notificado al alumno.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al rechazar documento.'];
    }
    header("Location: ../front/admin/dashboard.php?page=alumno_setup&user_id=$alumno_id");
    exit();

} elseif ($action === 'admin_upload') {
    $alumno_id = intval($_POST['alumno_id']);
    $tipo = $_POST['tipo_documento'] ?? '';

    if (empty($tipo) || empty($alumno_id) || !isset($_FILES['documento'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Datos incompletos para subida administrativa.'];
        header("Location: ../front/admin/dashboard.php?page=alumno_setup&user_id=$alumno_id");
        exit();
    }

    $file = $_FILES['documento'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al subir archivo.'];
        header("Location: ../front/admin/dashboard.php?page=alumno_setup&user_id=$alumno_id");
        exit();
    }

    // Allowed types
    $allowed = ['pdf'];
    $allowed_images = ['jpg', 'jpeg', 'png'];

    if ($tipo === 'Identificación Oficial') {
        $allowed = array_merge(['pdf'], $allowed_images);
    } elseif ($tipo === 'Fotografía de Perfil') {
        $allowed = $allowed_images;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Formato no permitido.'];
        header("Location: ../front/admin/dashboard.php?page=alumno_setup&user_id=$alumno_id");
        exit();
    }

    // Path Logic (Same as Alumno)
    // Script is in back/
    // Upload dir is ../uploads/documentos/
    $uploadDir = __DIR__ . '/../uploads/documentos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $cleanType = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($tipo));
    $filename = $alumno_id . '_' . $cleanType . '_' . time() . '_admin.' . $ext;
    $targetPath = $uploadDir . $filename;
    $dbPath = '../../uploads/documentos/' . $filename; // Frontend Relative Path

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Admin upload = Auto Approved? Yes usually.
        $sql = "INSERT INTO Documentos_Alumno (alumno_id, tipo_documento, archivo_path, estado, mensaje_rechazo, fecha_subida)
                VALUES (?, ?, ?, 'Aprobado', NULL, NOW())
                ON DUPLICATE KEY UPDATE 
                archivo_path = VALUES(archivo_path), 
                estado = 'Aprobado', 
                mensaje_rechazo = NULL,
                fecha_subida = NOW()";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $alumno_id, $tipo, $dbPath);

        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Documento subido y aprobado correctamente.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al guardar en BD.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al mover archivo (Admin).'];
    }

    header("Location: ../front/admin/dashboard.php?page=alumno_setup&user_id=$alumno_id");
    exit();
}
?>