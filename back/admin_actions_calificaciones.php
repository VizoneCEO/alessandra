<?php
session_start();
require 'db_connect.php';
require 'log_helper.php';

// --- Seguridad y Redirección ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['perfil_id'], [1, 6])) { // Perfiles de admin
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'ajuste_extraordinario') {
        $clase_id = intval($_POST['clase_id'] ?? 0);
        $inscripcion_id = intval($_POST['inscripcion_id'] ?? 0);
        $parcial = intval($_POST['parcial'] ?? 0);
        $pesos = $_POST['pesos'] ?? [];
        $notas = $_POST['notas'] ?? [];

        if ($clase_id === 0 || $inscripcion_id === 0 || !in_array($parcial, [1, 2, 3])) {
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros críticos (Clase, Inscripción o Parcial).']);
            exit();
        }

        // Validar Suma
        $wAct = floatval($pesos['Actividades'] ?? 0);
        $wAsi = floatval($pesos['Asistencia'] ?? 0);
        $wExa = floatval($pesos['Examenes'] ?? 0);
        $totalW = $wAct + $wAsi + $wExa;

        if (abs($totalW - 100) > 0.01) {
            echo json_encode(['success' => false, 'message' => 'Las ponderaciones enviadas no suman 100%.']);
            exit();
        }

        // Iniciar Transacción
        $conn->begin_transaction();

        try {
            // STEP A: UPDATE OR INSERT PONDERACIONES
            foreach ($pesos as $nombre_cat => $valor_pond) {
                $stmt = $conn->prepare("SELECT id FROM Categorias_Calificacion WHERE clase_id = ? AND nombre_categoria = ?");
                $stmt->bind_param("is", $clase_id, $nombre_cat);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $cat_id = $result->fetch_assoc()['id'];
                    $stmt_update = $conn->prepare("UPDATE Categorias_Calificacion SET ponderacion = ? WHERE id = ?");
                    $stmt_update->bind_param("di", $valor_pond, $cat_id);
                    $stmt_update->execute();
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO Categorias_Calificacion (clase_id, nombre_categoria, ponderacion) VALUES (?, ?, ?)");
                    $stmt_insert->bind_param("isd", $clase_id, $nombre_cat, $valor_pond);
                    $stmt_insert->execute();
                }
            }

            // Reload Categories dictionary
            $categorias_map = [];
            $res = $conn->query("SELECT id, nombre_categoria FROM Categorias_Calificacion WHERE clase_id = $clase_id");
            while ($row = $res->fetch_assoc()) {
                $categorias_map[$row['nombre_categoria']] = $row['id'];
            }

            // STEP B & C: CREATE "ADMIN" ACTIVITIES AND ASSIGN GRADES
            $cat_data = [
                'Actividades' => ['nombre_act_base' => 'Ajuste Extraordinario Admin', 'nota' => $notas['Actividades'] ?? 0],
                'Asistencia'  => ['nombre_act_base' => 'Ajuste Asistencia Admin', 'nota' => $notas['Asistencia'] ?? 0],
                'Examenes'    => ['nombre_act_base' => 'Ajuste Examen Admin', 'nota' => $notas['Examenes'] ?? 0]
            ];

            foreach ($cat_data as $cat_key => $data) {
                if (!isset($categorias_map[$cat_key])) continue;
                $cat_id = $categorias_map[$cat_key];
                $nombre_act = $data['nombre_act_base'];
                $nota = floatval($data['nota']);

                // Find if an Admin activity already exists for this category + partial
                $stmt_act = $conn->prepare("SELECT id FROM Actividades_Evaluables WHERE categoria_id = ? AND parcial = ? AND nombre_actividad LIKE '%Admin%' LIMIT 1");
                $stmt_act->bind_param("ii", $cat_id, $parcial);
                $stmt_act->execute();
                $res_act = $stmt_act->get_result();

                $actividad_id = 0;
                if ($res_act->num_rows > 0) {
                    $actividad_id = $res_act->fetch_assoc()['id'];
                } else {
                    $stmt_insert_act = $conn->prepare("INSERT INTO Actividades_Evaluables (categoria_id, nombre_actividad, parcial) VALUES (?, ?, ?)");
                    $stmt_insert_act->bind_param("isi", $cat_id, $nombre_act, $parcial);
                    $stmt_insert_act->execute();
                    $actividad_id = $stmt_insert_act->insert_id;
                }

                // Assing grade
                if ($actividad_id > 0) {
                    $stmt_calif = $conn->prepare("INSERT INTO Calificaciones (inscripcion_id, actividad_id, calificacion_obtenida) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE calificacion_obtenida = VALUES(calificacion_obtenida)");
                    $stmt_calif->bind_param("iid", $inscripcion_id, $actividad_id, $nota);
                    $stmt_calif->execute();
                }
            }

            $conn->commit();
            registrar_log($conn, $_SESSION['user_id'], 'EXTRAORDINARIO', "Ajuste extraordinario emulado para inscripcion $inscripcion_id en parcial $parcial");

            echo json_encode(['success' => true, 'message' => 'Calificación Extraordinaria generada correctamente.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
        }
    }
}
