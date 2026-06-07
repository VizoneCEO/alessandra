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
    $es_activo = ($row['ciclo_id'] == $ciclo_activo_id);

    if (!$es_activo) {
        if ($row['calificacion_final'] !== null) {
            // Opción A: La calificación ya es "final" y está guardada (ciclo inactivo).
            $calif = (float) $row['calificacion_final'];
        } else {
            // Opción B: No tiene calificación final guardada, pero el ciclo es inactivo.
            $data = getDetalleCalificacion($conn, $row['inscripcion_id'], $categorias_principales);

            // Verificamos si hay alguna actividad evaluada en cualquier parcial
            $total_items = 0;
            foreach ($categorias_principales as $cat) {
                $total_items += count($data['items_desglose'][$cat][1] ?? []);
                $total_items += count($data['items_desglose'][$cat][2] ?? []);
                $total_items += count($data['items_desglose'][$cat][3] ?? []);
            }

            // Si hay al menos un ítem evaluado, mostramos la calificación acumulada
            if ($total_items > 0) {
                $calif = (float) $data['final'];
            }
        }
    }

    if (!isset($calificaciones_alumno[$materia_id])) {
        $calificaciones_alumno[$materia_id] = [
            'calif' => $calif,
            'inscripcion_id' => $row['inscripcion_id']
        ];
    } else {
        if ($es_activo) {
            // Si está cursando actualmente, priorizamos mostrar el detalle de la cursada activa
            // y anulamos la calificación final para que no cuente en el promedio ni se muestre
            $calificaciones_alumno[$materia_id] = [
                'calif' => null,
                'inscripcion_id' => $row['inscripcion_id']
            ];
        } else {
            // Si es otro ciclo inactivo, nos quedamos con la calificación más alta
            if ($calif !== null && $calif > $calificaciones_alumno[$materia_id]['calif']) {
                $calificaciones_alumno[$materia_id] = [
                    'calif' => $calif,
                    'inscripcion_id' => $row['inscripcion_id']
                ];
            }
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
$total_materias_calificadas = 0;
foreach ($calificaciones_alumno as $m_id => $data) {
    if ($data['calif'] !== null) {
        $promedio_general += $data['calif'];
        $total_materias_calificadas++;
    }
}
if ($total_materias_calificadas > 0) {
    $promedio_general = $promedio_general / $total_materias_calificadas;
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
                    <div class="flex items-center gap-3">
                        <h5 class="text-sm font-bold uppercase tracking-widest text-zinc-800">Semestre <?php echo $i; ?></h5>
                        <a href="print_boleta.php?semestre=<?php echo $i; ?>" target="_blank" class="w-6 h-6 flex items-center justify-center rounded bg-zinc-200/50 hover:bg-sky-500 hover:text-white text-zinc-400 text-[10px] transition-colors" title="Imprimir Boleta">
                            <i class="fas fa-print"></i>
                        </a>
                    </div>
                    <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                </div>

                <div class="flex-1 p-0">
                    <ul class="divide-y divide-zinc-50">
                        <?php if (isset($plan_estudios[$i])): ?>
                            <?php foreach ($plan_estudios[$i] as $materia): ?>

                                <?php
                                // Buscamos si el alumno tiene calificación FINAL para esta materia
                                $materia_data = $calificaciones_alumno[$materia['id']] ?? null;
                                $calificacion = $materia_data['calif'] ?? null;
                                $inscripcion_id = $materia_data['inscripcion_id'] ?? null;
                                $status_class = '';

                                if ($calificacion !== null) {
                                    // ===== LÓGICA DE COLOR DE MATERIA MODIFICADA =====
                                    $status_class = ($calificacion >= 7.5) ? 'text-zinc-900 font-bold' : 'text-rose-600 font-bold';
                                } else {
                                    $calificacion = '--';
                                    $status_class = 'text-zinc-300 font-light';
                                }
                                ?>

                                <li class="px-6 py-4 flex flex-col hover:bg-zinc-50/50 transition-colors">
                                    <div class="flex justify-between items-center w-full">
                                        <span class="text-sm text-zinc-600 font-light flex-1"><?php echo htmlspecialchars($materia['nombre_materia']); ?></span>
                                        <div class="flex items-center gap-4">
                                            <span class="font-mono text-sm <?php echo $status_class; ?>">
                                                <?php echo ($calificacion !== '--') ? number_format($calificacion, 1) : $calificacion; ?>
                                            </span>
                                            <?php if ($inscripcion_id !== null): ?>
                                                <button type="button" 
                                                        class="text-zinc-400 hover:text-sky-500 transition-colors focus:outline-none"
                                                        onclick="toggleDetails('detalles-<?php echo $inscripcion_id; ?>')"
                                                        title="Ver Detalles">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                            <?php else: ?>
                                                <div class="w-[14px]"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($inscripcion_id !== null): ?>
                                        <?php 
                                            // Obtener detalles de la calificación
                                            $data_calificacion = getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales);
                                            $calif_por_parcial = $data_calificacion['calif_por_parcial'];
                                            $promedios_parciales = $data_calificacion['promedios_parciales'];
                                            $items_desglose = $data_calificacion['items_desglose'];
                                        ?>
                                        <div id="detalles-<?php echo $inscripcion_id; ?>" class="hidden mt-4 bg-white border border-zinc-100 rounded-lg shadow-sm overflow-hidden w-full">
                                            <table class="w-full text-left text-sm">
                                                <!-- Categorías Loop -->
                                                <?php foreach ($categorias_principales as $cat_nombre): ?>
                                                    <?php
                                                    $cat_slug = strtolower($cat_nombre);
                                                    $cat_icon = ['Actividades' => 'fa-tasks', 'Asistencia' => 'fa-user-check', 'Examenes' => 'fa-file-alt'][$cat_nombre];
                                                    ?>
                                                    <tr class="bg-zinc-50/50 text-xs uppercase tracking-wide text-zinc-400 font-semibold">
                                                        <td colspan="3" class="px-6 py-2 pt-4">
                                                            <i class="fas <?php echo $cat_icon; ?> mr-1 opacity-70"></i> 
                                                            <?php echo $cat_nombre; ?>
                                                        </td>
                                                    </tr>

                                                    <?php for ($p = 1; $p <= 3; $p++): ?>
                                                        <?php
                                                        $promedio = $promedios_parciales[$cat_nombre][$p] ?? 0;
                                                        $items = $items_desglose[$cat_nombre][$p] ?? [];
                                                        $titulo_modal = "$cat_nombre - Parcial $p";
                                                        ?>
                                                        <tr class="border-b border-zinc-50 last:border-0 hover:bg-zinc-50 transition-colors">
                                                            <td class="px-6 py-2 text-zinc-600 font-light w-1/3">Parcial <?php echo $p; ?></td>
                                                            
                                                            <!-- Botón 'Ver' como Ojo Minimalista -->
                                                            <td class="px-6 py-2 text-center w-1/3">
                                                                <button type="button" 
                                                                        class="text-zinc-300 hover:text-zinc-800 transition-colors focus:outline-none"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#califModalHistorial"
                                                                        data-titulo="<?php echo htmlspecialchars($titulo_modal); ?>"
                                                                        data-items='<?php echo json_encode($items); ?>'
                                                                        <?php echo empty($items) ? 'disabled' : ''; ?>
                                                                        title="Ver Detalles">
                                                                    <i class="fas fa-eye <?php echo empty($items) ? 'opacity-30 cursor-not-allowed' : ''; ?>"></i>
                                                                </button>
                                                            </td>
                                                            
                                                            <td class="px-6 py-2 text-right">
                                                                <span class="font-mono text-zinc-700 font-medium">
                                                                    <?php echo number_format($promedio, 1); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endfor; ?>
                                                <?php endforeach; ?>
                                                
                                                <!-- Totales SECTION -->
                                                <tr class="bg-zinc-100/50 mt-2">
                                                    <td colspan="3" class="px-6 py-3 border-t border-zinc-100">
                                                        <p class="text-xs uppercase tracking-widest text-zinc-400 font-bold mb-2">Promedios Parciales</p>
                                                        <div class="flex justify-between text-xs font-mono text-zinc-600">
                                                            <span>P1: <strong class="text-zinc-900"><?php echo number_format($calif_por_parcial[1], 1); ?></strong></span>
                                                            <span>P2: <strong class="text-zinc-900"><?php echo number_format($calif_por_parcial[2], 1); ?></strong></span>
                                                            <span>P3: <strong class="text-zinc-900"><?php echo number_format($calif_por_parcial[3], 1); ?></strong></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    <?php endif; ?>
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

<!-- MODAL PARA HISTORIAL (ESTILO BOOTSTRAP POR COMPATIBILIDAD JS, PERO ESTILIZADO LIMPIO) -->
<div class="modal fade" id="califModalHistorial" tabindex="-1" aria-labelledby="modalTituloHistorial" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-2xl rounded-xl overflow-hidden">
      <div class="modal-header bg-zinc-950 text-white border-0 py-4">
        <h5 class="modal-title font-serif italic" id="modalTituloHistorial">Desglose</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <ul class="list-none m-0 p-0" id="modalContenidoHistorial">
          <!-- JS llena esto -->
        </ul>
      </div>
      <div class="modal-footer border-t border-zinc-100 py-3 bg-zinc-50">
        <button type="button" class="px-4 py-2 bg-white border border-zinc-300 text-zinc-600 text-xs uppercase tracking-widest hover:bg-zinc-100 rounded transition" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
function toggleDetails(id) {
    const el = document.getElementById(id);
    if (el) {
        if (el.classList.contains('hidden')) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var califModal = document.getElementById('califModalHistorial');

    if (califModal) {
        // Escuchamos el evento 'show' de Bootstrap
        califModal.addEventListener('show.bs.modal', function (event) {

            // 1. Obtener el botón que disparó el modal
            var button = event.relatedTarget;

            // 2. Extraer los datos de los atributos data-*
            var titulo = button.getAttribute('data-titulo');
            var itemsJson = button.getAttribute('data-items');
            var items = JSON.parse(itemsJson);

            // 3. Obtener los elementos del modal
            var modalTitle = califModal.querySelector('.modal-title');
            var modalBodyList = califModal.querySelector('#modalContenidoHistorial');

            // 4. Limpiar el contenido anterior
            modalTitle.textContent = titulo;
            modalBodyList.innerHTML = ''; // Limpiamos la lista

            // 5. Construir el nuevo contenido
            if (items.length > 0) {
                items.forEach(function(item) {
                    var li = document.createElement('li');
                    li.className = 'flex justify-between items-center px-6 py-4 border-b border-zinc-100 last:border-0 hover:bg-zinc-50';

                    var nombreSpan = document.createElement('span');
                    nombreSpan.className = 'text-zinc-700 font-light text-sm';
                    nombreSpan.textContent = item.nombre;

                    var califSpan = document.createElement('span');
                    // Estilo minimalista para el badge 
                    califSpan.className = 'font-mono font-bold text-zinc-900 text-sm';
                    califSpan.textContent = parseFloat(item.calif).toFixed(1);

                    li.appendChild(nombreSpan);
                    li.appendChild(califSpan);
                    modalBodyList.appendChild(li);
                });
            } else {
                var li = document.createElement('li');
                li.className = 'px-6 py-4 text-center text-zinc-400 italic text-sm';
                li.textContent = 'No hay actividades registradas para este parcial.';
                modalBodyList.appendChild(li);
            }
        });
    }
});
</script>