<?php
// --- 1. OBTENER DATOS ---
require '../../back/db_connect.php';
$alumno_id = $_SESSION['user_id'];
$categorias_principales = ['Actividades', 'Asistencia', 'Examenes'];

// --- 2. FUNCIÓN AUXILIAR PARA CALCULAR PROMEDIOS (IDÉNTICA A 'Mis Clases') ---
function getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales)
{
    $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
    if ($clase_id_result->num_rows == 0) {
        return ['final' => 0, 'promedios_parciales' => [], 'items_desglose' => [], 'calif_por_parcial' => [1 => 0, 2 => 0, 3 => 0]];
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
                $calif = (float) $item['calificacion_obtenida'];
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
    for ($p = 1; $p <= 3; $p++) {
        foreach ($categorias_principales as $cat_nombre) {
            $ponderacion = ($ponderaciones[$cat_nombre]['ponderacion'] ?? 0) / 100;
            $promedio = $data_return['promedios_parciales'][$cat_nombre][$p] ?? 0;
            $data_return['calif_por_parcial'][$p] += ($promedio * $ponderacion);
        }
    }
    $suma_parciales = $data_return['calif_por_parcial'][1] + $data_return['calif_por_parcial'][2] + $data_return['calif_por_parcial'][3];
    if ($suma_parciales > 0) {
        $data_return['final'] = $suma_parciales / 3;
    } else {
        $data_return['final'] = 0.0;
    }
    return $data_return;
}

// --- 3. OBTENER EL CICLO ACTIVO ---
$ciclo_activo_id = null;
$ciclo_activo_result = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1");
if ($ciclo_activo_result->num_rows > 0) {
    $ciclo_activo_id = $ciclo_activo_result->fetch_assoc()['id'];
}

// --- 4. OBTENER TODAS LAS INSCRIPCIONES DEL ALUMNO ---
$sql_inscripciones = "SELECT 
                            c.materia_id,
                            i.id as inscripcion_id,
                            i.calificacion_final,
                            c.ciclo_id
                       FROM Inscripciones i
                       JOIN Clases c ON i.clase_id = c.id
                       WHERE i.alumno_id = ?";
$stmt = $conn->prepare($sql_inscripciones);
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$result_inscripciones = $stmt->get_result();
$calificaciones_alumno = [];

while ($row = $result_inscripciones->fetch_assoc()) {
    $materia_id = $row['materia_id'];
    $calif = null;

    if ($row['calificacion_final'] !== null) {
        // Opción A: La calificación ya es "final" y está guardada.
        $calif = (float) $row['calificacion_final'];
    } elseif ($row['ciclo_id'] == $ciclo_activo_id) {
        // Opción B: Es del ciclo actual. Calculamos para ver si está "completa".
        $data = getDetalleCalificacion($conn, $row['inscripcion_id'], $categorias_principales);

        // --- ESTA ES LA LÓGICA ---
        // Verificamos si hay actividades en CADA UNO de los 3 parciales.
        $p1_items_count = 0;
        $p2_items_count = 0;
        $p3_items_count = 0;
        foreach ($categorias_principales as $cat) {
            $p1_items_count += count($data['items_desglose'][$cat][1] ?? []);
            $p2_items_count += count($data['items_desglose'][$cat][2] ?? []);
            $p3_items_count += count($data['items_desglose'][$cat][3] ?? []);
        }

        // Si hay items en los 3 parciales, usamos la calificación final calculada
        if ($p1_items_count > 0 && $p2_items_count > 0 && $p3_items_count > 0) {
            $calif = (float) $data['final'];
        }
        // Si no (ej. solo P1 y P2 están calificados), $calif sigue siendo NULL y mostrará '--'
    }

    // Si encontramos una calificación (final o completa), la procesamos
    if ($calif !== null) {
        if (!isset($calificaciones_alumno[$materia_id]) || $calif > $calificaciones_alumno[$materia_id]) {
            $calificaciones_alumno[$materia_id] = $calif;
        }
    }
}
$stmt->close();

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

// ===== NUEVA LÓGICA DE COLOR PARA PROMEDIO GENERAL =====
// Si es menor a 8 (el 8 ya pasa), se pone rosa
$gpa_class = ($promedio_general < 8.0) ? 'text-rose-600' : 'text-zinc-900';
// ========================================================
?>

<!-- Header y Widget GPA -->
<div class="flex flex-col md:flex-row items-end justify-between mb-10 pb-6 border-b border-zinc-200">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Historial Académico</h3>
        <p class="text-zinc-500 font-light text-sm">Avance de Carrera (Tira de Materias)</p>
    </div>

    <!-- Widget GPA Minimalista -->
    <div class="mt-6 md:mt-0 flex items-center bg-white border border-zinc-100 shadow-sm rounded-lg px-6 py-4">
        <div class="mr-4 text-right">
            <p class="text-[10px] uppercase tracking-widest text-zinc-400 font-bold">Promedio Global</p>
            <p class="text-xs text-zinc-300 font-light">Acumulado</p>
        </div>
        <div class="text-4xl font-serif font-bold <?php echo $gpa_class; ?>">
            <?php echo number_format($promedio_general, 2); ?>
        </div>
    </div>
</div>

<?php if (empty($plan_estudios)): ?>
    <div class="p-4 bg-zinc-100 text-zinc-600 rounded text-sm italic">No hay un plan de estudios (catálogo de materias)
        registrado en el sistema.</div>
<?php else: ?>
    <!-- GRID DE SEMESTRES (Timeline Vertical) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <div
                class="bg-white rounded-lg border border-zinc-100 shadow-sm hover:shadow-md transition-shadow duration-300 flex flex-col h-full">
                <!-- Header Semestre -->
                <div class="px-6 py-4 bg-zinc-50 border-b border-zinc-100 flex items-center justify-between">
                    <h5 class="text-sm font-bold uppercase tracking-widest text-zinc-800">Semestre <?php echo $i; ?></h5>
                    <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                </div>

                <div class="flex-1 p-0">
                    <ul class="divide-y divide-zinc-50">
                        <?php if (isset($plan_estudios[$i])): ?>
                            <?php foreach ($plan_estudios[$i] as $materia): ?>

                                <?php
                                // Buscamos si el alumno tiene calificación FINAL para esta materia
                                $calificacion = $calificaciones_alumno[$materia['id']] ?? null;
                                $status_class = '';

                                if ($calificacion !== null) {
                                    // ===== LÓGICA DE COLOR DE MATERIA MODIFICADA =====
                                    $status_class = ($calificacion >= 7.5) ? 'text-zinc-900 font-bold' : 'text-rose-600 font-bold';
                                } else {
                                    $calificacion = '--';
                                    $status_class = 'text-zinc-300 font-light';
                                }
                                ?>

                                <li class="px-6 py-4 flex justify-between items-center hover:bg-zinc-50/50 transition-colors">
                                    <span
                                        class="text-sm text-zinc-600 font-light"><?php echo htmlspecialchars($materia['nombre_materia']); ?></span>
                                    <span class="font-mono text-sm <?php echo $status_class; ?>">
                                        <?php echo ($calificacion !== '--') ? number_format($calificacion, 1) : $calificacion; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="px-6 py-4 text-xs text-zinc-400 italic">No hay materias registradas.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endfor; ?>
    </div>
<?php endif; ?>