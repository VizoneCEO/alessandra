<?php
// --- 1. OBTENER DATOS DE FORMA EFICIENTE ---
require '../../back/db_connect.php';
$profesor_id = $_SESSION['user_id'];
$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();

if (!$ciclo_activo) {
    echo '<div class="alert alert-warning">No hay un ciclo escolar activo.</div>';
    return;
}
$ciclo_activo_id = $ciclo_activo['id'];
$mis_clases = $conn->query("SELECT c.id, m.nombre_materia FROM Clases c JOIN Materias m ON c.materia_id = m.id WHERE c.profesor_id = $profesor_id AND c.ciclo_id = $ciclo_activo_id")->fetch_all(MYSQLI_ASSOC);
$clase_seleccionada_id = isset($_GET['clase_id']) ? (int)$_GET['clase_id'] : null;

if ($clase_seleccionada_id) {
    // Obtenemos los datos de la clase para trabajar
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_seleccionada_id")->fetch_all(MYSQLI_ASSOC);
    $categorias = [];
    foreach($categorias_db as $cat) {
        $categorias[$cat['nombre_categoria']] = $cat;
    }

    $alumnos = $conn->query("SELECT i.id as inscripcion_id, u.nombre_completo FROM Inscripciones i JOIN Usuarios u ON i.alumno_id = u.id WHERE i.clase_id = $clase_seleccionada_id ORDER BY u.nombre_completo")->fetch_all(MYSQLI_ASSOC);

    // --- FUNCIÓN OPTIMIZADA PARA OBTENER DATOS ---
    function get_items_y_calificaciones($conn, $categoria_id, $alumnos) {
        if (!$categoria_id || empty($alumnos)) return ['items' => [], 'calificaciones' => []];

        $items = $conn->query("SELECT * FROM Actividades_Evaluables WHERE categoria_id = $categoria_id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
        if (empty($items)) return ['items' => [], 'calificaciones' => []];

        $inscripcion_ids = array_column($alumnos, 'inscripcion_id');
        $item_ids = array_column($items, 'id');
        $sql_calif = "SELECT inscripcion_id, actividad_id, calificacion_obtenida 
                      FROM Calificaciones 
                      WHERE inscripcion_id IN (". implode(',', $inscripcion_ids) .") 
                      AND actividad_id IN (". implode(',', $item_ids) .")";

        $calificaciones_result = $conn->query($sql_calif);
        $calificaciones = [];
        while ($nota = $calificaciones_result->fetch_assoc()) {
            $calificaciones[$nota['inscripcion_id']][$nota['actividad_id']] = $nota['calificacion_obtenida'];
        }

        return ['items' => $items, 'calificaciones' => $calificaciones];
    }

    $data_actividades = get_items_y_calificaciones($conn, $categorias['Actividades']['id'] ?? null, $alumnos);
    $data_asistencia = get_items_y_calificaciones($conn, $categorias['Asistencia']['id'] ?? null, $alumnos);
    $data_examenes = get_items_y_calificaciones($conn, $categorias['Examenes']['id'] ?? null, $alumnos);
}
?>

<h3 class="fs-4 mb-3">Mis Clases</h3>
<?php if (isset($_SESSION['message'])) { echo '<div class="alert alert-'.$_SESSION['message']['type'].' alert-dismissible fade show" role="alert">'.htmlspecialchars($_SESSION['message']['text']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; unset($_SESSION['message']); } ?>

<?php if (!$clase_seleccionada_id): ?>
    <div class="list-group">
        <?php foreach ($mis_clases as $clase) { echo "<a href='dashboard.php?view=clases&clase_id={$clase['id']}' class='list-group-item list-group-item-action'>Gestionar: <strong>".htmlspecialchars($clase['nombre_materia'])."</strong></a>"; } ?>
    </div>
<?php else: ?>
    <a href="dashboard.php?view=clases" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left me-2"></i>Volver a mis clases</a>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-balance-scale me-2"></i>Paso 1: Define las Ponderaciones</div>
        <div class="card-body">
            <form action="../../back/profesor_actions.php" method="POST">
                <input type="hidden" name="action" value="save_ponderaciones">
                <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">
                <div class="row">
                    <?php $categorias_principales = ['Actividades', 'Asistencia', 'Examenes']; ?>
                    <?php foreach($categorias_principales as $cat_nombre): ?>
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><?php echo $cat_nombre; ?></label>
                        <div class="input-group"><input type="number" class="form-control ponderacion-input" name="ponderacion[<?php echo $cat_nombre; ?>]" value="<?php echo $categorias[$cat_nombre]['ponderacion'] ?? 0; ?>" min="0" max="100" required><span class="input-group-text">%</span></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-md-3 d-flex align-items-end">
                        <div><strong>Total: <span id="total-ponderacion">0</span>%</strong><button type="submit" class="btn btn-primary ms-3">Guardar</button></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <?php foreach($categorias_principales as $cat_nombre): ?>
            <li class="nav-item" role="presentation"><button class="nav-link <?php if($cat_nombre == 'Actividades') echo 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-<?php echo strtolower($cat_nombre); ?>" type="button"><?php echo $cat_nombre; ?></button></li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content card" id="myTabContent">
        <?php foreach(['Actividades', 'Asistencia', 'Examenes'] as $cat_nombre):
            $data = ${"data_".strtolower($cat_nombre)};
            $categoria_id = $categorias[$cat_nombre]['id'] ?? null;
        ?>
        <div class="tab-pane fade <?php if($cat_nombre == 'Actividades') echo 'show active'; ?> p-4" id="tab-<?php echo strtolower($cat_nombre); ?>" role="tabpanel">
            <?php if(!$categoria_id || ($categorias[$cat_nombre]['ponderacion'] ?? 0) == 0): ?>
                <div class="alert alert-info">Define una ponderación mayor a 0% para esta categoría para poder activarla.</div>
            <?php else: ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <form action="../../back/profesor_actions.php" method="POST" class="row gx-2 align-items-center">
                            <input type="hidden" name="action" value="create_item">
                            <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">
                            <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
                            <div class="col"><input type="text" name="nombre_item" class="form-control" placeholder="Nombre del nuevo elemento (Ej: Tarea 1)" required></div>
                            <div class="col-auto"><button type="submit" class="btn btn-success">Crear Nuevo</button></div>
                        </form>
                    </div>
                </div>

                <form action="../../back/profesor_actions.php" method="POST">
                    <input type="hidden" name="action" value="save_grades">
                    <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <?php foreach($data['items'] as $item): ?>
                                        <th class="text-center">
                                            <?php echo htmlspecialchars($item['nombre_actividad']); ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-0 ms-1" title="Eliminar" onclick="confirmDelete(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($alumnos as $alumno): ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($alumno['nombre_completo']); ?></td>
                                    <?php foreach($data['items'] as $item): ?>
                                    <td>
                                        <input type="number" step="0.1" max="100" min="0" class="form-control" name="calificaciones[<?php echo $alumno['inscripcion_id']; ?>][<?php echo $item['id']; ?>]" value="<?php echo $data['calificaciones'][$alumno['inscripcion_id']][$item['id']] ?? ''; ?>">
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary float-end mt-3"><i class="fas fa-save me-2"></i>Guardar Calificaciones</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form action="../../back/profesor_actions.php" method="POST" id="deleteItemForm" style="display: none;">
    <input type="hidden" name="action" value="delete_item">
    <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">
    <input type="hidden" name="actividad_id" id="delete_actividad_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.ponderacion-input').forEach(input => { total += parseFloat(input.value) || 0; });
        const totalSpan = document.getElementById('total-ponderacion');
        if (totalSpan) {
            totalSpan.textContent = total;
            totalSpan.style.color = (total === 100) ? 'green' : '#dc3545';
        }
    }
    document.body.addEventListener('input', e => { if (e.target.classList.contains('ponderacion-input')) updateTotal(); });
    updateTotal();
});

// Nueva función JS para manejar el borrado de forma segura
function confirmDelete(actividadId) {
    if (confirm('¿Estás seguro de que quieres eliminar este elemento y todas sus calificaciones? Esta acción es permanente.')) {
        document.getElementById('delete_actividad_id').value = actividadId;
        document.getElementById('deleteItemForm').submit();
    }
}
</script>