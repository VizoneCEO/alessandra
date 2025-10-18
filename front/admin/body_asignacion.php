<?php
// --- 1. OBTENER DATOS ---
require '../../back/db_connect.php';

// Verificamos si se pasó un ID de clase en la URL
if (!isset($_GET['clase_id'])) {
    echo '<div class="alert alert-info">Por favor, selecciona una clase desde el panel de "Gestión de Materias" para empezar a asignar alumnos.</div>';
    // Salimos del script si no hay clase seleccionada
    return;
}

$clase_id = (int)$_GET['clase_id'];

// a) Obtener detalles de la clase seleccionada (Materia, Profesor, Ciclo)
$sql_clase_details = "SELECT m.nombre_materia, u.nombre_completo as profesor_nombre, ce.nombre_ciclo
                      FROM Clases c
                      JOIN Materias m ON c.materia_id = m.id
                      JOIN Usuarios u ON c.profesor_id = u.id
                      JOIN Ciclos_Escolares ce ON c.ciclo_id = ce.id
                      WHERE c.id = $clase_id";
$clase_details = $conn->query($sql_clase_details)->fetch_assoc();


// b) Obtener alumnos YA INSCRITOS en esta clase
$sql_inscritos = "SELECT i.id as inscripcion_id, u.id as alumno_id, u.nombre_completo, u.curp
                  FROM Inscripciones i
                  JOIN Usuarios u ON i.alumno_id = u.id
                  WHERE i.clase_id = $clase_id AND u.perfil_id = 3
                  ORDER BY u.nombre_completo";
$alumnos_inscritos = $conn->query($sql_inscritos)->fetch_all(MYSQLI_ASSOC);
$inscritos_ids = array_column($alumnos_inscritos, 'alumno_id');


// c) Obtener alumnos DISPONIBLES (que no estén en la lista de inscritos)
$sql_disponibles = "SELECT id, nombre_completo, curp FROM Usuarios WHERE perfil_id = 3";
if (!empty($inscritos_ids)) {
    // Excluimos a los que ya están inscritos
    $sql_disponibles .= " AND id NOT IN (" . implode(',', $inscritos_ids) . ")";
}
$sql_disponibles .= " ORDER BY nombre_completo";
$alumnos_disponibles = $conn->query($sql_disponibles)->fetch_all(MYSQLI_ASSOC);

?>

<h3 class="fs-4 mb-3">Asignación de Alumnos</h3>

<div class="alert alert-secondary">
    Asignando alumnos para:
    <strong>Materia:</strong> <?php echo htmlspecialchars($clase_details['nombre_materia']); ?> |
    <strong>Profesor:</strong> <?php echo htmlspecialchars($clase_details['profesor_nombre']); ?> |
    <strong>Ciclo:</strong> <?php echo htmlspecialchars($clase_details['nombre_ciclo']); ?>
</div>


<?php
// Mostrar mensajes de sesión
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . $_SESSION['message']['type'] . ' alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['message']['text']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['message']);
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-check-circle me-1"></i>
                Alumnos Inscritos en esta Clase (<?php echo count($alumnos_inscritos); ?>)
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <ul class="list-group list-group-flush">
                    <?php if (empty($alumnos_inscritos)): ?>
                        <li class="list-group-item">Aún no hay alumnos inscritos.</li>
                    <?php else: ?>
                        <?php foreach($alumnos_inscritos as $alumno): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($alumno['nombre_completo']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($alumno['curp']); ?></small>
                                </div>
                                <form action="../../back/admin_actions_asignacion.php" method="POST">
                                    <input type="hidden" name="action" value="unenroll_student">
                                    <input type="hidden" name="inscripcion_id" value="<?php echo $alumno['inscripcion_id']; ?>">
                                    <input type="hidden" name="clase_id" value="<?php echo $clase_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Dar de baja de la clase"><i class="fas fa-user-minus"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-list-ul me-1"></i>
                Alumnos Disponibles para Inscribir (<?php echo count($alumnos_disponibles); ?>)
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                 <ul class="list-group list-group-flush">
                    <?php if (empty($alumnos_disponibles)): ?>
                        <li class="list-group-item">No hay más alumnos disponibles.</li>
                    <?php else: ?>
                        <?php foreach($alumnos_disponibles as $alumno): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                               <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($alumno['nombre_completo']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($alumno['curp']); ?></small>
                                </div>
                                <form action="../../back/admin_actions_asignacion.php" method="POST">
                                    <input type="hidden" name="action" value="enroll_student">
                                    <input type="hidden" name="alumno_id" value="<?php echo $alumno['id']; ?>">
                                    <input type="hidden" name="clase_id" value="<?php echo $clase_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Inscribir en esta clase"><i class="fas fa-user-plus"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>