<?php
// --- 1. OBTENER DATOS INICIALES ---
require '../../back/db_connect.php';
$alumno_id = $_SESSION['user_id'];
$categorias_principales = ['Actividades', 'Asistencia', 'Examenes'];

/**
 * Función MEJORADA para calcular la calificación actual de una clase
 * Y obtener el desglose de promedios Y las actividades individuales por parcial.
 * * @return array [
 * 'final' => 0.0,
 * 'promedios_parciales' => [cat_nombre][parcial] => prom,
 * 'items_desglose' => [cat_nombre][parcial] => [['nombre'=>'T1', 'calif'=>9], ...],
 * 'calif_por_parcial' => [1 => 0.0, 2 => 0.0, 3 => 0.0]
 * ]
 */
function getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales) {

    // 1. Obtenemos el clase_id de esta inscripción
    $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
    if ($clase_id_result->num_rows == 0) {
        return ['final' => 0, 'promedios_parciales' => [], 'items_desglose' => [], 'calif_por_parcial' => [1=>0, 2=>0, 3=>0]];
    }
    $clase_id = $clase_id_result->fetch_assoc()['clase_id'];

    // 2. Obtenemos las ponderaciones
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_id")->fetch_all(MYSQLI_ASSOC);
    $ponderaciones = [];
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }

    // 3. Preparamos la estructura de datos que devolveremos
    $data_return = [
        'final' => 0.0,
        'promedios_parciales' => [],
        'items_desglose' => [],
        'calif_por_parcial' => [1 => 0.0, 2 => 0.0, 3 => 0.0] // <-- NUEVO
    ];

    // 4. Iteramos por cada categoría principal (Actividades, Asistencia, Examenes)
    foreach ($categorias_principales as $cat_nombre) {

        $cat_id = $ponderaciones[$cat_nombre]['id'] ?? 0;

        // Inicializamos los arrays para esta categoría
        for ($p = 1; $p <= 3; $p++) {
            $data_return['promedios_parciales'][$cat_nombre][$p] = 0;
            $data_return['items_desglose'][$cat_nombre][$p] = [];
        }

        if ($cat_id > 0) {
            // 5. Obtenemos TODAS las calificaciones y actividades de esta categoría para este alumno
            $sql_items = "SELECT 
                            a.parcial, 
                            a.nombre_actividad, 
                            c.calificacion_obtenida 
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

                // Guardamos para el desglose del modal
                $data_return['items_desglose'][$cat_nombre][$parcial][] = [
                    'nombre' => $item['nombre_actividad'],
                    'calif' => $calif
                ];
                // Guardamos para calcular promedios
                $califs_por_parcial[$parcial][] = $calif;
            }
            $stmt->close();

            // 6. Calculamos promedios por parcial
            for ($p = 1; $p <= 3; $p++) {
                if (count($califs_por_parcial[$p]) > 0) {
                    $data_return['promedios_parciales'][$cat_nombre][$p] = array_sum($califs_por_parcial[$p]) / count($califs_por_parcial[$p]);
                }
            }
        }

        // 7. (YA NO USAMOS ESTO PARA LA FINAL, PERO ES NECESARIO PARA EL BUCLE)
    }

    // 8. --- Calculamos los totales ponderados por parcial ---
    for ($p = 1; $p <= 3; $p++) {
        foreach ($categorias_principales as $cat_nombre) {
            $ponderacion = ($ponderaciones[$cat_nombre]['ponderacion'] ?? 0) / 100;
            $promedio = $data_return['promedios_parciales'][$cat_nombre][$p] ?? 0;
            // Sumamos el (promedio * ponderación) al total de ESE parcial
            $data_return['calif_por_parcial'][$p] += ($promedio * $ponderacion);
        }
    }

    // 9. --- LÓGICA DE CÁLCULO FINAL CORREGIDA (IDÉNTICA A REPORTE_PROFESOR) ---
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

// -------------------------------------------------
// --- INICIO DE LA LÓGICA DE LA PÁGINA ---
// -------------------------------------------------

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

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Mis Clases</h3>
    <p class="text-zinc-500 font-light text-sm">Ciclo Escolar Actual</p>
</div>

<?php if (!$ciclo_activo): ?>
    <div class="p-4 bg-amber-50 text-amber-800 border-l-4 border-amber-500 rounded text-sm">No hay ningún ciclo escolar activo en este momento.</div>
<?php elseif (empty($mis_clases)): ?>
    <div class="p-4 bg-white shadow-sm rounded border-l-4 border-zinc-300 text-zinc-600 text-sm">No estás inscrito a ninguna clase en el ciclo escolar activo.</div>
<?php else: ?>
    <!-- GRID DE TARJETAS TAILWIND -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($mis_clases as $clase): ?>
            <?php
            // Calculamos la calificación actual y el desglose
            $data_calificacion = getDetalleCalificacion($conn, $clase['inscripcion_id'], $categorias_principales);

            $calif_final = $data_calificacion['final'];
            $promedios_parciales = $data_calificacion['promedios_parciales'];
            $items_desglose = $data_calificacion['items_desglose'];
            $calif_por_parcial = $data_calificacion['calif_por_parcial'];

            // ===== LÓGICA DE COLOR AÑADIDA (Minimalista) =====
            // Si es menor a 7.5 (el 7.5 ya pasa), se pone rosa rojizo
            $final_calif_class = ($calif_final < 7.5) ? 'text-rose-600' : 'text-emerald-700'; // O text-zinc-900 si prefieres neutral
            ?>
            <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-shadow duration-300 flex flex-col h-full overflow-hidden border border-zinc-50">
                <!-- Card Header Limpio -->
                <div class="p-6 border-b border-zinc-50">
                    <h5 class="font-serif text-xl font-bold text-zinc-900 mb-1 leading-tight">
                        <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                    </h5>
                    <p class="text-sm italic text-zinc-500 font-light flex items-center">
                        <i class="fas fa-chalkboard-teacher mr-2 text-zinc-300"></i>
                        <?php echo htmlspecialchars($clase['profesor_nombre']); ?>
                    </p>
                </div>

                <div class="flex-1 p-0">
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
                                                data-bs-target="#califModal"
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
                
                <!-- Footer con Calificación Final -->
                <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-between items-center">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold uppercase tracking-widest text-zinc-400">Promedio Final</span>
                        <span class="text-[10px] text-emerald-600 font-medium mt-1 leading-tight max-w-[150px]">Promedio acumulado hasta el momento. El promedio final se conforma de los 3 parciales.</span>
                    </div>
                    <span class="text-3xl font-serif font-bold <?php echo $final_calif_class; ?>">
                        <?php echo number_format($calif_final, 1); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODAL (ESTILO BOOTSTRAP POR COMPATIBILIDAD JS, PERO ESTILIZADO LIMPIO) -->
<div class="modal fade" id="califModal" tabindex="-1" aria-labelledby="modalTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-2xl rounded-xl overflow-hidden">
      <div class="modal-header bg-zinc-950 text-white border-0 py-4">
        <h5 class="modal-title font-serif italic" id="modalTitulo">Desglose</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <ul class="list-none m-0 p-0" id="modalContenido">
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
document.addEventListener('DOMContentLoaded', function() {
    var califModal = document.getElementById('califModal');

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
        var modalBodyList = califModal.querySelector('#modalContenido');

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
            // Esto no debería pasar si el botón está deshabilitado, pero por si acaso
            var li = document.createElement('li');
            li.className = 'px-6 py-4 text-center text-zinc-400 italic text-sm';
            li.textContent = 'No hay actividades registradas para este parcial.';
            modalBodyList.appendChild(li);
        }
    });
});
</script>