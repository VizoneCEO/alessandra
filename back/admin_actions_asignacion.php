<?php
session_start();
require 'db_connect.php';
require 'log_helper.php';

// --- Seguridad: Solo los administradores y ayudantes pueden hacer esto ---
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil_id'] != 1 && $_SESSION['perfil_id'] != 6)) {
    header("Location: ../index.php");
    exit();
}

// Guardamos el ID de la clase para poder redirigir de vuelta a la misma página
$clase_id = isset($_POST['clase_id']) ? $_POST['clase_id'] : null;
$base_url = ($_SESSION['perfil_id'] == 6) ? "../front/ayudante/dashboard.php" : "../front/admin/dashboard.php";
$redirect_url = $base_url . "?page=asignacion" . ($clase_id ? "&clase_id=$clase_id" : "");


if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        // --- ACCIÓN: INSCRIBIR UN ALUMNO A UNA CLASE ---
        case 'enroll_student':
            $alumno_id = $_POST['alumno_id'];

            $stmt = $conn->prepare("INSERT INTO Inscripciones (alumno_id, clase_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $alumno_id, $clase_id);
            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'ENROLL_STUDENT', "Alumno inscrito en clase: $clase_id", "Alumno ID: $alumno_id");
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Alumno inscrito en la clase correctamente.'];
            } else {
                if ($conn->errno == 1062) { // Error de entrada duplicada
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: El alumno ya está inscrito en esta clase.'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al inscribir al alumno.'];
                }
            }
            $stmt->close();
            break;

        // --- ACCIÓN: DAR DE BAJA A UN ALUMNO DE UNA CLASE ---
        case 'unenroll_student':
            $inscripcion_id = $_POST['inscripcion_id'];

            $stmt = $conn->prepare("DELETE FROM Inscripciones WHERE id = ?");
            $stmt->bind_param("i", $inscripcion_id);
            if ($stmt->execute()) {
                // LOG
                registrar_log($conn, $_SESSION['user_id'], 'UNENROLL_STUDENT', "Alumno dado de baja: Inscripción $inscripcion_id");
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Alumno dado de baja de la clase.'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error al dar de baja al alumno.'];
            }
            $stmt->close();
            break;

        // --- ACCIÓN: IMPORTAR ALUMNOS DE OTRO GRUPO ---
        case 'import_group':
            $source_grupo = $_POST['source_grupo'];
            
            // 1. Obtener materia_id y ciclo_id de la clase actual
            $stmt = $conn->prepare("SELECT materia_id, ciclo_id FROM Clases WHERE id = ?");
            $stmt->bind_param("i", $clase_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $target_clase = $res->fetch_assoc();
                $materia_id = $target_clase['materia_id'];
                $ciclo_id = $target_clase['ciclo_id'];
                $stmt->close();
                
                // 2. Obtener alumnos del grupo de origen que no estén ya inscritos en la misma materia/ciclo
                $sql = "SELECT DISTINCT i.alumno_id 
                        FROM Inscripciones i
                        JOIN Clases c ON i.clase_id = c.id
                        JOIN Usuarios u ON i.alumno_id = u.id
                        WHERE c.grupo = ? 
                          AND c.ciclo_id = ? 
                          AND u.estado = 'Activo'
                          AND u.perfil_id = 3
                          AND i.alumno_id NOT IN (
                              SELECT i2.alumno_id 
                              FROM Inscripciones i2
                              JOIN Clases c2 ON i2.clase_id = c2.id
                              WHERE c2.materia_id = ? AND c2.ciclo_id = ?
                          )";
                $stmt2 = $conn->prepare($sql);
                $stmt2->bind_param("siii", $source_grupo, $ciclo_id, $materia_id, $ciclo_id);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                
                $count = 0;
                if ($res2->num_rows > 0) {
                    $enroll_stmt = $conn->prepare("INSERT INTO Inscripciones (alumno_id, clase_id) VALUES (?, ?)");
                    while ($row = $res2->fetch_assoc()) {
                        $alumno_id = $row['alumno_id'];
                        $enroll_stmt->bind_param("ii", $alumno_id, $clase_id);
                        if ($enroll_stmt->execute()) {
                            $count++;
                            registrar_log($conn, $_SESSION['user_id'], 'ENROLL_STUDENT', "Alumno importado de grupo $source_grupo a clase: $clase_id", "Alumno ID: $alumno_id");
                        }
                    }
                    $enroll_stmt->close();
                }
                $stmt2->close();
                
                if ($count > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => "Se importaron $count alumnos del grupo $source_grupo exitosamente."];
                } else {
                    $_SESSION['message'] = ['type' => 'info', 'text' => "No se encontraron alumnos disponibles en el grupo $source_grupo para importar (o ya están inscritos en esta materia)."];
                }
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: Clase destino no encontrada.'];
                $stmt->close();
            }
            break;
    }
}

// Al terminar, siempre volvemos a la página de asignación de la clase en la que estábamos
header("Location: " . $redirect_url);
exit();
?>