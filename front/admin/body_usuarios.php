<?php
require '../../back/db_connect.php';

// Modificamos la consulta para incluir el estado y ordenar por nombre
$sql_users = "SELECT u.id, u.nombre_completo, u.curp, p.nombre_perfil, u.estado 
              FROM Usuarios u 
              JOIN Perfiles p ON u.perfil_id = p.id 
              ORDER BY u.nombre_completo"; // Ordenamos por nombre
$result_users = $conn->query($sql_users);

$sql_profiles = "SELECT * FROM Perfiles";
$result_profiles = $conn->query($sql_profiles);
$profiles = $result_profiles->fetch_all(MYSQLI_ASSOC);
?>

<h3 class="fs-4 mb-3">Gestor de Usuarios</h3>

<?php
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-' . $_SESSION['message']['type'] . ' alert-dismissible fade show" role="alert">'
         . htmlspecialchars($_SESSION['message']['text']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['message']);
}
?>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-user-plus me-1"></i>Registrar Nuevo Usuario</div>
    <div class="card-body">
        <form action="../../back/admin_actions.php" method="POST">
             <input type="hidden" name="action" value="create_user">
            <div class="row">
                <div class="col-md-4 mb-3"><label for="nombre_completo" class="form-label">Nombre Completo</label><input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required></div>
                <div class="col-md-4 mb-3"><label for="curp" class="form-label">CURP</label><input type="text" class="form-control" id="curp" name="curp" required></div>
                <div class="col-md-3 mb-3"><label for="perfil_id" class="form-label">Perfil</label><select class="form-select" id="perfil_id" name="perfil_id" required><?php foreach ($profiles as $profile){ echo "<option value='{$profile['id']}'>".htmlspecialchars($profile['nombre_perfil'])."</option>"; } ?></select></div>
                <div class="col-md-1 d-flex align-items-end mb-3"><button type="submit" class="btn btn-primary w-100">Crear</button></div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6"><i class="fas fa-users me-1"></i>Lista de Usuarios</div>
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre o CURP...">
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="userTable">
                <thead>
                    <tr>
                        <th>Nombre Completo</th>
                        <th>CURP</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result_users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($row['curp']); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_perfil']); ?></td>
                        <td>
                            <span class="badge <?php echo $row['estado'] == 'activo' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ucfirst($row['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                    data-user-id="<?php echo $row['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($row['nombre_completo']); ?>"
                                    data-user-profile-id="<?php echo $row['perfil_id']; ?>"
                                    onclick="prepareEditModal(this)">
                                <i class="fas fa-edit"></i>
                            </button>

                            <form action="../../back/admin_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Liberar contraseña para <?php echo htmlspecialchars($row['nombre_completo']); ?>?');">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-info" title="Liberar Contraseña"><i class="fas fa-key"></i></button>
                            </form>

                            <form action="../../back/admin_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Cambiar estado de <?php echo htmlspecialchars($row['nombre_completo']); ?>?');">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $row['estado'] == 'activo' ? 'btn-secondary' : 'btn-success'; ?>"
                                        title="<?php echo $row['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>">
                                    <i class="fas <?php echo $row['estado'] == 'activo' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
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

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../../back/admin_actions.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_profile">
                    <input type="hidden" id="edit_user_id" name="user_id">

                    <div class="mb-3">
                        <label for="edit_nombre_completo" class="form-label">Nombre Completo:</label>
                        <input type="text" class="form-control" id="edit_nombre_completo" name="nombre_completo" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_perfil_id" class="form-label">Cambiar Perfil a:</label>
                        <select class="form-select" id="edit_perfil_id" name="perfil_id" required>
                             <?php foreach ($profiles as $profile){ echo "<option value='{$profile['id']}'>".htmlspecialchars($profile['nombre_perfil'])."</option>"; } ?>
                        </select>
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
// Función para poblar el modal de edición (actualizada)
function prepareEditModal(button) {
    document.getElementById('edit_user_id').value = button.getAttribute('data-user-id');
    document.getElementById('edit_nombre_completo').value = button.getAttribute('data-user-name'); // Ahora llena el input
    document.getElementById('edit_perfil_id').value = button.getAttribute('data-user-profile-id'); // Selecciona el perfil actual
}

// Script para la barra de búsqueda
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toUpperCase();
    const table = document.getElementById('userTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const nameCol = rows[i].getElementsByTagName('td')[0]; // Columna Nombre
        const curpCol = rows[i].getElementsByTagName('td')[1]; // Columna CURP
        if (nameCol || curpCol) {
            const nameText = nameCol.textContent || nameCol.innerText;
            const curpText = curpCol.textContent || curpCol.innerText;
            if (nameText.toUpperCase().indexOf(filter) > -1 || curpText.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }
});
</script>