<?php
// --- 1. OBTENER DATOS INICIALES ---
require '../../back/db_connect.php';
$profesor_id = $_SESSION['user_id'];
$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();
$categorias_principales = ['Actividades', 'Asistencia', 'Examenes']; // Definición global

if (!$ciclo_activo) {
    echo '<div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50" role="alert"><span class="font-medium">Atención:</span> No hay un ciclo escolar activo para generar reportes.</div>';
    return;
}
$ciclo_activo_id = $ciclo_activo['id'];

// ===== CONSULTA MODIFICADA: AHORA INCLUYE SUCURSAL =====
$sql_mis_clases = "SELECT c.id, m.nombre_materia, s.nombre_sucursal 
                   FROM Clases c 
                   JOIN Materias m ON c.materia_id = m.id
                   JOIN Sucursales s ON c.sucursal_id = s.id
                   WHERE c.profesor_id = ? AND c.ciclo_id = ?
                   ORDER BY s.nombre_sucursal, m.nombre_materia";

$stmt_clases = $conn->prepare($sql_mis_clases);
$stmt_clases->bind_param("ii", $profesor_id, $ciclo_activo_id);
$stmt_clases->execute();
$mis_clases = $stmt_clases->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_clases->close();
// =======================================================

// Verificamos si se ha seleccionado una clase
$clase_seleccionada_id = isset($_GET['clase_id']) ? (int) $_GET['clase_id'] : null;
$reporte_data = [];
$ponderaciones = []; // Mapa de [cat_nombre] => [datos]

// --- 2. FUNCIÓN DE CÁLCULO (IDÉNTICA A 'Mis Clases') ---
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

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Reporte Ejecutivo</h3>
    <p class="text-zinc-500 font-light text-sm">Resumen de Calificaciones Finales y Promedios</p>
</div>

<!-- Selector de Materia -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8 flex items-center justify-between">
    <div class="flex items-center text-zinc-500 text-sm font-medium">
        <div class="h-8 w-8 bg-zinc-100 rounded-full flex items-center justify-center mr-3">
            <i class="fas fa-filter text-zinc-400"></i>
        </div>
        Selecciona una materia:
    </div>
    <div class="flex-1 max-w-md">
        <form method="GET" class="m-0">
            <input type="hidden" name="view" value="reporte">
            <div class="relative">
                <select name="clase_id"
                    class="appearance-none block w-full px-4 py-3 pr-8 border border-zinc-200 rounded-lg text-sm focus:outline-none focus:border-zinc-900 bg-white text-zinc-800 transition-colors"
                    onchange="this.form.submit()" required>
                    <option value="">-- Elige una de tus clases --</option>
                    <?php foreach ($mis_clases as $clase): ?>
                        <option value="<?php echo $clase['id']; ?>" <?php echo ($clase['id'] == $clase_seleccionada_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                            - <?php echo htmlspecialchars($clase['nombre_sucursal']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-zinc-500">
                    <i class="fas fa-chevron-down text-xs"></i>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($clase_seleccionada_id && !empty($reporte_data)): ?>

    <!-- Matriz de Resultados -->
    <div class="bg-white rounded-xl shadow-lg border border-zinc-100 overflow-hidden relative">
        <div class="px-6 py-4 bg-zinc-950 text-white flex justify-between items-center border-b border-zinc-900">
            <h5 class="font-serif text-lg italic">Matriz de Rendimiento Académico</h5>
            <button onclick="window.print()"
                class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 hover:text-white transition-colors">
                <i class="fas fa-print mr-1"></i> Imprimir
            </button>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-zinc-900 text-white text-[10px] uppercase tracking-wider font-light">
                        <th rowspan="2" class="px-6 py-3 sticky left-0 z-20 bg-zinc-900 border-r border-zinc-800 shadow-xl">
                            Alumno</th>
                        <?php foreach ($categorias_principales as $cat_nombre): ?>
                            <th colspan="3" class="px-4 py-2 text-center border-r border-zinc-800 border-b border-zinc-800">
                                <?php echo $cat_nombre; ?>
                                <span
                                    class="opacity-50 block text-[9px]">(<?php echo number_format($ponderaciones[$cat_nombre]['ponderacion'] ?? 0, 0); ?>%)</span>
                            </th>
                        <?php endforeach; ?>

                        <th rowspan="2" class="px-4 py-3 text-center border-r border-zinc-800 bg-zinc-900/50">Prom. P1</th>
                        <th rowspan="2" class="px-4 py-3 text-center border-r border-zinc-800 bg-zinc-900/50">Prom. P2</th>
                        <th rowspan="2" class="px-4 py-3 text-center border-r border-zinc-800 bg-zinc-900/50">Prom. P3</th>
                        <th rowspan="2" class="px-6 py-3 text-center font-bold bg-zinc-950">Final</th>
                    </tr>
                    <tr class="bg-zinc-900 text-zinc-400 text-[9px] uppercase tracking-wider">
                        <?php foreach ($categorias_principales as $cat_nombre): ?>
                            <th class="px-2 py-1 text-center border-r border-zinc-800">P1</th>
                            <th class="px-2 py-1 text-center border-r border-zinc-800">P2</th>
                            <th class="px-2 py-1 text-center border-r border-zinc-800">P3</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-zinc-100">
                    <?php foreach ($reporte_data as $row): ?>
                        <tr class="hover:bg-zinc-50 transition-colors group">
                            <td
                                class="px-6 py-3 font-medium text-zinc-800 sticky left-0 bg-white group-hover:bg-zinc-50 border-r border-zinc-100 shadow-sm z-10">
                                <?php echo htmlspecialchars($row['nombre']); ?>
                            </td>

                            <?php $data = $row['data']; ?>

                            <?php foreach ($categorias_principales as $cat_nombre): ?>
                                <?php for ($p = 1; $p <= 3; $p++): ?>
                                    <?php $promedio_parcial = $data['promedios_parciales'][$cat_nombre][$p] ?? 0; ?>
                                    <td class="px-2 py-3 text-center border-r border-zinc-50 text-zinc-500 font-mono text-xs">
                                        <?php echo ($promedio_parcial > 0) ? number_format($promedio_parcial, 1) : '-'; ?>
                                    </td>
                                <?php endfor; ?>
                            <?php endforeach; ?>

                            <!-- Promedios Parciales -->
                            <td
                                class="px-4 py-3 text-center border-r border-zinc-100 bg-zinc-50/50 font-mono text-xs text-zinc-600">
                                <?php echo number_format($data['calif_por_parcial'][1], 1); ?>
                            </td>
                            <td
                                class="px-4 py-3 text-center border-r border-zinc-100 bg-zinc-50/50 font-mono text-xs text-zinc-600">
                                <?php echo number_format($data['calif_por_parcial'][2], 1); ?>
                            </td>
                            <td
                                class="px-4 py-3 text-center border-r border-zinc-100 bg-zinc-50/50 font-mono text-xs text-zinc-600">
                                <?php echo number_format($data['calif_por_parcial'][3], 1); ?>
                            </td>

                            <!-- Final -->
                            <?php
                            $final = $data['final'];
                            // Lógica de color condicional
                            $finalClass = ($final < 7.5) ? 'text-rose-600' : 'text-zinc-900';
                            if ($final == 0)
                                $finalClass = 'text-zinc-300';
                            ?>
                            <td
                                class="px-6 py-3 text-center bg-zinc-50 border-l border-zinc-200 font-serif font-bold text-lg <?php echo $finalClass; ?>">
                                <?php echo number_format($final, 1); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($clase_seleccionada_id): ?>
    <div class="p-12 text-center border rounded-xl bg-zinc-50 border-zinc-200">
        <i class="fas fa-users-slash text-4xl text-zinc-300 mb-4"></i>
        <p class="text-zinc-500">No hay alumnos inscritos en esta clase para generar un reporte.</p>
    </div>
<?php endif; ?>