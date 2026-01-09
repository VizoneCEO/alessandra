<?php
require '../../back/db_connect.php';
$sql_sucursales = "SELECT * FROM Sucursales ORDER BY nombre_sucursal ASC";
$result_sucursales = $conn->query($sql_sucursales);
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Sucursales</h3>
    <p class="text-zinc-500 font-light text-sm">Administra las ubicaciones y planteles.</p>
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

<!-- Crear Sucursal -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8">
    <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-4"><i class="fas fa-plus-circle mr-1"></i>
        Registrar Nueva Sucursal</h6>
    <form action="../../back/admin_actions_sucursales.php" method="POST">
        <input type="hidden" name="action" value="create_sucursal">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
            <div class="md:col-span-8">
                <label for="nombre_sucursal" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Nombre de
                    la Sucursal</label>
                <input type="text"
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors"
                    id="nombre_sucursal" name="nombre_sucursal" placeholder="Ej: Plantel Centro" required>
            </div>
            <div class="md:col-span-4">
                <button type="submit"
                    class="w-full py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors rounded">
                    Crear Sucursal
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Tabla Sucursales -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
    <div class="p-6 border-b border-zinc-100">
        <div class="text-zinc-600 font-medium flex items-center">
            <i class="fas fa-building mr-2 text-zinc-400"></i> Sucursales Registradas
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 font-medium">Nombre de la Sucursal</th>
                    <th class="px-6 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 text-sm text-slate-700">
                <?php while ($row = $result_sucursales->fetch_assoc()): ?>
                    <tr class="hover:bg-zinc-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-zinc-900">
                            <?php echo htmlspecialchars($row['nombre_sucursal']); ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['nombre_sucursal']); ?>')"
                                    class="text-amber-600 hover:text-amber-800 cursor-pointer transition-colors"
                                    title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form action="../../back/admin_actions_sucursales.php" method="POST" class="inline"
                                    onsubmit="return confirm('¿Estás seguro de eliminar esta sucursal? \n¡OJO! Esto borrará todas las clases y asignaciones asociadas a ella.');">
                                    <input type="hidden" name="action" value="delete_sucursal">
                                    <input type="hidden" name="sucursal_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit"
                                        class="text-rose-500 hover:text-rose-700 cursor-pointer transition-colors"
                                        title="Eliminar">
                                        <i class="fas fa-trash"></i>
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
<div id="editSucursalModal"
    class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 opacity-100">
        <form action="../../back/admin_actions_sucursales.php" method="POST">
            <div class="px-6 py-4 border-b border-zinc-100 flex justify-between items-center bg-zinc-50">
                <h5 class="font-bold text-zinc-900">Editar Sucursal</h5>
                <button type="button" onclick="closeEditModal()"
                    class="text-zinc-400 hover:text-rose-500 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6">
                <input type="hidden" name="action" value="update_sucursal">
                <input type="hidden" id="edit_sucursal_id" name="sucursal_id">

                <div>
                    <label for="edit_nombre_sucursal"
                        class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Nombre de la Sucursal</label>
                    <input type="text"
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none"
                        id="edit_nombre_sucursal" name="nombre_sucursal" required>
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

<script>
    function openEditModal(id, nombre) {
        document.getElementById('edit_sucursal_id').value = id;
        document.getElementById('edit_nombre_sucursal').value = nombre;
        document.getElementById('editSucursalModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editSucursalModal').classList.add('hidden');
    }
</script>