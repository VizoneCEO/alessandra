<?php
// --- 1. OBTENER DATOS DE FORMA EFICIENTE ---
require '../../back/db_connect.php';
$profesor_id = $_SESSION['user_id'];
$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();

if (!$ciclo_activo) {
    echo '<div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50" role="alert"><span class="font-medium">Atención:</span> No hay un ciclo escolar activo.</div>';
    return;
}
$ciclo_activo_id = $ciclo_activo['id'];

// ===== CONSULTA MODIFICADA =====
// Ahora traemos la sucursal y ordenamos por ella
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
// ===============================

$clase_seleccionada_id = isset($_GET['clase_id']) ? (int) $_GET['clase_id'] : null;

if ($clase_seleccionada_id) {

    // --- NUEVA CONSULTA: Obtenemos info de la clase seleccionada ---
    $sql_clase_info = "SELECT m.nombre_materia, s.nombre_sucursal 
                       FROM Clases c 
                       JOIN Materias m ON c.materia_id = m.id 
                       JOIN Sucursales s ON c.sucursal_id = s.id 
                       WHERE c.id = ?";
    $stmt_info = $conn->prepare($sql_clase_info);
    $stmt_info->bind_param("i", $clase_seleccionada_id);
    $stmt_info->execute();
    $clase_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();
    // -----------------------------------------------------------

    // Obtenemos los datos de la clase para trabajar
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_seleccionada_id")->fetch_all(MYSQLI_ASSOC);
    $categorias = [];
    foreach ($categorias_db as $cat) {
        $categorias[$cat['nombre_categoria']] = $cat;
    }

    $alumnos = $conn->query("SELECT i.id as inscripcion_id, u.nombre_completo FROM Inscripciones i JOIN Usuarios u ON i.alumno_id = u.id WHERE i.clase_id = $clase_seleccionada_id ORDER BY u.nombre_completo")->fetch_all(MYSQLI_ASSOC);

    // --- FUNCIÓN OPTIMIZADA PARA OBTENER DATOS (AHORA INCLUYE PARCIALES) ---
    function get_items_y_calificaciones($conn, $categoria_id, $alumnos)
    {
        if (!$categoria_id || empty($alumnos)) {
            // Devolvemos la estructura de parciales vacía
            return ['items' => [1 => [], 2 => [], 3 => []], 'calificaciones' => []];
        }

        // Consultamos y agrupamos por parcial
        $items_result = $conn->query("SELECT * FROM Actividades_Evaluables WHERE categoria_id = $categoria_id ORDER BY parcial, id");
        $items_agrupados = [1 => [], 2 => [], 3 => []];
        $item_ids = [];

        while ($item = $items_result->fetch_assoc()) {
            if (isset($items_agrupados[$item['parcial']])) {
                $items_agrupados[$item['parcial']][] = $item;
                $item_ids[] = $item['id'];
            }
        }

        if (empty($item_ids)) {
            return ['items' => $items_agrupados, 'calificaciones' => []];
        }

        // Obtenemos calificaciones (esto no cambia)
        $inscripcion_ids = array_column($alumnos, 'inscripcion_id');
        $sql_calif = "SELECT inscripcion_id, actividad_id, calificacion_obtenida 
                      FROM Calificaciones 
                      WHERE inscripcion_id IN (" . implode(',', $inscripcion_ids) . ") 
                      AND actividad_id IN (" . implode(',', $item_ids) . ")";

        $calificaciones_result = $conn->query($sql_calif);
        $calificaciones = [];
        while ($nota = $calificaciones_result->fetch_assoc()) {
            $calificaciones[$nota['inscripcion_id']][$nota['actividad_id']] = $nota['calificacion_obtenida'];
        }

        return ['items' => $items_agrupados, 'calificaciones' => $calificaciones];
    }

    $data_actividades = get_items_y_calificaciones($conn, $categorias['Actividades']['id'] ?? null, $alumnos);
    $data_asistencia = get_items_y_calificaciones($conn, $categorias['Asistencia']['id'] ?? null, $alumnos);
    $data_examenes = get_items_y_calificaciones($conn, $categorias['Examenes']['id'] ?? null, $alumnos);
}
?>

<!-- Header Section -->
<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 flex items-center">
        <?php if ($clase_seleccionada_id && $clase_info): ?>
            <span class="mr-2">Gestionar:</span>
            <span class="italic font-bold"><?php echo htmlspecialchars($clase_info['nombre_materia']); ?></span>
            <span
                class="ml-4 text-sm font-sans font-light text-zinc-400 uppercase tracking-widest border px-2 py-1 rounded-full">
                <?php echo htmlspecialchars($clase_info['nombre_sucursal']); ?>
            </span>
        <?php else: ?>
            Mis Clases Asignadas
        <?php endif; ?>
    </h3>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <?php
    $msgType = $_SESSION['message']['type'];
    $bgColor = ($msgType == 'success') ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200';
    ?>
    <div class="p-4 mb-6 text-sm rounded-lg border <?php echo $bgColor; ?> flex justify-between items-center" role="alert">
        <span><?php echo htmlspecialchars($_SESSION['message']['text']); ?></span>
        <button onclick="this.parentElement.remove()"
            class="text-xs uppercase font-bold opacity-50 hover:opacity-100">Cerrar</button>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if (!$clase_seleccionada_id): ?>

    <!-- VISTA PRINCIPAL (GRID DE TARJETAS) -->
    <?php
    $current_sucursal = null;
    if (empty($mis_clases)) {
        echo '<div class="p-8 border border-dashed border-zinc-300 rounded-xl text-center text-zinc-500 italic">No tienes clases asignadas en este ciclo activo.</div>';
    } else {
        // Agrupar clases por sucursal
        $clases_por_sucursal = [];
        foreach ($mis_clases as $c) {
            $clases_por_sucursal[$c['nombre_sucursal']][] = $c;
        }

        foreach ($clases_por_sucursal as $sucursal => $clases):
            ?>
            <div class="mb-10">
                <h4 class="text-sm font-bold uppercase tracking-widest text-zinc-400 mb-4 flex items-center">
                    <i class="fas fa-building mr-2"></i> <?php echo htmlspecialchars($sucursal); ?>
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($clases as $clase): ?>
                        <a href="dashboard.php?view=clases&clase_id=<?php echo $clase['id']; ?>"
                            class="group block bg-white rounded-xl border border-zinc-100 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden transform hover:-translate-y-1">
                            <div class="h-2 bg-zinc-900 group-hover:bg-amber-500 transition-colors"></div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-2">
                                    <h5 class="font-serif text-xl font-bold text-zinc-900 group-hover:text-amber-600 transition-colors">
                                        <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                                    </h5>
                                    <i
                                        class="fas fa-arrow-right text-zinc-300 group-hover:text-amber-500 group-hover:translate-x-1 transition-all"></i>
                                </div>
                                <p class="text-xs text-zinc-400 font-light">
                                    Click para gestionar calificaciones
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php
        endforeach;
    }
    ?>

<?php else: ?>

    <!-- VISTA DE GESTIÓN (DETALLE DE CLASE) -->

    <a href="dashboard.php?view=clases"
        class="inline-flex items-center text-zinc-400 hover:text-zinc-900 text-sm font-medium mb-6 transition-colors">
        <i class="fas fa-arrow-left mr-2"></i> Volver a mis clases
    </a>

    <!-- Paso 1: Ponderaciones -->
    <div class="bg-white rounded-xl shadow-sm border border-zinc-100 mb-8 overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-50 flex items-center justify-between bg-zinc-50/50">
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500">
                <i class="fas fa-balance-scale mr-2"></i> Configuración de Ponderaciones
            </h6>
            <span class="text-[10px] text-zinc-400 bg-white border px-2 py-1 rounded">Peso Total del Semestre</span>
        </div>
        <div class="p-6 lg:p-8">
            <form action="../../back/profesor_actions.php" method="POST">
                <input type="hidden" name="action" value="save_ponderaciones">
                <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 items-end">
                    <?php $categorias_principales = ['Actividades', 'Asistencia', 'Examenes']; ?>
                    <?php foreach ($categorias_principales as $cat_nombre): ?>
                        <div>
                            <label
                                class="block text-center text-xs font-bold uppercase tracking-widest text-zinc-400 mb-2"><?php echo $cat_nombre; ?></label>
                            <div class="relative">
                                <input type="number"
                                    class="ponderacion-input w-full border-b-2 border-zinc-200 focus:border-zinc-900 outline-none py-2 bg-transparent text-center font-serif text-2xl text-zinc-900 transition-colors"
                                    name="ponderacion[<?php echo $cat_nombre; ?>]"
                                    value="<?php echo $categorias[$cat_nombre]['ponderacion'] ?? 0; ?>" min="0" max="100"
                                    required>
                                <span class="absolute right-0 bottom-2 text-zinc-400 text-sm">%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="flex items-center justify-between md:block pt-4 md:pt-0">
                        <div class="text-center mb-0 md:mb-2">
                            <span class="text-xs uppercase text-zinc-400">Total</span>
                            <div class="font-serif text-2xl font-bold" id="total-ponderacion">0%</div>
                        </div>
                        <button type="submit"
                            class="w-full py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-700 transition-colors rounded shadow-lg">
                            Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Pestañas de Categorías (Tabs Modernas) -->
    <div class="mb-6 border-b border-zinc-200">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="myTab" role="tablist">
            <?php foreach ($categorias_principales as $i => $cat_nombre): ?>
                <li class="mr-2" role="presentation">
                    <button
                        class="inline-block p-4 border-b-2 rounded-t-lg hover:text-zinc-600 hover:border-zinc-300 transition-all <?php echo ($i == 0) ? 'text-zinc-900 border-zinc-900 font-bold' : 'text-zinc-400 border-transparent font-normal'; ?>"
                        id="tab-btn-<?php echo strtolower($cat_nombre); ?>"
                        data-target="#tab-<?php echo strtolower($cat_nombre); ?>" type="button"
                        onclick="switchTab(this, '<?php echo strtolower($cat_nombre); ?>')">
                        <?php echo $cat_nombre; ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Contenido de Tabs -->
    <div id="myTabContent">
        <?php foreach (['Actividades', 'Asistencia', 'Examenes'] as $i_cat => $cat_nombre):
            $data = ${"data_" . strtolower($cat_nombre)};
            $categoria_id = $categorias[$cat_nombre]['id'] ?? null;
            $cat_slug = strtolower($cat_nombre);
            $isActive = ($i_cat == 0) ? '' : 'hidden'; // Solo el primero visible por defecto
            ?>

            <div class="tab-pane <?php echo $isActive; ?> transition-opacity duration-300" id="tab-<?php echo $cat_slug; ?>"
                role="tabpanel">

                <?php if (!$categoria_id || ($categorias[$cat_nombre]['ponderacion'] ?? 0) == 0): ?>
                    <div class="p-8 bg-zinc-50 border border-zinc-200 rounded-lg text-center text-zinc-500">
                        <i class="fas fa-info-circle text-2xl mb-2 text-zinc-300"></i>
                        <p>Define una ponderación mayor a 0% en la configuración superior para activar esta categoría.</p>
                    </div>
                <?php else: ?>

                    <!-- Formulario Crear Nuevo Item -->
                    <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-6 mb-8">
                        <form action="../../back/profesor_actions.php" method="POST"
                            class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            <input type="hidden" name="action" value="create_item">
                            <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">
                            <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">

                            <div class="md:col-span-6">
                                <label class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">Nombre de la
                                    Actividad</label>
                                <input type="text" name="nombre_item"
                                    class="w-full border border-zinc-300 rounded px-3 py-2 focus:border-zinc-900 focus:outline-none text-sm"
                                    placeholder="Ej. Tarea 1, Examen Parcial..." required>
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">Parcial</label>
                                <select name="parcial"
                                    class="w-full border border-zinc-300 rounded px-3 py-2 focus:border-zinc-900 focus:outline-none text-sm bg-white"
                                    required>
                                    <option value="">Selecciona...</option>
                                    <option value="1">Parcial 1</option>
                                    <option value="2">Parcial 2</option>
                                    <option value="3">Parcial 3</option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <button type="submit"
                                    class="w-full py-2 bg-emerald-600 text-white text-xs font-bold uppercase tracking-widest hover:bg-emerald-700 transition-colors rounded shadow-sm">
                                    <i class="fas fa-plus mr-1"></i> Crear
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Sub-Tabs Parciales -->
                    <div class="mb-4">
                        <div class="flex space-x-1 bg-zinc-100 p-1 rounded-lg inline-flex">
                            <?php for ($p = 1; $p <= 3; $p++): ?>
                                <button
                                    class="px-4 py-1.5 text-xs font-bold uppercase tracking-wider rounded-md transition-all <?php echo ($p == 1) ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700'; ?>"
                                    onclick="switchPill(this, '<?php echo $cat_slug; ?>', <?php echo $p; ?>)">
                                    Parcial <?php echo $p; ?>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Contenido Parciales -->
                    <div id="pills-content-<?php echo $cat_slug; ?>">
                        <?php for ($p = 1; $p <= 3; $p++): ?>
                            <div class="pill-pane <?php echo ($p == 1) ? '' : 'hidden'; ?>"
                                id="pill-<?php echo $cat_slug; ?>-p<?php echo $p; ?>">

                                <?php $items_parcial = $data['items'][$p]; ?>

                                <?php if (empty($items_parcial)): ?>
                                    <div class="text-center py-10 text-zinc-400 italic">No hay elementos creados para el Parcial
                                        <?php echo $p; ?>.</div>
                                <?php else: ?>
                                    <form action="../../back/profesor_actions.php" method="POST">
                                        <input type="hidden" name="action" value="save_grades">
                                        <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">

                                        <div class="bg-white border boundary-zinc-200 rounded-lg overflow-hidden shadow-sm relative">
                                            <!-- Contenedor Scroll Horizontal -->
                                            <div class="overflow-x-auto custom-scrollbar">
                                                <table class="w-full text-left border-collapse whitespace-nowrap">
                                                    <thead>
                                                        <tr class="bg-zinc-950 text-white text-xs uppercase tracking-wider">
                                                            <th class="px-4 py-3 sticky left-0 z-10 bg-zinc-950 border-r border-zinc-800 shadow-lg"
                                                                style="min-width: 200px;">Alumno</th>
                                                            <?php foreach ($items_parcial as $item): ?>
                                                                <th class="px-4 py-3 text-center border-r border-zinc-800 min-w-[100px]">
                                                                    <div class="flex flex-col items-center">
                                                                        <span
                                                                            class="mb-1"><?php echo htmlspecialchars($item['nombre_actividad']); ?></span>
                                                                        <button type="button"
                                                                            class="text-rose-400 hover:text-rose-200 text-[10px]"
                                                                            title="Eliminar"
                                                                            onclick="confirmDelete(<?php echo $item['id']; ?>)">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </th>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-zinc-100">
                                                        <?php foreach ($alumnos as $alumno): ?>
                                                            <tr class="hover:bg-zinc-50 transition-colors">
                                                                <td
                                                                    class="px-4 py-2 text-sm font-medium text-zinc-800 sticky left-0 bg-white hover:bg-zinc-50 border-r border-zinc-100 shadow-sm">
                                                                    <?php echo htmlspecialchars($alumno['nombre_completo']); ?>
                                                                </td>
                                                                <?php foreach ($items_parcial as $item): ?>
                                                                    <td class="p-0 border-r border-zinc-100">
                                                                        <input type="number" step="0.1" max="100" min="0"
                                                                            class="w-full h-full py-3 text-center bg-transparent focus:bg-amber-50 focus:outline-none transition-colors text-zinc-600 font-mono text-sm"
                                                                            name="calificaciones[<?php echo $alumno['inscripcion_id']; ?>][<?php echo $item['id']; ?>]"
                                                                            value="<?php echo $data['calificaciones'][$alumno['inscripcion_id']][$item['id']] ?? ''; ?>"
                                                                            placeholder="-">
                                                                    </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <button type="submit"
                                                class="px-6 py-3 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors rounded shadow-lg">
                                                <i class="fas fa-save mr-2"></i> Guardar Calificaciones (P<?php echo $p; ?>)
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<!-- Hidden Form for Deletion -->
<form action="../../back/profesor_actions.php" method="POST" id="deleteItemForm" style="display: none;">
    <input type="hidden" name="action" value="delete_item">
    <input type="hidden" name="clase_id" value="<?php echo $clase_seleccionada_id; ?>">
    <input type="hidden" name="actividad_id" id="delete_actividad_id">
</form>

<script>
    // Logic for Ponderaciones Total Calculation
    document.addEventListener('DOMContentLoaded', function () {
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.ponderacion-input').forEach(input => { total += parseFloat(input.value) || 0; });
            const totalSpan = document.getElementById('total-ponderacion');
            if (totalSpan) {
                totalSpan.textContent = total + '%';
                if (total === 100) {
                    totalSpan.classList.remove('text-rose-600');
                    totalSpan.classList.add('text-emerald-600');
                } else {
                    totalSpan.classList.add('text-rose-600');
                    totalSpan.classList.remove('text-emerald-600');
                }
            }
        }
        document.body.addEventListener('input', e => { if (e.target.classList.contains('ponderacion-input')) updateTotal(); });
        updateTotal();
    });

    function confirmDelete(actividadId) {
        if (confirm('¿Estás seguro de que quieres eliminar este elemento y todas sus calificaciones? Esta acción es permanente.')) {
            document.getElementById('delete_actividad_id').value = actividadId;
            document.getElementById('deleteItemForm').submit();
        }
    }

    // Custom Tab Switcher (Tailwind)
    function switchTab(btn, catSlug) {
        // 1. Reset all tabs styles
        const allBtns = btn.closest('ul').querySelectorAll('button');
        allBtns.forEach(b => {
            b.classList.remove('text-zinc-900', 'border-zinc-900', 'font-bold');
            b.classList.add('text-zinc-400', 'border-transparent', 'font-normal');
        });
        // 2. Activate clicked tab style
        btn.classList.remove('text-zinc-400', 'border-transparent', 'font-normal');
        btn.classList.add('text-zinc-900', 'border-zinc-900', 'font-bold');

        // 3. Hide all contents
        const contentContainer = document.getElementById('myTabContent');
        const allPanes = contentContainer.querySelectorAll('.tab-pane');
        allPanes.forEach(pane => pane.classList.add('hidden'));

        // 4. Show target content
        const targetPane = document.getElementById('tab-' + catSlug);
        targetPane.classList.remove('hidden');
    }

    // Custom Pill Switcher (Tailwind)
    function switchPill(btn, catSlug, p) {
        // 1. Reset all pills in this group
        const container = btn.parentElement;
        const allPills = container.querySelectorAll('button');
        allPills.forEach(b => {
            b.classList.remove('bg-white', 'text-zinc-900', 'shadow-sm');
            b.classList.add('text-zinc-500');
        });

        // 2. Activate clicked pill
        btn.classList.remove('text-zinc-500');
        btn.classList.add('bg-white', 'text-zinc-900', 'shadow-sm');

        // 3. Hide all pill contents
        const pillContentContainer = document.getElementById('pills-content-' + catSlug);
        const allPillPanes = pillContentContainer.querySelectorAll('.pill-pane');
        allPillPanes.forEach(pane => pane.classList.add('hidden'));

        // 4. Show target pill content
        const targetPillPane = document.getElementById('pill-' + catSlug + '-p' + p);
        targetPillPane.classList.remove('hidden');
    }
</script>