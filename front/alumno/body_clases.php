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
            $data_calificacion = getDetalleCalificacion($conn, $clase['inscripcion_id'], $categorias_principales);

            $calif_final = $data_calificacion['final'];
            $promedios_parciales = $data_calificacion['promedios_parciales'];
            $items_desglose = $data_calificacion['items_desglose'];
            $calif_por_parcial = $data_calificacion['calif_por_parcial']; // <-- NUEVO DATO
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

                            <?php foreach ($categorias_principales as $cat_nombre): ?>
                                <?php
                                $cat_slug = strtolower($cat_nombre);
                                $cat_icon = ['Actividades' => 'fa-tasks', 'Asistencia' => 'fa-user-check', 'Examenes' => 'fa-file-alt'][$cat_nombre];
                                ?>
                                <li class="list-group-item list-group-item-light d-flex justify-content-between align-items-center">
                                    <strong>
                                        <i class="fas <?php echo $cat_icon; ?> me-2 text-muted"></i>
                                        <?php echo $cat_nombre; ?>
                                    </strong>
                                </li>

                                <?php // Bucle de los 3 parciales para esta categoría ?>
                                <?php for ($p = 1; $p <= 3; $p++): ?>
                                    <?php
                                    $promedio = $promedios_parciales[$cat_nombre][$p] ?? 0;
                                    $items = $items_desglose[$cat_nombre][$p] ?? [];
                                    $titulo_modal = "$cat_nombre - Parcial $p";
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center ps-4">
                                        <div>
                                            Parcial <?php echo $p; ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-2"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#califModal"
                                                    data-titulo="<?php echo htmlspecialchars($titulo_modal); ?>"
                                                    data-items='<?php echo json_encode($items); ?>'
                                                    <?php echo empty($items) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-eye"></i> Ver
                                            </button>
                                        </div>
                                        <span class="badge bg-primary rounded-pill fs-6">
                                            <?php echo number_format($promedio, 1); ?>
                                        </span>
                                    </li>
                                <?php endfor; ?>
                            <?php endforeach; ?>

                            <li class="list-group-item list-group-item-light d-flex justify-content-between align-items-center">
                                <strong>
                                    <i class="fas fa-calculator me-2 text-muted"></i>
                                    Totales Parciales
                                </strong>
                            </li>

                            <li class="list-group-item d-flex justify-content-between align-items-center ps-4">
                                Calificación Parcial 1
                                <span class="badge bg-success rounded-pill fs-6">
                                    <?php echo number_format($calif_por_parcial[1], 1); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center ps-4">
                                Calificación Parcial 2
                                <span class="badge bg-success rounded-pill fs-6">
                                    <?php echo number_format($calif_por_parcial[2], 1); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center ps-4">
                                Calificación Parcial 3
                                <span class="badge bg-success rounded-pill fs-6">
                                    <?php echo number_format($calif_por_parcial[3], 1); ?>
                                </span>
                            </li>
                            <li class="list-group-item text-center bg-light">
                                <h6 class="mb-1 text-muted">Calificación Obtenida al Momento</h6>
                                <h2 class="fw-bold text-primary mb-0"><?php echo number_format($calif_final, 1); ?></h2>
                            </li>
                        </ul>
                    </div>
                    </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal fade" id="califModal" tabindex="-1" aria-labelledby="modalTitulo" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitulo">Desglose de Calificaciones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="modalSubtitulo"></p>
        <ul class="list-group" id="modalContenido">
          </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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
                li.className = 'list-group-item d-flex justify-content-between align-items-center';

                var nombreSpan = document.createElement('span');
                nombreSpan.textContent = item.nombre;

                var califSpan = document.createElement('span');
                califSpan.className = 'badge bg-dark rounded-pill';
                califSpan.textContent = parseFloat(item.calif).toFixed(1);

                li.appendChild(nombreSpan);
                li.appendChild(califSpan);
                modalBodyList.appendChild(li);
            });
        } else {
            // Esto no debería pasar si el botón está deshabilitado, pero por si acaso
            var li = document.createElement('li');
            li.className = 'list-group-item text-muted';
            li.textContent = 'No hay actividades registradas para este parcial.';
            modalBodyList.appendChild(li);
        }
    });
});
</script>