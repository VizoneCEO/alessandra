<?php
// --- 1. OBTENER DATOS INICIALES ---
require '../../back/db_connect.php';
$profesor_id = $_SESSION['user_id'];
$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();

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

if ($clase_seleccionada_id) {
    // --- 2. SI SE SELECCIONÓ UNA CLASE, HACEMOS LA MAGIA ---

    // a) Obtenemos las ponderaciones
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_seleccionada_id")->fetch_all(MYSQLI_ASSOC);
    $ponderaciones = [];
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }

    // b) Obtenemos los alumnos inscritos
    $alumnos = $conn->query("SELECT i.id as inscripcion_id, u.nombre_completo FROM Inscripciones i JOIN Usuarios u ON i.alumno_id = u.id WHERE i.clase_id = $clase_seleccionada_id ORDER BY u.nombre_completo")->fetch_all(MYSQLI_ASSOC);

    // c) Por cada alumno, calculamos sus promedios y calificación final
    foreach ($alumnos as $alumno) {
        $calificacion_final = 0;
        $promedios_alumno = [];

        foreach (['Actividades', 'Asistencia', 'Examenes'] as $cat_nombre) {
            $promedio_categoria = 0;
            if (isset($ponderaciones[$cat_nombre])) {
                $categoria_id = $ponderaciones[$cat_nombre]['id'];

                // Obtenemos todas las calificaciones del alumno en esta categoría
                $sql_califs = "SELECT c.calificacion_obtenida 
                               FROM Calificaciones c
                               JOIN Actividades_Evaluables a ON c.actividad_id = a.id
                               WHERE c.inscripcion_id = {$alumno['inscripcion_id']} AND a.categoria_id = $categoria_id";

                $calificaciones = $conn->query($sql_califs)->fetch_all(MYSQLI_ASSOC);

                if (!empty($calificaciones)) {
                    $suma_califs = array_sum(array_column($calificaciones, 'calificacion_obtenida'));
                    $promedio_categoria = $suma_califs / count($calificaciones);
                }
            }
            // Guardamos el promedio de la categoría para mostrarlo
            $promedios_alumno[$cat_nombre] = $promedio_categoria;
            // Sumamos a la calificación final, aplicando la ponderación
            $calificacion_final += $promedio_categoria * (($ponderaciones[$cat_nombre]['ponderacion'] ?? 0) / 100);
        }

        // Guardamos los resultados del alumno
        $reporte_data[] = [
            'nombre' => $alumno['nombre_completo'],
            'promedios' => $promedios_alumno,
            'final' => $calificacion_final
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
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Alumno</th>
                        <?php foreach (['Actividades', 'Asistencia', 'Examenes'] as $cat_nombre): ?>
                            <th class="text-center">
                                <?php echo $cat_nombre; ?>
                                <small class="d-block">(<?php echo number_format($ponderaciones[$cat_nombre]['ponderacion'] ?? 0, 2); ?>%)</small>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-center">Calificación Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte_data as $row): ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td class="text-center"><?php echo number_format($row['promedios']['Actividades'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($row['promedios']['Asistencia'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($row['promedios']['Examenes'], 2); ?></td>
                        <td class="text-center fw-bold fs-5 text-primary"><?php echo number_format($row['final'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($clase_seleccionada_id): ?>
    <div class="alert alert-secondary">No hay alumnos inscritos en esta clase para generar un reporte.</div>
<?php endif; ?>