<?php
session_start();
require 'db_connect.php';
require 'log_helper.php';

// Le decimos a MySQLi que reporte los errores como excepciones para poder "cacharlos"
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Seguridad: Solo los administradores pueden jugar aquí ---
// --- Seguridad: Solo los administradores y ayudantes pueden jugar aquí ---
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil_id'] != 1 && $_SESSION['perfil_id'] != 6)) {
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
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'CREATE_MATERIA', "Materia creada: $nombre", "Semestre: $semestre");
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Materia creada en el catálogo exitosamente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear la materia.'];
            }
            $stmt->close();
            break;

        // --- ACCIÓN: ASIGNAR UN PROFESOR A UNA MATERIA (CREAR CLASE) ---
        // --- BLOQUE 'create_clase' MODIFICADO ---
        case 'create_clase':
            $materia_id = $_POST['materia_id'];
            $profesor_id = $_POST['profesor_id'];
            $ciclo_id = $_POST['ciclo_id'];
            $sucursal_id = $_POST['sucursal_id'];
            $grupo = $_POST['grupo']; // <-- 1. RECIBIMOS EL GRUPO

            try {
                // 2. ACTUALIZAMOS LA CONSULTA SQL
                $stmt = $conn->prepare("INSERT INTO Clases (materia_id, profesor_id, ciclo_id, sucursal_id, grupo) VALUES (?, ?, ?, ?, ?)");
                // 3. ACTUALIZAMOS EL BIND_PARAM (de iiii a iiiis)
                $stmt->bind_param("iiiis", $materia_id, $profesor_id, $ciclo_id, $sucursal_id, $grupo);
                $stmt->execute();

                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'CREATE_CLASE', "Clase asignada. Materia ID: $materia_id", "Profesor: $profesor_id, Grupo: $grupo");

                $_SESSION['message'] = ['type' => 'success', 'text' => 'Profesor asignado a la materia correctamente.'];
                $stmt->close();

            } catch (mysqli_sql_exception $e) {
                // Verificamos si el código de error es 1062 (Entrada duplicada)
                if ($e->getCode() == 1062) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Este profesor ya está asignado a esta materia en este grupo, ciclo y sucursal.'];
                } else {
                    // Si es cualquier otro error, lo mostramos
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al asignar el profesor: ' . $e->getMessage()];
                }
            }
            break;
        // --- FIN DEL BLOQUE MODIFICADO ---

        // --- ACCIÓN: ELIMINAR LA ASIGNACIÓN DE UN PROFESOR (ELIMINAR CLASE) ---
        case 'delete_clase':
            if ($_SESSION['perfil_id'] == 6) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Permisos insuficientes: No puedes eliminar clases.'];
                break;
            }
            $clase_id = $_POST['clase_id'];

            $stmt = $conn->prepare("DELETE FROM Clases WHERE id = ?");
            $stmt->bind_param("i", $clase_id);
            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'DELETE_CLASE', "Clase eliminada: ID $clase_id");
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Asignación de profesor eliminada.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar la asignación.'];
            }
            $stmt->close();
            break;

        // --- ACCIÓN: ELIMINAR UNA MATERIA DEL CATÁLOGO ---
        case 'delete_materia':
            if ($_SESSION['perfil_id'] == 6) {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Permisos insuficientes: No puedes eliminar materias.'];
                break;
            }
            $materia_id = $_POST['materia_id'];

            $stmt = $conn->prepare("DELETE FROM Materias WHERE id = ?");
            $stmt->bind_param("i", $materia_id);
            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'DELETE_MATERIA', "Materia eliminada: ID $materia_id", "Permanente");
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Materia eliminada del catálogo permanentemente.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar la materia.'];
            }
            $stmt->close();
        // --- ACCIÓN: REASIGNAR PROFESOR A UNA CLASE EXISTENTE ---
        case 'reassign_profesor':
            $clase_id = $_POST['clase_id'] ?? 0;
            $nuevo_profesor_id = $_POST['profesor_id'] ?? 0;

            if ($clase_id > 0 && $nuevo_profesor_id > 0) {
                // REGLA DE ORO: Solo UPDATE, nada de DELETE
                $stmt = $conn->prepare("UPDATE Clases SET profesor_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $nuevo_profesor_id, $clase_id);

                if ($stmt->execute()) {
                    registrar_log($conn, $_SESSION['user_id'], 'REASSIGN_PROF', "Profesor reasignado en clase ID: $clase_id", "Nuevo Prof: $nuevo_profesor_id");
                    echo json_encode(['success' => true, 'message' => 'Profesor reasignado correctamente. Respetando el historial académico previo.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error en la base de datos al reasignar el profesor.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Datos insuficientes para la reasignación.']);
            }
            exit(); // IMPORTANTE: Terminar script para devolver AJAX puro
            break;

    }
}

// Al terminar, siempre volvemos al dashboard de materias
// Los filtros de ciclo y sucursal se pierden, pero eso es esperado por ahora.
// Al terminar, volvemos al dashboard correspondiente
// Recuperamos filtros para persistencia
$ciclo_redirect = isset($_POST['ciclo_id']) ? "&ciclo_id=" . $_POST['ciclo_id'] : "";
$sucursal_redirect = isset($_POST['sucursal_id']) ? "&sucursal_id=" . $_POST['sucursal_id'] : "";

// Determinamos el ancla (scroll position)
// Si viene de crear materia, quizás semestrre? Si es asignación, seguro semestre.
// Vamos a intentar obtener el semestre de la materia si es posible, o recibirlo por POST si lo añadimos al form.
// Por simplicidad, si es create_clase o delete_clase, buscaremos el materia_id para saber el semestre?
// Más fácil: recibir 'semestre_anchor' en el POST.

$anchor = "";
if (isset($_POST['semestre'])) {
    $anchor = "#semestre_" . $_POST['semestre'];
} elseif (isset($_POST['materia_id'])) {
    // Si tenemos materia_id pero no semestre explícito (ej. delete_clase), podríamos buscarlo,
    // pero para 'create_clase' ya enviamos materia_id.
    // Vamos a hacer que el frontend envíe 'semestre_anchor' hidden.
}

if (isset($_POST['semestre_anchor'])) {
    $anchor = "#semestre_" . $_POST['semestre_anchor'];
}

$base_url = ($_SESSION['perfil_id'] == 6) ? "../front/ayudante/dashboard.php" : "../front/admin/dashboard.php";
header("Location: " . $base_url . "?page=materias" . $ciclo_redirect . $sucursal_redirect . $anchor);
exit();
?>