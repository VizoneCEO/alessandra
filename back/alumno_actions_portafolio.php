<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 3) {
    header("Location: ../index.php");
    exit();
}

$action = $_POST['action'] ?? '';
$alumno_id = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../uploads/portafolio/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Function to handle single file upload
function uploadFile($file, $uploadDir, $alumno_id)
{
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed))
        return false;

    $filename = $alumno_id . '_p_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return '../../uploads/portafolio/' . $filename;
    }
    return false;
}

if ($action === 'create') {
    $titulo = $_POST['titulo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';

    if (empty($titulo) || empty($categoria)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos.']);
        exit();
    }

    // Handle "imagen" input which can be multiple now
    // We expect "imagen[]" from frontend if multiple="multiple" is used
    // But PHP maps <input type="file" name="imagen[]" multiple> to $_FILES['imagen'] structure being arrays.

    // Logic: First valid image is Cover. All valid images (including cover) go to Portafolio_Imagenes?
    // OR: Cover is Cover. Others are Gallery.
    // Let's stick to Plan: Cover column in Portafolio is the main one.

    $processedImages = [];

    // Normalize $_FILES array if multiple
    if (isset($_FILES['imagen']) && is_array($_FILES['imagen']['name'])) {
        $count = count($_FILES['imagen']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['imagen']['error'][$i] === UPLOAD_ERR_OK) {
                $f = [
                    'name' => $_FILES['imagen']['name'][$i],
                    'type' => $_FILES['imagen']['type'][$i],
                    'tmp_name' => $_FILES['imagen']['tmp_name'][$i],
                    'error' => $_FILES['imagen']['error'][$i],
                    'size' => $_FILES['imagen']['size'][$i]
                ];
                $path = uploadFile($f, $uploadDir, $alumno_id);
                if ($path)
                    $processedImages[] = $path;
            }
        }
    } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // Single file fallback
        $path = uploadFile($_FILES['imagen'], $uploadDir, $alumno_id);
        if ($path)
            $processedImages[] = $path;
    }

    if (empty($processedImages)) {
        echo json_encode(['success' => false, 'message' => 'Debes subir al menos una imagen válida.']);
        exit();
    }

    $coverPath = $processedImages[0]; // First one is cover

    $stmt = $conn->prepare("INSERT INTO Portafolio (alumno_id, titulo, categoria, imagen) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $alumno_id, $titulo, $categoria, $coverPath);

    if ($stmt->execute()) {
        $portfolio_id = $stmt->insert_id;

        // Insert ALL images into Gallery (including cover? Usually yes for full view)
        // Let's insert all of them into Portafolio_Imagenes
        $stmtImg = $conn->prepare("INSERT INTO Portafolio_Imagenes (portafolio_id, imagen_path) VALUES (?, ?)");
        foreach ($processedImages as $img) {
            $stmtImg->bind_param("is", $portfolio_id, $img);
            $stmtImg->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Proyecto publicado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en base de datos.']);
    }

} elseif ($action === 'update') {
    $id = intval($_POST['id']);
    $titulo = $_POST['titulo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';

    // Verify ownership
    $check = $conn->prepare("SELECT id FROM Portafolio WHERE id = ? AND alumno_id = ?");
    $check->bind_param("ii", $id, $alumno_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado.']);
        exit();
    }

    // Update Text
    $upd = $conn->prepare("UPDATE Portafolio SET titulo = ?, categoria = ? WHERE id = ?");
    $upd->bind_param("ssi", $titulo, $categoria, $id);
    $upd->execute();

    // Handle New Images
    if (isset($_FILES['imagen_new']) && is_array($_FILES['imagen_new']['name'])) {
        $count = count($_FILES['imagen_new']['name']);
        $stmtImg = $conn->prepare("INSERT INTO Portafolio_Imagenes (portafolio_id, imagen_path) VALUES (?, ?)");

        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['imagen_new']['error'][$i] === UPLOAD_ERR_OK) {
                $f = [
                    'name' => $_FILES['imagen_new']['name'][$i],
                    'tmp_name' => $_FILES['imagen_new']['tmp_name'][$i]
                ];
                $path = uploadFile($f, $uploadDir, $alumno_id);
                if ($path) {
                    $stmtImg->bind_param("is", $id, $path);
                    $stmtImg->execute();
                }
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Proyecto actualizado.']);

} elseif ($action === 'delete_image') {
    $img_id = intval($_POST['img_id']);

    // Verify ownership via join
    $sql = "SELECT pi.id, pi.imagen_path, p.id as pid FROM Portafolio_Imagenes pi 
            JOIN Portafolio p ON pi.portafolio_id = p.id 
            WHERE pi.id = ? AND p.alumno_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $img_id, $alumno_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // Delete File
        $relativePath = str_replace('../../', '../', $row['imagen_path']);
        $fullPath = __DIR__ . '/' . $relativePath;
        if (file_exists($fullPath))
            unlink($fullPath);

        // Delete DB
        $conn->query("DELETE FROM Portafolio_Imagenes WHERE id = $img_id");

        echo json_encode(['success' => true, 'message' => 'Imagen eliminada.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Imagen no encontrada o sin permiso.']);
    }

} elseif ($action === 'delete') {
    $id = intval($_POST['id']);

    // Verify ownership
    $check = $conn->prepare("SELECT id FROM Portafolio WHERE id = ? AND alumno_id = ?");
    $check->bind_param("ii", $id, $alumno_id);
    $check->execute();
    $res = $check->get_result();

    if ($row = $res->fetch_assoc()) {
        // Get all images from Gallery to delete files
        $imgs = $conn->query("SELECT imagen_path FROM Portafolio_Imagenes WHERE portafolio_id = $id");
        while ($r = $imgs->fetch_assoc()) {
            $relativePath = str_replace('../../', '../', $r['imagen_path']);
            $fullPath = __DIR__ . '/' . $relativePath;
            if (file_exists($fullPath))
                unlink($fullPath);
        }

        // Also delete Cover if not in gallery (though we put it in gallery too)
        // Just in case:
        $coverCheck = $conn->query("SELECT imagen FROM Portafolio WHERE id = $id")->fetch_assoc();
        $relativePath = str_replace('../../', '../', $coverCheck['imagen']);
        $fullPath = __DIR__ . '/' . $relativePath;
        if (file_exists($fullPath))
            unlink($fullPath);

        // Delete DB (Cascade should handle gallery rows, but we manually deleted files)
        $del = $conn->prepare("DELETE FROM Portafolio WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            echo json_encode(['success' => true, 'message' => 'Proyecto eliminado.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar de BD.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado or no autorizado.']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
}
?>