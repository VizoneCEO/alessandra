<?php
// Incluimos la conexión para poder hacer consultas
require '../../back/db_connect.php';

// Consultamos los ciclos existentes para mostrarlos en la tabla
$sql_ciclos = "SELECT * FROM Ciclos_Escolares ORDER BY fecha_inicio DESC";
$result_ciclos = $conn->query($sql_ciclos);
?>

<h3 class="fs-4 mb-3">Gestión de Ciclos Escolares</h3>

<?php
// Mostrar mensajes de éxito o error que vengan del backend
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . $_SESSION['message']['type'] . ' alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['message']['text']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['message']);
}
?>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-calendar-plus me-1"></i>
        Crear Nuevo Ciclo Escolar
    </div>
    <div class="card-body">
        <form action="../../back/admin_actions_ciclos.php" method="POST">
            <input type="hidden" name="action" value="create_ciclo">
            <div class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label for="nombre_ciclo" class="form-label">Nombre del Ciclo</label>
                    <input type="text" class="form-control" id="nombre_ciclo" name="nombre_ciclo" placeholder="Ej: Apertura 2025" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="submit" class="btn btn-primary w-100">Crear Ciclo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-calendar-alt me-1"></i>
        Ciclos Registrados
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre del Ciclo</th>
                        <th>Fecha de Inicio</th>
                        <th>Fecha de Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result_ciclos->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombre_ciclo']); ?></td>
                        <td><?php echo $row['fecha_inicio']; ?></td>
                        <td><?php echo $row['fecha_fin']; ?></td>
                        <td>
                            <?php
                                $estado = htmlspecialchars($row['estado']);
                                $badge_class = 'bg-secondary';
                                if ($estado == 'activo') $badge_class = 'bg-success';
                                if ($estado == 'cerrado') $badge_class = 'bg-danger';
                                echo "<span class='badge $badge_class'>$estado</span>";
                            ?>
                        </td>
                        <td>
                            <form action="../../back/admin_actions_ciclos.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="ciclo_id" value="<?php echo $row['id']; ?>">
                                <select name="nuevo_estado" class="form-select form-select-sm d-inline" style="width: auto;" onchange="this.form.submit()">
                                    <option value="">Cambiar a...</option>
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                    <option value="cerrado">Cerrado</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>