<?php
// Incluimos la conexión para poder hacer consultas
require '../../back/db_connect.php';

// Consultamos las sucursales existentes para la tabla
$sql_sucursales = "SELECT * FROM Sucursales ORDER BY nombre_sucursal ASC";
$result_sucursales = $conn->query($sql_sucursales);
?>

<h3 class="fs-4 mb-3">Gestión de Sucursales</h3>

<?php
// Mostrar mensajes de éxito o error
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
        <i class="fas fa-plus-circle me-1"></i>
        Crear Nueva Sucursal
    </div>
    <div class="card-body">
        <form action="../../back/admin_actions_sucursales.php" method="POST">
            <input type="hidden" name="action" value="create_sucursal">
            <div class="row align-items-end">
                <div class="col-md-6 mb-3">
                    <label for="nombre_sucursal" class="form-label">Nombre de la Sucursal</label>
                    <input type="text" class="form-control" id="nombre_sucursal" name="nombre_sucursal" placeholder="Ej: Plantel Centro" required>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="btn btn-primary w-100">Crear Sucursal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-building me-1"></i>
        Sucursales Registradas
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre de la Sucursal</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result_sucursales->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombre_sucursal']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSucursalModal"
                                    data-sucursal-id="<?php echo $row['id']; ?>"
                                    data-sucursal-nombre="<?php echo htmlspecialchars($row['nombre_sucursal']); ?>"
                                    onclick="prepareEditModal(this)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <form action="../../back/admin_actions_sucursales.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta sucursal? \n¡OJO! Esto borrará todas las clases y asignaciones asociadas a ella.');">
                                <input type="hidden" name="action" value="delete_sucursal">
                                <input type="hidden" name="sucursal_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editSucursalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../../back/admin_actions_sucursales.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Sucursal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_sucursal">
                    <input type="hidden" id="edit_sucursal_id" name="sucursal_id">

                    <div class="mb-3">
                        <label for="edit_nombre_sucursal" class="form-label">Nombre de la Sucursal:</label>
                        <input type="text" class="form-control" id="edit_nombre_sucursal" name="nombre_sucursal" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para poblar el modal de edición
function prepareEditModal(button) {
    document.getElementById('edit_sucursal_id').value = button.getAttribute('data-sucursal-id');
    document.getElementById('edit_nombre_sucursal').value = button.getAttribute('data-sucursal-nombre');
}
</script>