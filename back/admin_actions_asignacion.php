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
    }
}

// Al terminar, siempre volvemos a la página de asignación de la clase en la que estábamos
header("Location: " . $redirect_url);
exit();
?>