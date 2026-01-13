<?php
require '../../back/db_connect.php';

// Modificamos la consulta para incluir el estado y ordenar por nombre
// Modificamos la consulta para incluir el estado, cuenta y forma
$sql_users = "SELECT u.id, u.nombre_completo, u.curp, u.perfil_id, p.nombre_perfil, u.estado, u.cuenta_deposito_id, u.forma, c.banco, c.titular
              FROM Usuarios u 
              JOIN Perfiles p ON u.perfil_id = p.id 
              LEFT JOIN Finanzas_Cuentas c ON u.cuenta_deposito_id = c.id
              ORDER BY u.nombre_completo";
$result_users = $conn->query($sql_users);

$sql_profiles = "SELECT * FROM Perfiles";
$result_profiles = $conn->query($sql_profiles);
$profiles = $result_profiles->fetch_all(MYSQLI_ASSOC);

// Fetch Deposit Accounts + Student Count
$sql_accounts = "SELECT c.*, COUNT(u.id) as total_students 
                 FROM Finanzas_Cuentas c 
                 LEFT JOIN Usuarios u ON u.cuenta_deposito_id = c.id 
                 WHERE c.activo = 1 
                 GROUP BY c.id";
$result_accounts = $conn->query($sql_accounts);
$accounts = [];
while ($acc = $result_accounts->fetch_assoc()) {
    $accounts[] = $acc;
}
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Gestor de Usuarios</h3>
    <p class="text-zinc-500 font-light text-sm">Administración de cuentas académicas y administrativas.</p>
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

<!-- Crear Usuario -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8">
    <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-4"><i class="fas fa-user-plus mr-1"></i>
        Registrar Nuevo Usuario</h6>
    <form action="../../back/admin_actions.php" method="POST">
        <input type="hidden" name="action" value="create_user">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
            <div>
                <label for="nombre_completo" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Nombre
                    Completo</label>
                <input type="text"
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors"
                    id="nombre_completo" name="nombre_completo" required placeholder="Nombre Apellido">
            </div>
            <div>
                <label for="curp" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">CURP</label>
                <input type="text"
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors"
                    id="curp" name="curp" required placeholder="Clave Única">
            </div>
            <div>
                <label for="perfil_id" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Perfil</label>
                <select
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors"
                    id="perfil_id" name="perfil_id" required>
                    <?php foreach ($profiles as $profile) {
                        echo "<option value='{$profile['id']}'>" . htmlspecialchars($profile['nombre_perfil']) . "</option>";
                    } ?>
                </select>
            </div>
            <div>
                <label for="forma" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Forma</label>
                <select
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors"
                    id="forma" name="forma" required>
                    <option value="presencial">Presencial</option>
                    <option value="online">Online</option>
                </select>
            </div>
            <div>
                <button type="submit"
                    class="w-full py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors rounded">
                    Crear Cuenta
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Cuentas de Depósito Section -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8">
    <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-4"><i class="fas fa-university mr-1"></i>
        Cuentas de Depósito</h6>

    <div class="flex flex-col md:flex-row gap-8">
        <!-- Form New Account -->
        <div class="w-full md:w-1/3 border-r border-zinc-100 pr-0 md:pr-8">
            <p class="text-sm font-bold text-zinc-800 mb-3">Agregar Nueva Cuenta</p>
            <form action="../../back/admin_actions.php" method="POST">
                <input type="hidden" name="action" value="create_account">
                <div class="space-y-4">
                    <div>
                        <input type="text" name="banco" required placeholder="Nombre del Banco"
                            class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors">
                    </div>
                    <div>
                        <input type="text" name="titular" required placeholder="Nombre del Titular"
                            class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="clabe" placeholder="CLABE (18 díg)"
                            class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors">
                        <input type="text" name="numero_cuenta" placeholder="No. Cuenta"
                            class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors">
                    </div>
                    <button type="submit"
                        class="w-full py-2 bg-emerald-600 text-white text-xs font-bold uppercase tracking-widest hover:bg-emerald-700 transition-colors rounded">
                        <i class="fas fa-plus mr-1"></i> Agregar Cuenta
                    </button>
                </div>
            </form>
        </div>

        <!-- Accounts List -->
        <div class="w-full md:w-2/3">
            <p class="text-sm font-bold text-zinc-800 mb-3">Cuentas Activas</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php if (count($accounts) > 0): ?>
                    <?php foreach ($accounts as $acc): ?>
                        <div
                            class="bg-zinc-50 border border-zinc-200 rounded-lg p-4 relative group hover:border-zinc-300 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h5 class="font-bold text-zinc-900 text-sm"><?php echo htmlspecialchars($acc['banco']); ?>
                                    </h5>
                                    <p class="text-xs text-zinc-500 uppercase tracking-wider">
                                        <?php echo htmlspecialchars($acc['titular']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-xs text-zinc-600 font-mono space-y-0.5">
                                <?php if ($acc['clabe']): ?>
                                    <p>CLABE: <?php echo htmlspecialchars($acc['clabe']); ?></p><?php endif; ?>
                                <?php if ($acc['numero_cuenta']): ?>
                                    <p>CTA: <?php echo htmlspecialchars($acc['numero_cuenta']); ?></p><?php endif; ?>
                            </div>

                            <div class="mt-3 pt-3 border-t border-zinc-200">
                                <p class="text-[10px] text-zinc-500 uppercase tracking-wide">
                                    <i class="fas fa-users text-emerald-600 mr-1"></i>
                                    <strong><?php echo $acc['total_students']; ?></strong> Alumnos asignados
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-2 text-center py-8 text-zinc-400 text-xs italic">
                        No hay cuentas registradas. Agrega una para comenzar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Usuarios -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
    <!-- Header Tooling -->
    <div class="p-6 border-b border-zinc-100 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="text-zinc-600 font-medium flex items-center">
            <i class="fas fa-users mr-2 text-zinc-400"></i> Directorio Activo
            <span id="totalActiveUsers"
                class="ml-2 bg-zinc-100 text-zinc-600 text-xs font-bold px-2 py-1 rounded-full border border-zinc-200"><?php echo $result_users->num_rows; ?></span>
        </div>

        <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
            <input type="text" id="searchInput"
                class="w-full md:w-64 border border-zinc-200 rounded-lg px-3 py-2 text-sm focus:border-zinc-900 outline-none"
                placeholder="Buscar por nombre o CURP...">

            <div class="flex rounded-lg border border-zinc-200 overflow-hidden" id="profileFilterButtons">
                <button type="button"
                    class="px-3 py-2 text-xs font-medium bg-zinc-900 text-white transition-colors border-r border-zinc-800"
                    data-filter="Todos">Todos</button>
                <button type="button"
                    class="px-3 py-2 text-xs font-medium bg-white text-zinc-600 hover:bg-zinc-50 transition-colors border-r border-zinc-200"
                    data-filter="administrador">Admin</button>
                <button type="button"
                    class="px-3 py-2 text-xs font-medium bg-white text-zinc-600 hover:bg-zinc-50 transition-colors border-r border-zinc-200"
                    data-filter="profesor">Profesor</button>
                <button type="button"
                    class="px-3 py-2 text-xs font-medium bg-white text-zinc-600 hover:bg-zinc-50 transition-colors"
                    data-filter="alumno">Alumno</button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto">
        <table class="w-full text-left" id="userTable">
            <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 font-medium">Nombre Completo</th>
                    <th class="px-6 py-3 font-medium">CURP</th>
                    <th class="px-6 py-3 font-medium">Perfil</th>
                    <th class="px-6 py-3 font-medium">Forma</th>
                    <th class="px-6 py-3 font-medium">Cuenta Depósito</th>
                    <th class="px-6 py-3 font-medium">Estado</th>
                    <th class="px-6 py-3 font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 text-sm text-slate-700">
                <?php while ($row = $result_users->fetch_assoc()): ?>
                    <tr class="hover:bg-zinc-50 transition-colors">
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                        <td class="px-6 py-4 font-mono text-xs text-zinc-500"><?php echo htmlspecialchars($row['curp']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-800">
                                <?php echo htmlspecialchars($row['nombre_perfil']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider <?php echo ($row['forma'] == 'online') ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'; ?>">
                                <?php echo htmlspecialchars($row['forma'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($row['banco']): ?>
                                <div class="text-xs">
                                    <p class="font-bold text-zinc-700"><?php echo htmlspecialchars($row['banco']); ?></p>
                                    <p class="text-[10px] text-zinc-500"><?php echo htmlspecialchars($row['titular']); ?></p>
                                </div>
                            <?php else: ?>
                                <span class="text-zinc-400 text-xs italic">--</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium <?php echo $row['estado'] == 'activo' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>">
                                <span
                                    class="w-1.5 h-1.5 rounded-full <?php echo $row['estado'] == 'activo' ? 'bg-emerald-500' : 'bg-rose-500'; ?>"></span>
                                <?php echo ucfirst($row['estado']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <?php if (strtolower($row['nombre_perfil']) == 'alumno' || $row['perfil_id'] == 3): ?>
                                    <a href="dashboard.php?page=alumno_setup&user_id=<?php echo $row['id']; ?>"
                                        class="text-zinc-600 hover:text-black transition-colors"
                                        title="Ver Expediente y Documentación">
                                        <i class="fas fa-folder-open fa-lg"></i>
                                    </a>

                                    <button
                                        onclick="openAssignModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['nombre_completo']); ?>', '<?php echo $row['cuenta_deposito_id'] ?? ''; ?>')"
                                        class="text-emerald-600 hover:text-emerald-800 transition-colors"
                                        title="Asignar Cuenta de Depósito">
                                        <i class="fas fa-university"></i>
                                    </button>
                                <?php endif; ?>

                                <button
                                    onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['nombre_completo']); ?>', '<?php echo $row['perfil_id']; ?>', '<?php echo $row['forma'] ?? 'presencial'; ?>')"
                                    class="text-amber-600 hover:text-amber-800 cursor-pointer transition-colors"
                                    title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form action="../../back/admin_actions.php" method="POST" class="inline"
                                    onsubmit="return confirm('¿Liberar contraseña de <?php echo htmlspecialchars($row['nombre_completo']); ?>?');">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit"
                                        class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                        title="Resetear Contraseña">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </form>

                                <form action="../../back/admin_actions.php" method="POST" class="inline"
                                    onsubmit="return confirm('¿Cambiar estado de <?php echo htmlspecialchars($row['nombre_completo']); ?>?');">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit"
                                        class="<?php echo $row['estado'] == 'activo' ? 'text-zinc-400 hover:text-zinc-600' : 'text-emerald-500 hover:text-emerald-700'; ?> cursor-pointer transition-colors"
                                        title="<?php echo $row['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>">
                                        <i
                                            class="fas <?php echo $row['estado'] == 'activo' ? 'fa-user-slash' : 'fa-check-circle'; ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit (Tailwind + Vanilla JS) -->
<div id="editUserModal"
    class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 opacity-100">
        <form action="../../back/admin_actions.php" method="POST">
            <div class="px-6 py-4 border-b border-zinc-100 flex justify-between items-center bg-zinc-50">
                <h5 class="font-bold text-zinc-900">Editar Usuario</h5>
                <button type="button" onclick="closeEditModal()"
                    class="text-zinc-400 hover:text-rose-500 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <input type="hidden" name="action" value="change_profile">
                <input type="hidden" id="edit_user_id" name="user_id">

                <div>
                    <label for="edit_nombre_completo"
                        class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Nombre Completo</label>
                    <input type="text"
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none"
                        id="edit_nombre_completo" name="nombre_completo" required>
                </div>

                <div>
                    <label for="edit_perfil_id"
                        class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Perfil</label>
                    <select
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-white"
                        id="edit_perfil_id" name="perfil_id" required>
                        <?php foreach ($profiles as $profile) {
                            echo "<option value='{$profile['id']}'>" . htmlspecialchars($profile['nombre_perfil']) . "</option>";
                        } ?>
                    </select>
                </div>

                <div>
                    <label for="edit_forma"
                        class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Forma</label>
                    <select
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-white"
                        id="edit_forma" name="forma" required>
                        <option value="presencial">Presencial</option>
                        <option value="online">Online</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 bg-zinc-50 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
                <button type="submit"
                    class="px-4 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider rounded hover:bg-zinc-800 transition-colors">Guardar
                    Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Assign Account -->
<div id="assignAccountModal"
    class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all scale-100 opacity-100">
        <form action="../../back/admin_actions.php" method="POST">
            <div class="px-6 py-4 border-b border-zinc-100 flex justify-between items-center bg-zinc-50">
                <h5 class="font-bold text-zinc-900">Asignar Cuenta de Depósito</h5>
                <button type="button" onclick="closeAssignModal()"
                    class="text-zinc-400 hover:text-rose-500 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" name="action" value="assign_account">
                <input type="hidden" id="assign_user_id" name="user_id">

                <div>
                    <p class="text-xs uppercase tracking-wider text-zinc-500 mb-2">Alumno</p>
                    <p id="assign_user_name" class="font-bold text-zinc-900 text-lg">Nombre Alumno</p>
                </div>

                <div>
                    <label for="assign_account_id"
                        class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Cuenta de Depósito</label>
                    <select
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-white"
                        id="assign_account_id" name="account_id">
                        <option value="NULL">-- Ninguna (Sin Asignar) --</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>">
                                <?php echo htmlspecialchars($acc['banco'] . ' - ' . $acc['titular']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="px-6 py-4 bg-zinc-50 flex justify-end gap-3">
                <button type="button" onclick="closeAssignModal()"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
                <button type="submit"
                    class="px-4 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider rounded hover:bg-zinc-800 transition-colors">
                    Guardar Asignación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAssignModal(userId, userName, currentAccountId) {
        document.getElementById('assign_user_id').value = userId;
        document.getElementById('assign_user_name').textContent = userName;
        document.getElementById('assign_account_id').value = currentAccountId || 'NULL';
        document.getElementById('assignAccountModal').classList.remove('hidden');
    }

    function closeAssignModal() {
        document.getElementById('assignAccountModal').classList.add('hidden');
    }
    // Logic for Modal
    function openEditModal(id, name, profileId, forma) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_nombre_completo').value = name;
        document.getElementById('edit_perfil_id').value = profileId;
        document.getElementById('edit_forma').value = forma;
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    // Logic for Filter (vanilla)
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('userTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const filterButtons = document.querySelectorAll('#profileFilterButtons button');
    let currentProfileFilter = 'Todos';

    // Update active state of buttons
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => {
                b.classList.remove('bg-zinc-900', 'text-white');
                b.classList.add('bg-white', 'text-zinc-600');
            });
            this.classList.remove('bg-white', 'text-zinc-600');
            this.classList.add('bg-zinc-900', 'text-white');

            currentProfileFilter = this.getAttribute('data-filter');
            filterTable();
        });
    });

    // Add Listener for Search
    searchInput.addEventListener('keyup', filterTable);

    function filterTable() {
        const searchText = searchInput.value.toUpperCase();
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const nameCol = row.getElementsByTagName('td')[0];
            const curpCol = row.getElementsByTagName('td')[1];
            const profileCol = row.getElementsByTagName('td')[2];

            if (!nameCol || !curpCol || !profileCol) continue;

            const nameText = nameCol.textContent || nameCol.innerText;
            const curpText = curpCol.textContent || curpCol.innerText;
            const profileText = (profileCol.textContent || profileCol.innerText).trim().toLowerCase();

            const textMatch = (nameText.toUpperCase().indexOf(searchText) > -1) ||
                (curpText.toUpperCase().indexOf(searchText) > -1);

            const profileMatch = (currentProfileFilter === 'Todos') ||
                (profileText.includes(currentProfileFilter.toLowerCase()));

            if (textMatch && profileMatch) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        }

        // Update Counter
        const counter = document.getElementById('totalActiveUsers');
        if (counter) counter.textContent = visibleCount;
    }

    searchInput.addEventListener('keyup', filterTable);
</script>