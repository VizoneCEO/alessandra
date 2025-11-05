<?php
// --- 1. OBTENER DATOS INICIALES ---
require '../../back/db_connect.php';
$profesor_id = $_SESSION['user_id'];
$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();
$categorias_principales = ['Actividades', 'Asistencia', 'Examenes']; // Definición global

if (!$ciclo_activo) {
    echo '<div class="alert alert-warning">No hay un ciclo escolar activo para generar reportes.</div>';
    return;
}
$ciclo_activo_id = $ciclo_activo['id'];

// Obtenemos las clases del profesor para el selector
$mis_clases = $conn->query("SELECT c.id, m.nombre_materia FROM Clases c JOIN Materias m ON c.materia_id = m.id WHERE c.profesor_id = $profesor_id AND c.ciclo_id = $ciclo_activo_id")->fetch_all(MYSQLI_ASSOC);

// Verificamos si se ha seleccionado una clase
$clase_seleccionada_id = isset($_GET['clase_id']) ? (int)$_GET['clase_id'] : null;
$reporte_data = [];
$ponderaciones = []; // Mapa de [cat_nombre] => [datos]

// --- 2. FUNCIÓN DE CÁLCULO (LÓGICA FINAL CORREGIDA) ---
function getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales) {

    $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
    if ($clase_id_result->num_rows == 0) {
        return ['final' => 0, 'promedios_parciales' => [], 'items_desglose' => [], 'calif_por_parcial' => [1=>0, 2=>0, 3=>0]];
    }
    $clase_id = $clase_id_result->fetch_assoc()['clase_id'];

    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_id")->fetch_all(MYSQLI_ASSOC);
    $ponderaciones = [];
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }

    $data_return = [
        'final' => 0.0,
        'promedios_parciales' => [],
        'items_desglose' => [],
        'calif_por_parcial' => [1 => 0.0, 2 => 0.0, 3 => 0.0]
    ];

    foreach ($categorias_principales as $cat_nombre) {

        $cat_id = $ponderaciones[$cat_nombre]['id'] ?? 0;

        for ($p = 1; $p <= 3; $p++) {
            $data_return['promedios_parciales'][$cat_nombre][$p] = 0;
            $data_return['items_desglose'][$cat_nombre][$p] = [];
        }

        if ($cat_id > 0) {
            $sql_items = "SELECT a.parcial, a.nombre_actividad, c.calificacion_obtenida 
                          FROM Calificaciones c
                          JOIN Actividades_Evaluables a ON c.actividad_id = a.id
                          WHERE c.inscripcion_id = ? AND a.categoria_id = ?
                          ORDER BY a.parcial, a.id";

            $stmt = $conn->prepare($sql_items);
            $stmt->bind_param("ii", $inscripcion_id, $cat_id);
            $stmt->execute();
            $result_items = $stmt->get_result();

            $califs_por_parcial = [1 => [], 2 => [], 3 => []];

            while ($item = $result_items->fetch_assoc()) {
                $parcial = $item['parcial'];
                $calif = (float)$item['calificacion_obtenida'];
                $data_return['items_desglose'][$cat_nombre][$parcial][] = ['nombre' => $item['nombre_actividad'], 'calif' => $calif];
                $califs_por_parcial[$parcial][] = $calif;
            }
            $stmt->close();

            for ($p = 1; $p <= 3; $p++) {
                if (count($califs_por_parcial[$p]) > 0) {
                    $data_return['promedios_parciales'][$cat_nombre][$p] = array_sum($califs_por_parcial[$p]) / count($califs_por_parcial[$p]);
                }
            }
        }
    }

    // Calculamos los totales ponderados por parcial
    for ($p = 1; $p <= 3; $p++) {
        foreach ($categorias_principales as $cat_nombre) {
            $ponderacion = ($ponderaciones[$cat_nombre]['ponderacion'] ?? 0) / 100;
            $promedio = $data_return['promedios_parciales'][$cat_nombre][$p] ?? 0;
            $data_return['calif_por_parcial'][$p] += ($promedio * $ponderacion);
        }
    }

    // --- LÓGICA DE CÁLCULO FINAL CORREGIDA ---
    // La calificación final es el promedio de los 3 parciales.
    $suma_parciales = $data_return['calif_por_parcial'][1] + $data_return['calif_por_parcial'][2] + $data_return['calif_por_parcial'][3];
    // Dividimos entre 3 solo si hay calificaciones (para evitar división por cero si todo está en 0)
    if ($suma_parciales > 0) {
        $data_return['final'] = $suma_parciales / 3;
    } else {
        $data_return['final'] = 0.0;
    }
    // --- FIN DE LA CORRECCIÓN ---

    return $data_return;
}
// --- FIN DE LA FUNCIÓN ---


if ($clase_seleccionada_id) {
    // a) Obtenemos las ponderaciones para la cabecera
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_seleccionada_id")->fetch_all(MYSQLI_ASSOC);
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }

    // b) Obtenemos los alumnos inscritos
    $alumnos = $conn->query("SELECT i.id as inscripcion_id, u.nombre_completo FROM Inscripciones i JOIN Usuarios u ON i.alumno_id = u.id WHERE i.clase_id = $clase_seleccionada_id ORDER BY u.nombre_completo")->fetch_all(MYSQLI_ASSOC);

    // c) Por cada alumno, llamamos a la función de cálculo
    foreach ($alumnos as $alumno) {
        $inscripcion_id = $alumno['inscripcion_id'];

        // Llamamos a la función unificada
        $data_calificacion = getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales);

        // Guardamos los resultados del alumno
        $reporte_data[] = [
            'nombre' => $alumno['nombre_completo'],
            'data' => $data_calificacion // Guardamos el paquete completo de datos
        ];
    }
}
?>

<h3 class="fs-4 mb-3">Reporte General de Calificaciones</h3>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-filter me-1"></i>Selecciona una materia para ver el reporte</div>
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="view" value="reporte">
            <div class="input-group">
                <select name="clase_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">-- Elige una de tus clases --</option>
                    <?php foreach ($mis_clases as $clase): ?>
                        <option value="<?php echo $clase['id']; ?>" <?php echo ($clase['id'] == $clase_seleccionada_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($clase_seleccionada_id && !empty($reporte_data)): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-chart-bar me-1"></i>Matriz de Calificaciones Finales</div>
    <div class="card-body">
        <div class="tabla-scroll-horizontal">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-dark text-center">
                        <tr>
                            <th rowspan="2" class="align-middle sticky-col">Alumno</th>
                            <?php foreach ($categorias_principales as $cat_nombre): ?>
                                <th colspan="3">
                                    <?php echo $cat_nombre; ?>
                                    <small class="d-block">(<?php echo number_format($ponderaciones[$cat_nombre]['ponderacion'] ?? 0, 2); ?>%)</small>
                                </th>
                            <?php endforeach; ?>

                            <th rowspan="2" class="align-middle">Prom. P1</th>
                            <th rowspan="2" class="align-middle">Prom. P2</th>
                            <th rowspan="2" class="align-middle">Prom. P3</th>

                            <th rowspan="2" class="align-middle">Calificación Final</th>
                        </tr>
                        <tr>
                            <?php foreach ($categorias_principales as $cat_nombre): ?>
                                <th>P1</th>
                                <th>P2</th>
                                <th>P3</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_data as $row): ?>
                        <tr>
                            <td class="fw-bold sticky-col"><?php echo htmlspecialchars($row['nombre']); ?></td>

                            <?php $data = $row['data']; // Obtenemos los datos calculados ?>

                            <?php foreach ($categorias_principales as $cat_nombre): ?>
                                <?php for ($p = 1; $p <= 3; $p++): ?>
                                    <?php
                                    // Buscamos el promedio [cat_nombre][parcial]
                                    $promedio_parcial = $data['promedios_parciales'][$cat_nombre][$p] ?? 0;
                                    ?>
                                    <td class="text-center"><?php echo number_format($promedio_parcial, 2); ?></td>
                                <?php endfor; ?>
                            <?php endforeach; ?>

                            <td class="text-center table-success fw-bold"><?php echo number_format($data['calif_por_parcial'][1], 2); ?></td>
                            <td class="text-center table-success fw-bold"><?php echo number_format($data['calif_por_parcial'][2], 2); ?></td>
                            <td class="text-center table-success fw-bold"><?php echo number_format($data['calif_por_parcial'][3], 2); ?></td>

                            <td class="text-center fw-bold fs-5 text-primary">
                                <?php echo number_format($data['final'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
        </div>
    </div>
</div>
<?php elseif ($clase_seleccionada_id): ?>
    <div class="alert alert-secondary">No hay alumnos inscritos en esta clase para generar un reporte.</div>
<?php endif; ?>