<?php
// --- 1. OBTENER DATOS ---
require '../../back/db_connect.php';
$alumno_id = $_SESSION['user_id'];

// --- 2. FUNCIÓN AUXILIAR PARA CALCULAR PROMEDIOS (Copiada de body_clases.php) ---
/**
 * Función para calcular la calificación actual de una clase
 * Y obtener el desglose de promedios por categoría.
 * * @return array ['promedios' => [...], 'final' => 0.0]
 */
function getDetalleCalificacion($conn, $inscripcion_id) {
    $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
    if ($clase_id_result->num_rows == 0) {
        return ['promedios' => ['Actividades' => 0, 'Asistencia' => 0, 'Examenes' => 0], 'final' => 0];
    }
    $clase_id = $clase_id_result->fetch_assoc()['clase_id'];

    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_id")->fetch_all(MYSQLI_ASSOC);
    $ponderaciones = [];
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }

    $calificacion_final = 0;
    $promedios_alumno = [];

    foreach (['Actividades', 'Asistencia', 'Examenes'] as $cat_nombre) {
        $promedio_categoria = 0;
        $ponderacion_valor = 0;

        if (isset($ponderaciones[$cat_nombre])) {
            $categoria_id = $ponderaciones[$cat_nombre]['id'];
            $ponderacion_valor = $ponderaciones[$cat_nombre]['ponderacion'] ?? 0;

            $sql_califs = "SELECT AVG(c.calificacion_obtenida) as promedio
                           FROM Calificaciones c
                           JOIN Actividades_Evaluables a ON c.actividad_id = a.id
                           WHERE c.inscripcion_id = $inscripcion_id AND a.categoria_id = $categoria_id";

            $promedio_result = $conn->query($sql_califs)->fetch_assoc();

            if ($promedio_result && $promedio_result['promedio'] !== null) {
                $promedio_categoria = (float)$promedio_result['promedio'];
            }
        }

        $promedios_alumno[$cat_nombre] = $promedio_categoria;
        $calificacion_final += $promedio_categoria * ($ponderacion_valor / 100);
    }

    return ['promedios' => $promedios_alumno, 'final' => $calificacion_final];
}

// --- 3. OBTENER EL CICLO ACTIVO ---
$ciclo_activo_id = null;
$ciclo_activo_result = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1");
if ($ciclo_activo_result->num_rows > 0) {
    $ciclo_activo_id = $ciclo_activo_result->fetch_assoc()['id'];
}

// --- 4. OBTENER TODAS LAS INSCRIPCIONES DEL ALUMNO ---
// Y decidir si usar la calif "final" (oficial) o la "actual" (calculada)
$sql_inscripciones = "SELECT 
                            c.materia_id,
                            i.id as inscripcion_id,
                            i.calificacion_final,
                            c.ciclo_id
                       FROM Inscripciones i
                       JOIN Clases c ON i.clase_id = c.id
                       WHERE i.alumno_id = $alumno_id";

$result_inscripciones = $conn->query($sql_inscripciones);
$calificaciones_alumno = [];

while ($row = $result_inscripciones->fetch_assoc()) {
    $materia_id = $row['materia_id'];
    $calif = null;

    if ($row['calificacion_final'] !== null) {
        // Opción A: La calificación ya es "final" y está guardada.
        $calif = (float)$row['calificacion_final'];
    } elseif ($row['ciclo_id'] == $ciclo_activo_id) {
        // Opción B: Es del ciclo actual, hay que calcularla.
        $data_calif_actual = getDetalleCalificacion($conn, $row['inscripcion_id']);
        $calif = (float)$data_calif_actual['final'];
    }

    // Si encontramos una calificación (final o actual), la procesamos
    if ($calif !== null) {
        // Si no la teníamos, o si la nueva calificación es más alta (recursó y mejoró)
        if (!isset($calificaciones_alumno[$materia_id]) || $calif > $calificaciones_alumno[$materia_id]) {
            $calificaciones_alumno[$materia_id] = $calif;
        }
    }
}

// --- 5. OBTENER EL PLAN DE ESTUDIOS COMPLETO (EL CATÁLOGO) ---
$sql_catalogo = "SELECT id, nombre_materia, semestre 
                 FROM Materias 
                 ORDER BY semestre, nombre_materia";

$result_catalogo = $conn->query($sql_catalogo);
$plan_estudios = [];

while ($row = $result_catalogo->fetch_assoc()) {
    $plan_estudios[$row['semestre']][] = $row;
}

// --- 6. CALCULAR PROMEDIO GENERAL (GPA) ---
$promedio_general = 0;
if (count($calificaciones_alumno) > 0) {
    $promedio_general = array_sum($calificaciones_alumno) / count($calificaciones_alumno);
}
?>

<div class="row align-items-center mb-3">
    <div class="col-md-8">
        <h3 class="fs-4 mb-0">Avance de Carrera (Tira de Materias)</h3>
    </div>
    <div class="col-md-4 text-md-end">
        <div class="card bg-light">
            <div class="card-body p-2 text-center">
                <h6 class="card-title text-muted mb-0">Promedio General</h6>
                <h3 class="card-text fw-bold text-primary mb-0"><?php echo number_format($promedio_general, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (empty($plan_estudios)): ?>
    <div class="alert alert-secondary">No hay un plan de estudios (catálogo de materias) registrado en el sistema.</div>
<?php else: ?>
    <div class="row">
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Semestre <?php echo $i; ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (isset($plan_estudios[$i])): ?>
                                <?php foreach ($plan_estudios[$i] as $materia): ?>

                                    <?php
                                    // Buscamos si el alumno tiene calificación para esta materia
                                    $calificacion = $calificaciones_alumno[$materia['id']] ?? null;
                                    $status_class = '';

                                    if ($calificacion !== null) {
                                        // --- LÓGICA MODIFICADA ---
                                        // Asumimos escala 0-10, pasando con 6
                                        $status_class = ($calificacion >= 6) ? 'fw-bold' : 'text-danger fw-bold';
                                    } else {
                                        $calificacion = '--';
                                        $status_class = 'text-muted';
                                    }
                                    ?>

                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($materia['nombre_materia']); ?></span>
                                        <span class="<?php echo $status_class; ?>">
                                            <?php echo ($calificacion !== '--') ? number_format($calificacion, 1) : $calificacion; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-muted">No hay materias registradas para este semestre.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
<?php endif; ?>