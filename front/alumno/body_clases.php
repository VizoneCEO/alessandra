<?php
// --- 1. OBTENER DATOS INICIALES ---
require '../../back/db_connect.php';
$alumno_id = $_SESSION['user_id'];

/**
 * Función para calcular la calificación actual de una clase
 * Y obtener el desglose de promedios por categoría.
 * (Adaptada de la lógica de front/profesor/body_reporte.php)
 * * @return array ['promedios' => [...], 'final' => 0.0]
 */
function getDetalleCalificacion($conn, $inscripcion_id) {
    // Obtenemos el clase_id de esta inscripción
    $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
    if ($clase_id_result->num_rows == 0) {
        // Retornamos un array vacío si no se encuentra
        return ['promedios' => ['Actividades' => 0, 'Asistencia' => 0, 'Examenes' => 0], 'final' => 0];
    }
    $clase_id = $clase_id_result->fetch_assoc()['clase_id'];

    // a) Obtenemos las ponderaciones
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_id")->fetch_all(MYSQLI_ASSOC);
    $ponderaciones = [];
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }

    $calificacion_final = 0;
    $promedios_alumno = [];

    // b) Calculamos el promedio para cada categoría principal
    foreach (['Actividades', 'Asistencia', 'Examenes'] as $cat_nombre) {
        $promedio_categoria = 0;
        $ponderacion_valor = 0;

        if (isset($ponderaciones[$cat_nombre])) {
            $categoria_id = $ponderaciones[$cat_nombre]['id'];
            $ponderacion_valor = $ponderaciones[$cat_nombre]['ponderacion'] ?? 0;

            // Obtenemos el PROMEDIO de las calificaciones del alumno en esta categoría
            $sql_califs = "SELECT AVG(c.calificacion_obtenida) as promedio
                           FROM Calificaciones c
                           JOIN Actividades_Evaluables a ON c.actividad_id = a.id
                           WHERE c.inscripcion_id = $inscripcion_id AND a.categoria_id = $categoria_id";

            $promedio_result = $conn->query($sql_califs)->fetch_assoc();

            if ($promedio_result && $promedio_result['promedio'] !== null) {
                $promedio_categoria = (float)$promedio_result['promedio'];
            }
        }

        // Guardamos el promedio de la categoría (ej. 80.00)
        $promedios_alumno[$cat_nombre] = $promedio_categoria;
        // Sumamos a la calificación final, aplicando la ponderación (ej. 80.00 * (30 / 100))
        $calificacion_final += $promedio_categoria * ($ponderacion_valor / 100);
    }

    return ['promedios' => $promedios_alumno, 'final' => $calificacion_final];
}


// c) Obtener el ciclo activo
$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();
$mis_clases = [];

if ($ciclo_activo) {
    $ciclo_activo_id = $ciclo_activo['id'];
    // d) Obtener las clases (inscripciones) del alumno PARA ESE CICLO
    $sql_clases = "SELECT 
                        m.nombre_materia, 
                        u.nombre_completo AS profesor_nombre,
                        i.id AS inscripcion_id
                   FROM Inscripciones i
                   JOIN Clases c ON i.clase_id = c.id
                   JOIN Materias m ON c.materia_id = m.id
                   JOIN Usuarios u ON c.profesor_id = u.id
                   WHERE i.alumno_id = $alumno_id AND c.ciclo_id = $ciclo_activo_id
                   ORDER BY m.nombre_materia";
    $mis_clases = $conn->query($sql_clases)->fetch_all(MYSQLI_ASSOC);
}
?>

<h3 class="fs-4 mb-3">Mis Clases (Ciclo Actual)</h3>

<?php if (!$ciclo_activo): ?>
    <div class="alert alert-warning">No hay ningún ciclo escolar activo en este momento.</div>
<?php elseif (empty($mis_clases)): ?>
    <div class="alert alert-secondary">No estás inscrito a ninguna clase en el ciclo escolar activo.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($mis_clases as $clase): ?>
            <?php
            // Calculamos la calificación actual y el desglose
            $data_calificacion = getDetalleCalificacion($conn, $clase['inscripcion_id']);
            $calif_final = $data_calificacion['final'];
            $promedios = $data_calificacion['promedios'];
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($clase['nombre_materia']); ?></h5>
                        <small class="text-white-50">
                            <i class="fas fa-user-tie me-1"></i>
                            <?php echo htmlspecialchars($clase['profesor_nombre']); ?>
                        </small>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-tasks me-2 text-muted"></i>
                                    Actividades
                                </div>
                                <span class="badge bg-primary rounded-pill fs-6">
                                    <?php echo number_format($promedios['Actividades'], 1); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-user-check me-2 text-muted"></i>
                                    Asistencia
                                </div>
                                <span class="badge bg-primary rounded-pill fs-6">
                                    <?php echo number_format($promedios['Asistencia'], 1); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-alt me-2 text-muted"></i>
                                    Exámenes
                                </div>
                                <span class="badge bg-primary rounded-pill fs-6">
                                    <?php echo number_format($promedios['Examenes'], 1); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer text-center bg-light">
                        <h6 class="mb-1 text-muted">Calificación Actual</h6>
                        <h2 class="fw-bold text-primary mb-0"><?php echo number_format($calif_final, 1); ?></h2>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>