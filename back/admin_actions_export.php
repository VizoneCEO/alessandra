<?php
session_start();
require 'db_connect.php';

// Validar que un administrador está solicitando
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 1) {
    die("Acceso Denegado: No posees los privilegios para extraer información en bruto.");
}

$report_type = $_POST['report_type'] ?? '';

if (empty($report_type)) {
    die("Error: No se especificó el tipo de reporte.");
}

// Configurar CSV Básico
function setup_csv_headers($filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename . '.csv');
    // Salida hacia el wrapper de php (salida directa al navegador)
    $output = fopen('php://output', 'w');
    // Forzar BOM UTF-8 para Excel Básico
    fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
    return $output;
}

if ($report_type === 'padron_alumnos') {
    $out = setup_csv_headers("Padron_General_Alumnos_" . date("Y-m-d"));
    fputcsv($out, ['ID', 'Matricula', 'Nombre Completo', 'CURP', 'Modalidad', 'Tel Celular', 'Calle y Num', 'Colonia', 'Contacto de Emergencia', 'Tel Emergencia']);

    $modalidad = $_POST['modalidad'] ?? 'all';
    $sucursal_id = $_POST['sucursal_id'] ?? 'all';

    $where_clauses = ["u.perfil_id = 3"]; // 3 es Alumno usualmente
    
    if ($modalidad !== 'all') {
        $mod_safe = $conn->real_escape_string($modalidad);
        $where_clauses[] = "u.forma = '$mod_safe'";
    }

    $join_sucursal = "";
    if ($sucursal_id !== 'all') {
        $suc_id = intval($sucursal_id);
        $join_sucursal = " JOIN Inscripciones i ON i.alumno_id = u.id JOIN Clases cl ON i.clase_id = cl.id AND cl.sucursal_id = $suc_id ";
    }

    $where_sql = implode(' AND ', $where_clauses);
    $sql = "SELECT DISTINCT u.id, u.matricula, u.nombre_completo, u.curp, u.forma, 
                   c.telefono_celular, c.calle_numero, c.colonia, c.emergencia_nombre, c.emergencia_telefono 
            FROM Usuarios u 
            LEFT JOIN Contacto_Alumnos c ON u.id = c.usuario_id 
            $join_sucursal 
            WHERE $where_sql
            ORDER BY u.nombre_completo ASC";
            
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], 
            $row['matricula'], 
            $row['nombre_completo'], 
            $row['curp'], 
            strtoupper($row['forma']), 
            $row['telefono_celular'], 
            $row['calle_numero'], 
            $row['colonia'], 
            $row['emergencia_nombre'], 
            $row['emergencia_telefono']
        ]);
    }
    fclose($out);
    exit();

} elseif ($report_type === 'financiero') {
    $out = setup_csv_headers("Reporte_Financiero_" . date("Y-m-d"));
    fputcsv($out, ['Alumno', 'Matricula', 'Total Pagado', 'Total Adeudo', 'Ultimo Pago Registrado', 'Estatus Gral']);

    $sql = "SELECT u.nombre_completo, u.matricula, 
                   SUM(CASE WHEN f.estado = 'Pagado' THEN f.total ELSE 0 END) AS pagado, 
                   SUM(CASE WHEN f.estado IN ('Vencido', 'Pago Pendiente') THEN f.total ELSE 0 END) AS adeudo, 
                   MAX(f.fecha_pago) AS ultimo_pago
            FROM Usuarios u 
            LEFT JOIN finanzas_cargos f ON u.id = f.alumno_id 
            WHERE u.perfil_id = 3
            GROUP BY u.id
            ORDER BY u.nombre_completo ASC";

    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $adeudo = floatval($row['adeudo']);
        $estatus = ($adeudo > 0) ? 'Con Deuda' : 'Al Corriente';
        // Format money slightly
        fputcsv($out, [
            $row['nombre_completo'],
            $row['matricula'],
            "$" . number_format($row['pagado'], 2),
            "$" . number_format($adeudo, 2),
            $row['ultimo_pago'] ? date("d/m/Y", strtotime($row['ultimo_pago'])) : 'Núnca',
            $estatus
        ]);
    }
    fclose($out);
    exit();

} elseif ($report_type === 'reprobados') {
    $out = setup_csv_headers("Auditoria_Reprobados_" . date("Y-m-d"));
    fputcsv($out, ['Materia', 'Profesor Asignado', 'Alumno', 'Promedio Obtenido', 'Estatus']);

    $ciclo_id = intval($_POST['ciclo_id']);
    $min_calificacion = floatval($_POST['min_calificacion'] ?? 7.0);

    $sql = "SELECT 
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
            ORDER BY m.nombre_materia ASC, promedio ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("id", $ciclo_id, $min_calificacion);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['nombre_materia'],
            $row['profesor_nombre'],
            $row['alumno_nombre'],
            number_format($row['promedio'], 2),
            'REPROBADO'
        ]);
    }
    $stmt->close();
    fclose($out);
    exit();

} else {
    die("Error: Estructura de reporte no configurada o corrupta.");
}
?>
