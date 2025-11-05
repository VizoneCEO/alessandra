<?php
// --- 1. OBTENER DATOS ESENCIALES ---
require '../../back/db_connect.php';

// a) Obtener TODOS los ciclos escolares para el selector
$todos_ciclos = $conn->query("SELECT id, nombre_ciclo, estado FROM Ciclos_Escolares ORDER BY fecha_inicio DESC")->fetch_all(MYSQLI_ASSOC);

// b) Determinar el ciclo seleccionado. Prioridad: GET > activo > el primero de la lista
$selected_ciclo_id = null;
$selected_ciclo_nombre = "Ninguno seleccionado";

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
// Si aún no hay ciclo, seleccionamos el primero si existe
if (!$selected_ciclo_id && !empty($todos_ciclos)) {
    $selected_ciclo_id = $todos_ciclos[0]['id'];
}
// Obtenemos el nombre del ciclo seleccionado
if ($selected_ciclo_id) {
    foreach($todos_ciclos as $ciclo){
        if($ciclo['id'] == $selected_ciclo_id) {
            $selected_ciclo_nombre = $ciclo['nombre_ciclo'];
            break;
        }
    }
}


// c) Obtener lista de todos los profesores
$profesores = $conn->query("SELECT id, nombre_completo FROM Usuarios WHERE perfil_id = 2 ORDER BY nombre_completo")->fetch_all(MYSQLI_ASSOC);

// d) Obtener todas las materias del catálogo
$materias_catalogo = $conn->query("SELECT * FROM Materias ORDER BY semestre, nombre_materia")->fetch_all(MYSQLI_ASSOC);

// e) Obtener las clases asignadas SOLO para el ciclo seleccionado
$clases_asignadas = [];
if ($selected_ciclo_id) {
    $sql_clases = "SELECT c.id, c.materia_id, u.nombre_completo AS profesor_nombre 
                   FROM Clases c 
                   JOIN Usuarios u ON c.profesor_id = u.id 
                   WHERE c.ciclo_id = " . $selected_ciclo_id;
    $result_clases = $conn->query($sql_clases);
    while($row = $result_clases->fetch_assoc()) {
        $clases_asignadas[$row['materia_id']][] = $row;
    }
}

// --- NUEVA SECCIÓN: OBTENER CONTEO DE ALUMNOS ---
$conteo_alumnos = [];
$lista_de_clases_ids = [];

// 1. Aplanamos el array $clases_asignadas para sacar todos los IDs de las clases
foreach ($clases_asignadas as $materia_grupo) {
    foreach ($materia_grupo as $clase) {
        $lista_de_clases_ids[] = $clase['id'];
    }
}

// 2. Si hay clases, hacemos UNA SOLA consulta para contarlos a todos
if (!empty($lista_de_clases_ids)) {
    // Creamos un string de placeholders: ?,?,?
    $placeholders = implode(',', array_fill(0, count($lista_de_clases_ids), '?'));
    // Tipos: iiii... (uno por cada ID)
    $tipos = str_repeat('i', count($lista_de_clases_ids));

    $sql_conteo = "SELECT clase_id, COUNT(id) as total_alumnos
                   FROM Inscripciones
                   WHERE clase_id IN ($placeholders)
                   GROUP BY clase_id";

    $stmt_conteo = $conn->prepare($sql_conteo);
    // Hacemos el bind_param dinámicamente
    $stmt_conteo->bind_param($tipos, ...$lista_de_clases_ids);
    $stmt_conteo->execute();
    $result_conteo = $stmt_conteo->get_result();

    while ($row_conteo = $result_conteo->fetch_assoc()) {
        $conteo_alumnos[$row_conteo['clase_id']] = $row_conteo['total_alumnos'];
    }
    $stmt_conteo->close();
}
// Ahora $conteo_alumnos tiene [clase_id] => count
?>

<h3 class="fs-4 mb-3">Gestión de Materias y Asignaciones</h3>

<?php
// Mostrar mensajes de sesión
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . $_SESSION['message']['type'] . ' alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['message']['text']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['message']);
}
?>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-filter me-1"></i>Seleccionar Ciclo Escolar de Trabajo</div>
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="page" value="materias">
            <div class="input-group">
                <select name="ciclo_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Selecciona un ciclo...</option>
                    <?php foreach ($todos_ciclos as $ciclo): ?>
                        <option value="<?php echo $ciclo['id']; ?>" <?php echo ($ciclo['id'] == $selected_ciclo_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ciclo['nombre_ciclo']) . ($ciclo['estado'] == 'activo' ? ' (Activo)' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>


<div class="card mb-4">
    <div class="card-header"><i class="fas fa-plus-circle me-1"></i>Añadir Nueva Materia al Catálogo</div>
    <div class="card-body">
        <form action="../../back/admin_actions_materias.php" method="POST" class="row align-items-end">
            <input type="hidden" name="action" value="create_materia">
            <div class="col-md-6"><label for="nombre_materia" class="form-label">Nombre de la Materia</label><input type="text" class="form-control" name="nombre_materia" required></div>
            <div class="col-md-4"><label for="semestre" class="form-label">Semestre</label><select class="form-select" name="semestre" required><option value="">Selecciona...</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Añadir</button></div>
        </form>
    </div>
</div>


<h4>Mostrando asignaciones para el ciclo: <span class="text-success fw-bold"><?php echo htmlspecialchars($selected_ciclo_nombre); ?></span></h4>

<?php for ($i = 1; $i <= 6; $i++): ?>
    <div class="row mt-4">
        <div class="col">
            <h5 class="text-center fw-bold mb-3 border-bottom pb-2">Semestre <?php echo $i; ?></h5>
        </div>
    </div>

    <div class="row g-3">

        <?php
        $materias_en_semestre = false; // Bandera para saber si hay materias
        ?>

        <?php foreach ($materias_catalogo as $materia): ?>
            <?php if ($materia['semestre'] == $i): ?>

                <?php $materias_en_semestre = true; ?>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                    <div class="materia-card h-100">
                        <div class="materia-card-header d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($materia['nombre_materia']); ?></span>
                            <form action="../../back/admin_actions_materias.php" method="POST" onsubmit="return confirm('¡ALERTA!\n¿Estás seguro de eliminar esta materia del catálogo?\nEsta acción es permanente y borrará todas sus asignaciones en TODOS los ciclos.');">
                                <input type="hidden" name="action" value="delete_materia">
                                <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                                <button type="submit" class="delete-clase-btn" title="Eliminar materia del catálogo"><i class="fas fa-times-circle"></i></button>
                            </form>
                        </div>

                        <div class="materia-card-body">
                            <?php if (isset($clases_asignadas[$materia['id']])): ?>
                                <?php foreach ($clases_asignadas[$materia['id']] as $clase): ?>

                                    <?php
                                    // Obtenemos el conteo para esta clase, default 0
                                    $total_alumnos = $conteo_alumnos[$clase['id']] ?? 0;
                                    $conteo_class = '';
                                    $conteo_icon = '';

                                    if ($total_alumnos == 0) {
                                        $conteo_class = 'conteo-alumnos-rojo';
                                        $conteo_icon = 'fa-exclamation-triangle';
                                    } else {
                                        $conteo_class = 'conteo-alumnos-normal';
                                        $conteo_icon = 'fa-users';
                                    }
                                    ?>

                                    <div class="clase-conteo-wrapper">
                                        <div class="clase-card">
                                            <span class="profesor-name"><i class="fas fa-user-tie me-2"></i><?php echo htmlspecialchars($clase['profesor_nombre']); ?></span>
                                            <div>
                                                <a href="dashboard.php?page=asignacion&clase_id=<?php echo $clase['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Asignar Alumnos">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                                <form action="../../back/admin_actions_materias.php" method="POST" onsubmit="return confirm('¿Quitar a este profesor de la materia?');" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_clase">
                                                    <input type="hidden" name="clase_id" value="<?php echo $clase['id']; ?>">
                                                    <button type="submit" class="delete-clase-btn" title="Quitar asignación"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="conteo-alumnos <?php echo $conteo_class; ?>">
                                            <i class="fas <?php echo $conteo_icon; ?> me-1"></i>
                                            <?php echo $total_alumnos; ?> alumno<?php echo ($total_alumnos != 1) ? 's' : ''; ?>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="materia-card-footer">
                            <?php if ($selected_ciclo_id): ?>
                            <form action="../../back/admin_actions_materias.php" method="POST">
                                <input type="hidden" name="action" value="create_clase">
                                <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                                <input type="hidden" name="ciclo_id" value="<?php echo $selected_ciclo_id; ?>">
                                <div class="input-group input-group-sm">
                                    <select name="profesor_id" class="form-select" required>
                                        <option value="">Asignar a...</option>
                                        <?php foreach($profesores as $profesor): ?>
                                            <option value="<?php echo $profesor['id']; ?>"><?php echo htmlspecialchars($profesor['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-outline-success" type="submit">+</button>
                                </div>
                            </form>
                            <?php else: ?>
                                <p class="text-muted small mt-2">Selecciona un ciclo para poder asignar profesores.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$materias_en_semestre): ?>
            <div class="col">
                <p class="text-muted">No hay materias registradas para este semestre.</p>
            </div>
        <?php endif; ?>

    </div> <?php endfor; ?>