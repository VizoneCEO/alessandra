<?php
// --- 1. OBTENER DATOS ESENCIALES ---
require '../../back/db_connect.php';

// a) Obtener TODOS los ciclos escolares para el selector
$todos_ciclos = $conn->query("SELECT id, nombre_ciclo, estado FROM Ciclos_Escolares ORDER BY fecha_inicio DESC")->fetch_all(MYSQLI_ASSOC);

// b) Obtener TODAS las sucursales para el selector
$todas_sucursales = $conn->query("SELECT id, nombre_sucursal FROM Sucursales ORDER BY nombre_sucursal ASC")->fetch_all(MYSQLI_ASSOC);

// c) Determinar el ciclo seleccionado. Prioridad: GET > activo > el primero de la lista
$selected_ciclo_id = null;
$selected_ciclo_nombre = "Ninguno";

if (isset($_GET['ciclo_id'])) {
    $selected_ciclo_id = $_GET['ciclo_id'];
} else {
    foreach ($todos_ciclos as $ciclo) {
        if ($ciclo['estado'] == 'activo') {
            $selected_ciclo_id = $ciclo['id'];
            break;
        }
    }
}
if (!$selected_ciclo_id && !empty($todos_ciclos)) {
    $selected_ciclo_id = $todos_ciclos[0]['id'];
}
// Obtenemos el nombre del ciclo seleccionado
if ($selected_ciclo_id) {
    foreach ($todos_ciclos as $ciclo) {
        if ($ciclo['id'] == $selected_ciclo_id) {
            $selected_ciclo_nombre = $ciclo['nombre_ciclo'];
            break;
        }
    }
}

// d) Determinar la sucursal seleccionada. Prioridad: GET > la primera de la lista
$selected_sucursal_id = null;
$selected_sucursal_nombre = "Ninguna";

if (isset($_GET['sucursal_id'])) {
    $selected_sucursal_id = $_GET['sucursal_id'];
} elseif (!empty($todas_sucursales)) {
    // A diferencia del ciclo, seleccionamos la primera por defecto si no hay GET
    $selected_sucursal_id = $todas_sucursales[0]['id'];
}
// Obtenemos el nombre de la sucursal seleccionada
if ($selected_sucursal_id) {
    foreach ($todas_sucursales as $sucursal) {
        if ($sucursal['id'] == $selected_sucursal_id) {
            $selected_sucursal_nombre = $sucursal['nombre_sucursal'];
            break;
        }
    }
}


// e) Obtener lista de todos los profesores
$profesores = $conn->query("SELECT id, nombre_completo FROM Usuarios WHERE perfil_id = 2 ORDER BY nombre_completo")->fetch_all(MYSQLI_ASSOC);

// f) Obtener todas las materias del catálogo
$materias_catalogo = $conn->query("SELECT * FROM Materias ORDER BY semestre, nombre_materia")->fetch_all(MYSQLI_ASSOC);

// g) Obtener las clases asignadas SOLO para el ciclo Y SUCURSAL seleccionados
$clases_asignadas = [];
if ($selected_ciclo_id && $selected_sucursal_id) {
    $sql_clases = "SELECT c.id, c.materia_id, c.grupo, u.nombre_completo AS profesor_nombre 
                   FROM Clases c 
                   JOIN Usuarios u ON c.profesor_id = u.id 
                   WHERE c.ciclo_id = ? AND c.sucursal_id = ?";

    $stmt = $conn->prepare($sql_clases);
    $stmt->bind_param("ii", $selected_ciclo_id, $selected_sucursal_id);
    $stmt->execute();
    $result_clases = $stmt->get_result();

    while ($row = $result_clases->fetch_assoc()) {
        $clases_asignadas[$row['materia_id']][] = $row;
    }
    $stmt->close();
}

// h) Obtener conteo de alumnos (la lógica no necesita cambiar)
$conteo_alumnos = [];
$lista_de_clases_ids = [];
foreach ($clases_asignadas as $materia_grupo) {
    foreach ($materia_grupo as $clase) {
        $lista_de_clases_ids[] = $clase['id'];
    }
}

if (!empty($lista_de_clases_ids)) {
    $placeholders = implode(',', array_fill(0, count($lista_de_clases_ids), '?'));
    $tipos = str_repeat('i', count($lista_de_clases_ids));

    $sql_conteo = "SELECT clase_id, COUNT(id) as total_alumnos FROM Inscripciones WHERE clase_id IN ($placeholders) GROUP BY clase_id";

    $stmt_conteo = $conn->prepare($sql_conteo);
    $stmt_conteo->bind_param($tipos, ...$lista_de_clases_ids);
    $stmt_conteo->execute();
    $result_conteo = $stmt_conteo->get_result();

    while ($row_conteo = $result_conteo->fetch_assoc()) {
        $conteo_alumnos[$row_conteo['clase_id']] = $row_conteo['total_alumnos'];
    }
    $stmt_conteo->close();
}
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Catálogo de Materias</h3>
    <p class="text-zinc-500 font-light text-sm">Gestiona asignaturas y asigna profesores a grupos.</p>
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

<!-- Barra Principal: Filtros y Añadir Materia -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-end">

        <!-- Contexto: Ciclo/Sucursal -->
        <div class="lg:col-span-8">
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-3"><i
                    class="fas fa-filter mr-1"></i> Contexto de Asignación</h6>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="page" value="materias">
                <div>
                    <select name="ciclo_id"
                        class="w-full text-sm border-b border-zinc-200 py-2 focus:border-zinc-900 focus:outline-none bg-transparent"
                        onchange="this.form.submit()">
                        <option value="">Selecciona un ciclo...</option>
                        <?php foreach ($todos_ciclos as $ciclo): ?>
                            <option value="<?php echo $ciclo['id']; ?>" <?php echo ($ciclo['id'] == $selected_ciclo_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ciclo['nombre_ciclo']) . ($ciclo['estado'] == 'activo' ? ' (Activo)' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select name="sucursal_id"
                        class="w-full text-sm border-b border-zinc-200 py-2 focus:border-zinc-900 focus:outline-none bg-transparent"
                        onchange="this.form.submit()">
                        <option value="">Selecciona una sucursal...</option>
                        <?php foreach ($todas_sucursales as $sucursal): ?>
                            <option value="<?php echo $sucursal['id']; ?>" <?php echo ($sucursal['id'] == $selected_sucursal_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Añadir Materia -->
        <div class="lg:col-span-4 border-t lg:border-t-0 lg:border-l border-zinc-100 lg:pl-6 pt-4 lg:pt-0">
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-3"><i class="fas fa-plus mr-1"></i>
                Nueva Materia</h6>
            <form action="../../back/admin_actions_materias.php" method="POST" class="flex flex-col space-y-3">
                <input type="hidden" name="action" value="create_materia">
                <div class="flex space-x-2">
                    <input type="text"
                        class="w-full border-b border-zinc-200 py-1 text-sm focus:border-zinc-900 outline-none"
                        name="nombre_materia" placeholder="Nombre (Ej. Historia)" required>
                    <select class="w-20 border-b border-zinc-200 py-1 text-sm focus:border-zinc-900 outline-none"
                        name="semestre" required>
                        <option value="">Sem.</option>
                        <?php for ($k = 1; $k <= 6; $k++)
                            echo "<option value='$k'>$k</option>"; ?>
                    </select>
                </div>
                <button type="submit"
                    class="w-full py-2 bg-zinc-900 text-white text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors rounded">
                    Añadir al Catálogo
                </button>
            </form>
        </div>
    </div>
</div>

<div class="mb-6 border-b border-zinc-100 pb-2">
    <span class="text-xs uppercase tracking-widest text-zinc-400">Viendo asignaciones para:</span>
    <span
        class="ml-2 font-serif italic text-emerald-600 font-bold"><?php echo htmlspecialchars($selected_ciclo_nombre); ?></span>
    <span class="mx-2 text-zinc-300">/</span>
    <span
        class="font-serif italic text-indigo-600 font-bold"><?php echo htmlspecialchars($selected_sucursal_nombre); ?></span>
</div>

<?php if (!$selected_ciclo_id || !$selected_sucursal_id): ?>
    <div class="p-8 border border-dashed border-zinc-300 rounded-xl text-center">
        <i class="fas fa-arrow-up text-2xl text-zinc-300 mb-2"></i>
        <p class="text-zinc-500 text-sm">Por favor, selecciona un Ciclo Escolar y una Sucursal arriba para comenzar.</p>
    </div>
<?php else: ?>

    <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="mb-10">
            <h5
                class="text-xs font-bold uppercase tracking-[0.2em] text-zinc-900 mb-6 border-b border-zinc-200 pb-2 inline-block">
                Semestre <?php echo $i; ?></h5>

            <!-- GRID CORREGIDO: MAX 3 COLUMNAS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php $materias_en_semestre = false; ?>
                <?php foreach ($materias_catalogo as $materia): ?>
                    <?php if ($materia['semestre'] == $i): ?>
                        <?php $materias_en_semestre = true; ?>

                        <!-- Fashion Card -->
                        <div
                            class="bg-white rounded-lg border border-zinc-100 shadow-sm hover:shadow-md transition-shadow flex flex-col h-full">
                            <!-- Card Header -->
                            <div class="px-5 py-4 border-b border-zinc-50 flex justify-between items-start">
                                <h6 class="font-serif font-bold text-lg text-zinc-900 leading-tight">
                                    <?php echo htmlspecialchars($materia['nombre_materia']); ?>
                                </h6>
                                <form action="../../back/admin_actions_materias.php" method="POST"
                                    onsubmit="return confirm('¡ALERTA!\n¿Eliminar materia del catálogo?\nEsto borrará todas asignaciones históricas.');">
                                    <input type="hidden" name="action" value="delete_materia">
                                    <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                                    <button type="submit" class="text-zinc-300 hover:text-rose-500 transition-colors pt-1">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- Card Body (Professors List) -->
                            <div class="px-5 py-4 flex-1 space-y-3 bg-zinc-50/20">
                                <?php if (isset($clases_asignadas[$materia['id']])): ?>
                                    <?php foreach ($clases_asignadas[$materia['id']] as $clase): ?>
                                        <?php
                                        $total_alumnos = $conteo_alumnos[$clase['id']] ?? 0;
                                        $conteo_color = ($total_alumnos == 0) ? 'text-rose-500' : 'text-zinc-400';
                                        ?>
                                        <!-- Item Profesor con ESPACIADO MEJORADO -->
                                        <div
                                            class="flex items-center justify-between group bg-white p-3 rounded border border-zinc-100 shadow-sm mb-2">
                                            <div class="flex items-center min-w-0 flex-1 mr-2">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-500 text-xs mr-3 flex-shrink-0">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold text-zinc-900 truncate">
                                                        <?php echo htmlspecialchars($clase['profesor_nombre']); ?>
                                                    </p>
                                                    <div class="flex items-center space-x-2 mt-0.5">
                                                        <span class="text-[10px] uppercase tracking-wider <?php echo $conteo_color; ?>">
                                                            <?php echo $total_alumnos; ?> Alumnos
                                                        </span>
                                                        <!-- BADGE GRUPO MEJORADO -->
                                                        <span
                                                            class="text-[10px] font-bold bg-zinc-100 text-zinc-600 px-2 py-0.5 rounded border border-zinc-200 uppercase tracking-wide"
                                                            title="Grupo">
                                                            Grp: <?php echo htmlspecialchars($clase['grupo']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                                                <a href="dashboard.php?page=asignacion&clase_id=<?php echo $clase['id']; ?>"
                                                    class="w-7 h-7 flex items-center justify-center rounded bg-zinc-50 hover:bg-emerald-500 hover:text-white text-zinc-400 text-xs transition-colors border border-zinc-200"
                                                    title="Asignar Alumnos">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                                <form action="../../back/admin_actions_materias.php" method="POST"
                                                    onsubmit="return confirm('¿Quitar asignación?');">
                                                    <input type="hidden" name="action" value="delete_clase">
                                                    <input type="hidden" name="clase_id" value="<?php echo $clase['id']; ?>">
                                                    <button type="submit"
                                                        class="w-7 h-7 flex items-center justify-center rounded bg-zinc-50 hover:bg-rose-500 hover:text-white text-zinc-400 text-xs transition-colors border border-zinc-200">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Card Footer (Assign Form) ROBUSTO -->
                            <div class="px-5 py-4 border-t border-zinc-100 bg-white">
                                <form action="../../back/admin_actions_materias.php" method="POST">
                                    <input type="hidden" name="action" value="create_clase">
                                    <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                                    <input type="hidden" name="ciclo_id" value="<?php echo $selected_ciclo_id; ?>">
                                    <input type="hidden" name="sucursal_id" value="<?php echo $selected_sucursal_id; ?>">

                                    <!-- Layout Responsive: Stacked on default, Row on large if space permits -->
                                    <div class="flex flex-col xl:flex-row gap-2">

                                        <div class="flex-1 min-w-0">
                                            <select name="profesor_id"
                                                class="w-full text-xs border border-zinc-200 rounded px-2 py-2 focus:border-zinc-900 outline-none bg-white appearance-none truncate"
                                                required>
                                                <option value="">Profesor...</option>
                                                <?php foreach ($profesores as $profesor): ?>
                                                    <option value="<?php echo $profesor['id']; ?>">
                                                        <?php echo htmlspecialchars($profesor['nombre_completo']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="flex gap-2">
                                            <!-- INPUT GRUPO ANCHO FIJO DIGNO -->
                                            <input type="text" name="grupo" placeholder="Grp"
                                                class="w-16 text-xs border border-zinc-200 rounded px-2 py-2 focus:border-zinc-900 outline-none text-center"
                                                required title="Grupo (Ej. A, B)">

                                            <button type="submit"
                                                class="w-8 flex items-center justify-center bg-zinc-900 text-white rounded hover:bg-zinc-700 transition-colors text-xs flex-shrink-0"
                                                title="Asignar">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!$materias_en_semestre): ?>
                    <div class="col-span-full py-8 text-center text-zinc-400 text-xs italic bg-zinc-50/50 rounded-lg">
                        No hay materias registradas para este semestre.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endfor; ?>

<?php endif; ?>