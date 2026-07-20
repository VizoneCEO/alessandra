<?php
// --- 1. OBTENER DATOS ---
require '../../back/db_connect.php';

// Verificamos si se pasó un ID de clase en la URL
if (!isset($_GET['clase_id'])) {
    echo '<div class="p-6 bg-blue-50 text-blue-800 rounded-lg border border-blue-200">Por favor, selecciona una clase desde el panel de "Gestión de Materias" para empezar a asignar alumnos.</div>';
    // Salimos del script si no hay clase seleccionada
    return;
}

$clase_id = (int) $_GET['clase_id'];

// a) Obtener detalles de la clase seleccionada (Materia, Profesor, Ciclo)
$sql_clase_details = "SELECT m.nombre_materia, u.nombre_completo as profesor_nombre, ce.nombre_ciclo, c.materia_id, c.ciclo_id
                      FROM Clases c
                      JOIN Materias m ON c.materia_id = m.id
                      JOIN Usuarios u ON c.profesor_id = u.id
                      JOIN Ciclos_Escolares ce ON c.ciclo_id = ce.id
                      WHERE c.id = $clase_id";
$clase_details = $conn->query($sql_clase_details)->fetch_assoc();


// b) Obtener alumnos YA INSCRITOS en esta clase
$sql_inscritos = "SELECT i.id as inscripcion_id, u.id as alumno_id, u.nombre_completo, u.curp, u.estado
                  FROM Inscripciones i
                  JOIN Usuarios u ON i.alumno_id = u.id
                  WHERE i.clase_id = $clase_id AND u.perfil_id = 3
                  ORDER BY u.nombre_completo";
$alumnos_inscritos = $conn->query($sql_inscritos)->fetch_all(MYSQLI_ASSOC);

// c) Obtener alumnos DISPONIBLES (que no estén inscritos en NINGUNA clase de esta materia en este ciclo)
//    Esto evita que un alumno se inscriba dos veces a la misma materia (aunque sea distinto grupo/profesor)
$materia_id = (int) $clase_details['materia_id'];
$ciclo_id = (int) $clase_details['ciclo_id'];

$sql_excluidos = "SELECT DISTINCT i.alumno_id 
                  FROM Inscripciones i
                  JOIN Clases c ON i.clase_id = c.id
                  WHERE c.materia_id = $materia_id AND c.ciclo_id = $ciclo_id";
$res_excluidos = $conn->query($sql_excluidos);
$ids_excluidos = [];
while ($row = $res_excluidos->fetch_assoc()) {
    $ids_excluidos[] = $row['alumno_id'];
}

$sql_disponibles = "SELECT id, nombre_completo, curp FROM Usuarios WHERE perfil_id = 3 AND estado = 'Activo'";

if (!empty($ids_excluidos)) {
    $sql_disponibles .= " AND id NOT IN (" . implode(',', $ids_excluidos) . ")";
}
$sql_disponibles .= " ORDER BY nombre_completo";
$alumnos_disponibles = $conn->query($sql_disponibles)->fetch_all(MYSQLI_ASSOC);

// d) Obtener los grupos existentes en el mismo ciclo para la opción de importar
$sql_grupos = "SELECT DISTINCT grupo FROM Clases WHERE ciclo_id = $ciclo_id AND grupo IS NOT NULL AND grupo != '' ORDER BY grupo";
$res_grupos = $conn->query($sql_grupos);
$grupos_existentes = [];
while ($row = $res_grupos->fetch_assoc()) {
    $grupos_existentes[] = $row['grupo'];
}

?>

<!-- HEADER INFORMATIVO DE LUJO -->
<div class="bg-white rounded-xl shadow-sm border border-amber-600/30 p-8 mb-8 relative overflow-hidden">
    <div
        class="absolute top-0 right-0 w-32 h-32 bg-amber-50 rounded-full mix-blend-multiply filter blur-xl opacity-70 translate-x-10 -translate-y-10">
    </div>

    <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <a href="dashboard.php?page=materias" class="text-xs font-bold uppercase tracking-widest text-zinc-400 hover:text-zinc-600 mb-2 inline-block transition-colors">
                <i class="fas fa-arrow-left mr-1"></i> Regresar
            </a>
            <p class="text-xs font-bold uppercase tracking-widest text-amber-600 mb-1">Panel de Asignación</p>
            <h2 class="font-serif text-3xl text-zinc-900 leading-tight">
                Asignando a: <span
                    class="italic"><?php echo htmlspecialchars($clase_details['nombre_materia']); ?></span>
            </h2>
        </div>
        <div class="text-right">
            <div class="inline-flex flex-col items-end">
                <span class="text-xs text-zinc-400 uppercase tracking-wider">Profesor</span>
                <span
                    class="text-sm font-medium text-zinc-800"><?php echo htmlspecialchars($clase_details['profesor_nombre']); ?></span>
            </div>
            <div class="w-px h-8 bg-zinc-200 mx-4 hidden md:inline-block align-middle"></div>
            <div class="inline-flex flex-col items-end mt-2 md:mt-0">
                <span class="text-xs text-zinc-400 uppercase tracking-wider">Ciclo</span>
                <span
                    class="text-sm font-medium text-zinc-800"><?php echo htmlspecialchars($clase_details['nombre_ciclo']); ?></span>
            </div>
        </div>
    </div>
</div>


<?php
// Mostrar mensajes de sesión
if (isset($_SESSION['message'])) {
    $msgType = $_SESSION['message']['type'];
    $bgColor = ($msgType == 'success') ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200';
    echo '<div class="p-4 mb-6 text-sm rounded-lg border ' . $bgColor . ' flex justify-between items-center" role="alert">
            <span>' . htmlspecialchars($_SESSION['message']['text']) . '</span>
            <button onclick="this.parentElement.remove()" class="text-xs uppercase font-bold opacity-50 hover:opacity-100">Cerrar</button>
          </div>';
    unset($_SESSION['message']);
}
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 h-[600px]">

    <!-- COLUMNA IZQUIERDA: ALUMNOS EN CLASE -->
    <div class="flex flex-col h-full bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <div class="px-6 py-4 bg-zinc-50 border-b border-zinc-200 flex justify-between items-center">
            <h5 class="text-sm font-bold uppercase tracking-wider text-emerald-700 flex items-center">
                <i class="fas fa-check-circle mr-2"></i> Inscritos
            </h5>
            <span
                class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2 py-1 rounded-full"><?php echo count($alumnos_inscritos); ?></span>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <?php if (empty($alumnos_inscritos)): ?>
                <div class="flex flex-col items-center justify-center h-full text-zinc-400 opacity-60">
                    <i class="fas fa-users-slash text-4xl mb-2"></i>
                    <p class="text-sm">No hay alumnos inscritos.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alumnos_inscritos as $alumno): ?>
                    <?php $inactivo = ($alumno['estado'] == 'inactivo'); ?>
                    <div
                        class="group flex items-center justify-between p-3 rounded-lg border <?php echo $inactivo ? 'border-rose-200 bg-rose-50/50 hover:bg-rose-100' : 'border-zinc-100 hover:border-rose-200 hover:bg-rose-50'; ?> transition-all">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full <?php echo $inactivo ? 'bg-rose-100 text-rose-600' : 'bg-emerald-50 text-emerald-600'; ?> flex items-center justify-center text-xs font-bold">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold <?php echo $inactivo ? 'text-rose-700' : 'text-zinc-900'; ?> leading-none">
                                    <?php echo htmlspecialchars($alumno['nombre_completo']); ?>
                                </p>
                                <p class="text-[10px] <?php echo $inactivo ? 'text-rose-500' : 'text-zinc-400'; ?> font-mono mt-1">
                                    <?php echo htmlspecialchars($alumno['curp']); ?>
                                    <?php if ($inactivo) echo ' - INACTIVO'; ?>
                                </p>
                            </div>
                        </div>

                        <form action="../../back/admin_actions_asignacion.php" method="POST">
                            <input type="hidden" name="action" value="unenroll_student">
                            <input type="hidden" name="inscripcion_id" value="<?php echo $alumno['inscripcion_id']; ?>">
                            <input type="hidden" name="clase_id" value="<?php echo $clase_id; ?>">
                            <button type="submit"
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-zinc-200 text-zinc-400 hover:bg-rose-500 hover:text-white hover:border-rose-500 transition-all shadow-sm"
                                title="Dar de baja">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>


    <!-- COLUMNA DERECHA: ALUMNOS DISPONIBLES -->
    <div class="flex flex-col h-full bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <div class="px-6 py-4 bg-zinc-50 border-b border-zinc-200">
            <div class="flex justify-between items-center mb-3">
                <h5 class="text-sm font-bold uppercase tracking-wider text-zinc-500 flex items-center">
                    <i class="fas fa-list-ul mr-2"></i> Disponibles
                </h5>
                <span
                    class="bg-zinc-100 text-zinc-600 text-xs font-bold px-2 py-1 rounded-full"><?php echo count($alumnos_disponibles); ?></span>
            </div>

            <?php if (!empty($grupos_existentes)): ?>
            <form action="../../back/admin_actions_asignacion.php" method="POST" class="mb-3 flex gap-2">
                <input type="hidden" name="action" value="import_group">
                <input type="hidden" name="clase_id" value="<?php echo $clase_id; ?>">
                <select name="source_grupo" class="flex-1 text-xs border border-zinc-200 rounded py-2 px-3 focus:border-zinc-800 focus:outline-none bg-white transition-colors" required>
                    <option value="">-- Importar alumnos de grupo --</option>
                    <?php foreach ($grupos_existentes as $g): ?>
                        <option value="<?php echo htmlspecialchars($g); ?>">Grupo <?php echo htmlspecialchars($g); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-zinc-800 text-white text-xs px-3 py-2 rounded hover:bg-zinc-700 transition-colors shadow-sm whitespace-nowrap">
                    <i class="fas fa-file-import mr-1"></i> Traer
                </button>
            </form>
            <?php endif; ?>

            <!-- QUICK SEARCH -->
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-zinc-400 text-xs"></i>
                <input type="text" id="filterAvailable" placeholder="Buscar alumno..."
                    class="w-full pl-9 pr-3 py-2 text-xs border border-zinc-200 rounded focus:border-zinc-800 focus:outline-none bg-white transition-colors">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2" id="availableList">
            <?php if (empty($alumnos_disponibles)): ?>
                <div class="flex flex-col items-center justify-center h-full text-zinc-400 opacity-60">
                    <i class="fas fa-check-circle text-4xl mb-2"></i>
                    <p class="text-sm">Todos los alumnos están inscritos.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alumnos_disponibles as $alumno): ?>
                    <div
                        class="student-item group flex items-center justify-between p-3 rounded-lg border border-zinc-100 hover:bg-zinc-50 transition-all">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div
                                class="w-8 h-8 rounded-full bg-zinc-100 text-zinc-400 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="truncate">
                                <p class="student-name text-sm font-bold text-zinc-700 leading-none truncate">
                                    <?php echo htmlspecialchars($alumno['nombre_completo']); ?>
                                </p>
                                <p class="text-[10px] text-zinc-400 font-mono mt-1">
                                    <?php echo htmlspecialchars($alumno['curp']); ?>
                                </p>
                            </div>
                        </div>

                        <form action="../../back/admin_actions_asignacion.php" method="POST" class="flex-shrink-0 ml-2">
                            <input type="hidden" name="action" value="enroll_student">
                            <input type="hidden" name="alumno_id" value="<?php echo $alumno['id']; ?>">
                            <input type="hidden" name="clase_id" value="<?php echo $clase_id; ?>">
                            <button type="submit"
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-900 text-white hover:bg-emerald-600 hover:scale-105 transition-all shadow-md"
                                title="Inscribir">
                                <i class="fas fa-plus text-xs"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    // Filtro Simple JS
    document.getElementById('filterAvailable').addEventListener('keyup', function () {
        let filter = this.value.toUpperCase();
        let list = document.getElementById('availableList');
        let items = list.querySelectorAll('.student-item');

        items.forEach(item => {
            let name = item.querySelector('.student-name').textContent || item.querySelector('.student-name').innerText;
            if (name.toUpperCase().indexOf(filter) > -1) {
                item.style.display = "";
            } else {
                item.style.display = "none";
            }
        });
    });
</script>