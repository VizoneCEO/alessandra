<?php
session_start();
require 'db_connect.php';

// --- ESTA LÍNEA ES NUEVA ---
// Le decimos a MySQLi que reporte los errores como excepciones para poder "cacharlos"
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Seguridad: Solo los administradores pueden jugar aquí ---
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        // --- ACCIÓN: CREAR UNA NUEVA MATERIA EN EL CATÁLOGO ---
        case 'create_materia':
            $nombre = $_POST['nombre_materia'];
            $semestre = $_POST['semestre'];

            $stmt = $conn->prepare("INSERT INTO Materias (nombre_materia, semestre) VALUES (?, ?)");
            $stmt->bind_param("si", $nombre, $semestre);
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Materia creada en el catálogo exitosamente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear la materia.'];
            }
            $stmt->close();
            break;

        // --- ACCIÓN: ASIGNAR UN PROFESOR A UNA MATERIA (CREAR CLASE) ---
        // --- BLOQUE 'create_clase' MODIFICADO CON TRY...CATCH ---
        case 'create_clase':
            $materia_id = $_POST['materia_id'];
            $profesor_id = $_POST['profesor_id'];
            $ciclo_id = $_POST['ciclo_id'];

            try {
                $stmt = $conn->prepare("INSERT INTO Clases (materia_id, profesor_id, ciclo_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $materia_id, $profesor_id, $ciclo_id);
                $stmt->execute();

                $_SESSION['message'] = ['type' => 'success', 'text' => 'Profesor asignado a la materia correctamente.'];
                $stmt->close();

            } catch (mysqli_sql_exception $e) {
                // Verificamos si el código de error es 1062 (Entrada duplicada)
                if ($e->getCode() == 1062) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Este profesor ya está asignado a esta materia en este ciclo.'];
                } else {
                    // Si es cualquier otro error, lo mostramos
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al asignar el profesor: ' . $e->getMessage()];
                }
            }
            break;

        // --- ACCIÓN: ELIMINAR LA ASIGNACIÓN DE UN PROFESOR (ELIMINAR CLASE) ---
        case 'delete_clase':
            $clase_id = $_POST['clase_id'];

            $stmt = $conn->prepare("DELETE FROM Clases WHERE id = ?");
            $stmt->bind_param("i", $clase_id);
             if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Asignación de profesor eliminada.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar la asignación.'];
            }
            $stmt->close();
            break;

            // --- ACCIÓN: ELIMINAR UNA MATERIA DEL CATÁLOGO ---
        // OJO: Esto es destructivo. Gracias al ON DELETE CASCADE, también borrará
        // todas las clases y asignaciones relacionadas a esta materia.
        case 'delete_materia':
            $materia_id = $_POST['materia_id'];

            $stmt = $conn->prepare("DELETE FROM Materias WHERE id = ?");
            $stmt->bind_param("i", $materia_id);
             if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Materia eliminada del catálogo permanentemente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar la materia.'];
            }
            $stmt->close();
            break;

    }
}

// Al terminar, siempre volvemos al dashboard de materias
header("Location: ../front/admin/dashboard.php?page=materias");
exit();
?>