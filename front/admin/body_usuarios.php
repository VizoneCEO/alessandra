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
        <div class="row gy-2 align-items-center">
            <div class="col-md-4">
                <i class="fas fa-users me-1"></i>Lista de Usuarios
            </div>
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre o CURP...">
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group" role="group" id="profileFilterButtons">
                    <button type="button" class="btn btn-sm btn-outline-secondary active" data-filter="Todos">Todos</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-filter="administrador">Admin</button>
                    <button type="button" class="btn btn-sm btn-outline-info" data-filter="profesor">Profesor</button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-filter="alumno">Alumno</button>
                </div>
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
// Función para poblar el modal de edición (sin cambios)
function prepareEditModal(button) {
    document.getElementById('edit_user_id').value = button.getAttribute('data-user-id');
    document.getElementById('edit_nombre_completo').value = button.getAttribute('data-user-name');
    document.getElementById('edit_perfil_id').value = button.getAttribute('data-user-profile-id');
}

// --- NUEVO SCRIPT DE FILTRADO COMBINADO ---

// Variable global para guardar el filtro de perfil seleccionado
let currentProfileFilter = 'Todos';

// Obtenemos las referencias a los elementos una sola vez
const searchInput = document.getElementById('searchInput');
const table = document.getElementById('userTable');
const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
const filterButtons = document.getElementById('profileFilterButtons');

/**
 * Función principal que filtra la tabla basándose en
 * el texto de búsqueda Y el botón de perfil activo.
 */
function filterTable() {
    const searchText = searchInput.value.toUpperCase();

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const nameCol = row.getElementsByTagName('td')[0]; // Columna 0: Nombre
        const curpCol = row.getElementsByTagName('td')[1]; // Columna 1: CURP
        const profileCol = row.getElementsByTagName('td')[2]; // Columna 2: Perfil

        if (!nameCol || !curpCol || !profileCol) continue;

        const nameText = nameCol.textContent || nameCol.innerText;
        const curpText = curpCol.textContent || curpCol.innerText;
        // Obtenemos el texto del perfil, limpiamos espacios y lo pasamos a minúscula
        const profileText = (profileCol.textContent || profileCol.innerText).trim().toLowerCase();

        // 1. Comprobar filtro de texto
        const textMatch = (nameText.toUpperCase().indexOf(searchText) > -1) ||
                          (curpText.toUpperCase().indexOf(searchText) > -1);

        // 2. Comprobar filtro de perfil
        const profileMatch = (currentProfileFilter === 'Todos') ||
                             (profileText === currentProfileFilter);

        // Mostrar la fila solo si AMBOS filtros coinciden
        if (textMatch && profileMatch) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    }
}

// 1. Asignar evento 'keyup' al campo de búsqueda
searchInput.addEventListener('keyup', filterTable);

// 2. Asignar eventos 'click' a los botones de filtro
filterButtons.addEventListener('click', function(e) {
    // Asegurarnos que se hizo clic en un botón
    if (e.target.tagName === 'BUTTON') {

        // Actualizar la variable global con el valor del data-filter
        currentProfileFilter = e.target.getAttribute('data-filter');

        // Quitar la clase 'active' de todos los botones del grupo
        const buttons = this.getElementsByTagName('button');
        for (let btn of buttons) {
            btn.classList.remove('active');
        }
        // Añadir la clase 'active' solo al botón presionado
        e.target.classList.add('active');

        // Ejecutar el filtro
        filterTable();
    }
});

</script>