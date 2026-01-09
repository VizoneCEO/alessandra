<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 3) {
    header("Location: ../index.php");
    exit();
}

$action = $_POST['action'] ?? '';
$alumno_id = $_SESSION['user_id'];

if ($action === 'upload') {
    $tipo = $_POST['tipo_documento'] ?? '';

    if (empty($tipo) || !isset($_FILES['documento'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Faltan datos o archivo.'];
        header("Location: ../front/alumno/dashboard.php?view=documentos");
        exit();
    }

    $file = $_FILES['documento'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al subir archivo. Código: ' . $file['error']];
        header("Location: ../front/alumno/dashboard.php?view=documentos");
        exit();
    }

    // Validate Type
    $allowed = ['pdf'];
    $allowed_images = ['jpg', 'jpeg', 'png'];

    // Reglas de validación por tipo
    if ($tipo === 'Identificación Oficial') {
        $allowed = array_merge(['pdf'], $allowed_images);
    } elseif ($tipo === 'Fotografía de Perfil') {
        $allowed = $allowed_images; // Solo imágenes
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $msg = 'Formato no válido.';
        if ($tipo === 'Identificación Oficial')
            $msg = 'Solo PDF, JPG, PNG.';
        if ($tipo === 'Fotografía de Perfil')
            $msg = 'Solo JPG, PNG (Imágenes).';
        if (!in_array('jpg', $allowed) && !in_array('pdf', $allowed))
            $msg = 'Tipo no soportado.';
        if (!in_array('jpg', $allowed) && in_array('pdf', $allowed))
            $msg = 'Solo archivo PDF.';

        $_SESSION['message'] = ['type' => 'error', 'text' => "Formato no permitido para $tipo. $msg"];
        header("Location: ../front/alumno/dashboard.php?view=documentos");
        exit();
    }

    // Prepare Path
    $uploadDir = __DIR__ . '/../uploads/documentos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    } // Ensure directory exists

    // Clean document type for filename (remove spaces, accents)
    $cleanType = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($tipo));
    $filename = $alumno_id . '_' . $cleanType . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    $dbPath = '../../uploads/documentos/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Insert or Update ON DUPLICATE KEY UPDATE logic (using UNIQUE key)
        // Reset status to Pendiente on re-upload
        $sql = "INSERT INTO Documentos_Alumno (alumno_id, tipo_documento, archivo_path, estado, mensaje_rechazo)
                VALUES (?, ?, ?, 'Pendiente', NULL)
                ON DUPLICATE KEY UPDATE 
                archivo_path = VALUES(archivo_path), 
                estado = 'Pendiente', 
                mensaje_rechazo = NULL,
                fecha_subida = NOW()";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $alumno_id, $tipo, $dbPath);

        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Documento subido correctamente. En espera de validación.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al guardar en base de datos.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al mover el archivo al servidor.'];
    }

    header("Location: ../front/alumno/dashboard.php?view=documentos");
    exit();

} elseif ($action === 'delete') {
    $doc_id = intval($_POST['doc_id']);

    // Check status first - ONLY delete if NOT approved
    $check = $conn->prepare("SELECT estado, archivo_path FROM Documentos_Alumno WHERE id = ? AND alumno_id = ?");
    $check->bind_param("ii", $doc_id, $alumno_id);
    $check->execute();
    $res = $check->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['estado'] === 'Aprobado') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No puedes eliminar un documento aprobado.'];
        } else {
            // Delete file
            $filePath = __DIR__ . '/../../' . str_replace('../../', '', $row['archivo_path']); // Fix relative path for server
            // Actually, DB path is relative to 'front/...'. 
            // DB path stored: '../../uploads/...'
            // Current script is in 'back/'.
            // So DB path '../../uploads' relative to 'front/alumno' means 'root/uploads'.
            // Current script 'back/' needs '../uploads'.
            // Let's rely on absolute path logic or fixed relative.

            // Re-resolve: 
            // stored: ../../uploads/documentos/file.pdf (relative to front/alumno/body.php)
            // real path: root/uploads/documentos/file.pdf
            // this script: root/back/action.php
            // target from here: ../uploads/documentos/file.pdf

            $realPath = __DIR__ . '/../' . $row['archivo_path']; // ../../../uploads... no wait.
            // If stored is '../../uploads', that goes up 2 levels from front/alumno -> root.
            // So it IS root/uploads.
            // From back/ (1 level deep), we need ../uploads.
            // So we need to remove one '../' from the stored path? 
            // No, easiest is just construct absolute path if possible or map it.

            // Let's try: ../uploads/documentos/filename 
            // The stored path is intended for HREF (HTML). 
            // Ideally we store relative to root 'uploads/...'. 
            // But current code stored '../../uploads/...'. 

            // Fix: Just use the filename if standard structure.
            $filename = basename($row['archivo_path']);
            $fileToDelete = __DIR__ . '/../uploads/documentos/' . $filename;

            if (file_exists($fileToDelete)) {
                unlink($fileToDelete);
            }

            // Delete DB Row
            $del = $conn->prepare("DELETE FROM Documentos_Alumno WHERE id = ?");
            $del->bind_param("i", $doc_id);
            if ($del->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Documento eliminado.'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error al eliminar registro.'];
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Documento no encontrado.'];
    }

    header("Location: ../front/alumno/dashboard.php?view=documentos");
    exit();
}
?>