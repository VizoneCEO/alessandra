<?php
session_start();
require 'db_connect.php';

// --- Seguridad y Redirección ---
// 1. Verificamos que sea un profesor quien realiza la acción
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 2) {
    header("Location: ../index.php");
    exit();
}

// 2. Preparamos la URL de redirección para volver siempre a la misma clase
$clase_id = isset($_POST['clase_id']) ? $_POST['clase_id'] : null;
$redirect_url = "../front/profesor/dashboard.php?view=clases" . ($clase_id ? "&clase_id=$clase_id" : "");


// --- Procesador de Acciones ---
if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        // --- ACCIÓN: GUARDAR PONDERACIONES DE LAS 3 CATEGORÍAS PRINCIPALES ---
        case 'save_ponderaciones':
            if ($clase_id && isset($_POST['ponderacion'])) {
                $ponderaciones = $_POST['ponderacion']; // Array con claves 'Actividades', 'Asistencia', 'Examenes'

                // Validamos que la suma sea 100
                if (array_sum($ponderaciones) != 100) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: La suma de las ponderaciones debe ser exactamente 100%.'];
                    break; // Salimos del switch
                }

                foreach($ponderaciones as $nombre_cat => $valor_pond) {
                    // Buscamos si la categoría ya existe para esta clase
                    $stmt = $conn->prepare("SELECT id FROM Categorias_Calificacion WHERE clase_id = ? AND nombre_categoria = ?");
                    $stmt->bind_param("is", $clase_id, $nombre_cat);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) { // Si existe, la actualizamos
                        $cat_id = $result->fetch_assoc()['id'];
                        $stmt_update = $conn->prepare("UPDATE Categorias_Calificacion SET ponderacion = ? WHERE id = ?");
                        $stmt_update->bind_param("di", $valor_pond, $cat_id);
                        $stmt_update->execute();
                    } else { // Si no existe, la creamos
                        $stmt_insert = $conn->prepare("INSERT INTO Categorias_Calificacion (clase_id, nombre_categoria, ponderacion) VALUES (?, ?, ?)");
                        $stmt_insert->bind_param("isd", $clase_id, $nombre_cat, $valor_pond);
                        $stmt_insert->execute();
                    }
                }
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Ponderaciones guardadas exitosamente.'];
            }
            break;

        // --- ACCIÓN: CREAR UNA NUEVA ACTIVIDAD O EXAMEN ---
        case 'create_item':
            if (isset($_POST['categoria_id'], $_POST['nombre_item'])) {
                $categoria_id = $_POST['categoria_id'];
                $nombre_item = trim($_POST['nombre_item']);

                if (!empty($nombre_item)) {
                    $stmt = $conn->prepare("INSERT INTO Actividades_Evaluables (categoria_id, nombre_actividad) VALUES (?, ?)");
                    $stmt->bind_param("is", $categoria_id, $nombre_item);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Elemento creado exitosamente.'];
                    } else {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al crear el elemento.'];
                    }
                }
            }
            break;

        // --- ACCIÓN: ELIMINAR UNA ACTIVIDAD O EXAMEN ---
        case 'delete_item':
            if (isset($_POST['actividad_id'])) {
                $actividad_id = $_POST['actividad_id'];
                // La BD se encarga de borrar las calificaciones en cascada (ON DELETE CASCADE)
                $stmt = $conn->prepare("DELETE FROM Actividades_Evaluables WHERE id = ?");
                $stmt->bind_param("i", $actividad_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Elemento eliminado correctamente.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al eliminar el elemento.'];
                }
            }
            break;

        // --- ACCIÓN: GUARDAR CALIFICACIONES ---
        case 'save_grades':
            if (isset($_POST['calificaciones'])) {
                $calificaciones = $_POST['calificaciones'];

                $stmt = $conn->prepare("INSERT INTO Calificaciones (inscripcion_id, actividad_id, calificacion_obtenida) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE calificacion_obtenida = VALUES(calificacion_obtenida)");

                foreach($calificaciones as $inscripcion_id => $actividades) {
                    foreach($actividades as $actividad_id => $nota) {
                        if ($nota !== '' && is_numeric($nota)) {
                            $nota_decimal = (float)$nota;
                            $stmt->bind_param("iid", $inscripcion_id, $actividad_id, $nota_decimal);
                            $stmt->execute();
                        }
                    }
                }
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Calificaciones guardadas correctamente.'];
            }
            break;
    }
}

// Al final de cualquier acción, volvemos a la página de la clase
header("Location: " . $redirect_url);
exit();
?>