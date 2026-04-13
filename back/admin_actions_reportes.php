<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['perfil_id'] != 1 && $_SESSION['perfil_id'] != 6)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'fetch_reprobados') {
    $ciclo_id = intval($_POST['ciclo_id'] ?? 0);
    $min_calificacion = floatval($_POST['min_calificacion'] ?? 7.0);

    if ($ciclo_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Ciclo no válido.']);
        exit();
    }

    try {
        $sql = "
            SELECT 
                m.nombre_materia, 
                u_prof.nombre_completo AS profesor_nombre,
                u_alum.nombre_completo AS alumno_nombre,
                AVG(calif.calificacion_obtenida) AS promedio
            FROM Inscripciones i
            JOIN Clases c ON i.clase_id = c.id
            JOIN Materias m ON c.materia_id = m.id
            JOIN Usuarios u_prof ON c.profesor_id = u_prof.id
            JOIN Usuarios u_alum ON i.alumno_id = u_alum.id
            JOIN Calificaciones calif ON calif.inscripcion_id = i.id
            WHERE c.ciclo_id = ?
            GROUP BY i.id, c.id
            HAVING promedio < ?
            ORDER BY m.nombre_materia ASC, promedio ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("id", $ciclo_id, $min_calificacion);
        $stmt->execute();
        $result = $stmt->get_result();

        $reprobados = [];
        while ($row = $result->fetch_assoc()) {
            $reprobados[] = $row;
        }

        echo json_encode([
            'success' => true, 
            'reprobados' => $reprobados
        ]);
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    
    exit();
}
