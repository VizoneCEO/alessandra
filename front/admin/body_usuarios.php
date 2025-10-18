<?php
// Incluimos la conexión para poder hacer consultas
require '../../back/db_connect.php';

// Consultamos los usuarios y sus perfiles
$sql_users = "SELECT u.id, u.nombre_completo, u.curp, p.nombre_perfil FROM Usuarios u JOIN Perfiles p ON u.perfil_id = p.id ORDER BY u.id";
$result_users = $conn->query($sql_users);

// Consultamos los perfiles para los dropdowns
$sql_profiles = "SELECT * FROM Perfiles";
$result_profiles = $conn->query($sql_profiles);
$profiles = $result_profiles->fetch_all(MYSQLI_ASSOC);
?>

<h3 class="fs-4 mb-3">Gestor de Usuarios</h3>

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
        <i class="fas fa-user-plus me-1"></i>
        Registrar Nuevo Usuario
    </div>
    <div class="card-body">
        <form action="../../back/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="create_user">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="nombre_completo" class="form-label">Nombre Completo</label>
                    <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="curp" class="form-label">CURP</label>
                    <input type="text" class="form-control" id="curp" name="curp" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="perfil_id" class="form-label">Perfil</label>
                    <select class="form-select" id="perfil_id" name="perfil_id" required>
                        <?php foreach ($profiles as $profile): ?>
                            <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['nombre_perfil']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end mb-3">
                    <button type="submit" class="btn btn-primary w-100">Crear</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-users me-1"></i>
        Lista de Usuarios
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>CURP</th>
                        <th>Perfil</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result_users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($row['curp']); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre_perfil']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                    data-user-id="<?php echo $row['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($row['nombre_completo']); ?>"
                                    onclick="prepareEditModal(this)">
                                <i class="fas fa-edit"></i> Editar
                            </button>

                            <form action="../../back/admin_actions.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres liberar la contraseña de este usuario?');">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-info">
                                    <i class="fas fa-key"></i> Liberar Pass
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

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../../back/admin_actions.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Editar Perfil de Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_profile">
                    <input type="hidden" id="edit_user_id" name="user_id">

                    <div class="mb-3">
                        <label class="form-label">Usuario:</label>
                        <p id="edit_user_name" class="fw-bold"></p>
                    </div>

                    <div class="mb-3">
                        <label for="edit_perfil_id" class="form-label">Cambiar Perfil a:</label>
                        <select class="form-select" id="edit_perfil_id" name="perfil_id" required>
                             <?php foreach ($profiles as $profile): ?>
                                <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['nombre_perfil']); ?></option>
                            <?php endforeach; ?>
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
// Pequeño script para pasar los datos del usuario al modal de edición
function prepareEditModal(button) {
    const userId = button.getAttribute('data-user-id');
    const userName = button.getAttribute('data-user-name');

    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_user_name').innerText = userName;
}
</script>