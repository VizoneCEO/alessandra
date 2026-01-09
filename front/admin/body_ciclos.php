<?php
require '../../back/db_connect.php';
$sql_ciclos = "SELECT * FROM Ciclos_Escolares ORDER BY fecha_inicio DESC";
$result_ciclos = $conn->query($sql_ciclos);
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Ciclos Escolares</h3>
    <p class="text-zinc-500 font-light text-sm">Gestiona la apertura y cierre de periodos académicos.</p>
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

<!-- Crear Ciclo -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8">
    <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-4"><i class="fas fa-calendar-plus mr-1"></i>
        Crear Nuevo Ciclo</h6>
    <form action="../../back/admin_actions_ciclos.php" method="POST">
        <input type="hidden" name="action" value="create_ciclo">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
            <div class="md:col-span-4">
                <label for="nombre_ciclo" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Nombre del
                    Ciclo</label>
                <input type="text"
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors"
                    id="nombre_ciclo" name="nombre_ciclo" placeholder="Ej: Apertura 2025" required>
            </div>
            <div class="md:col-span-3">
                <label for="fecha_inicio" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Fecha de
                    Inicio</label>
                <input type="date"
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors text-zinc-600"
                    id="fecha_inicio" name="fecha_inicio" required>
            </div>
            <div class="md:col-span-3">
                <label for="fecha_fin" class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Fecha de
                    Fin</label>
                <input type="date"
                    class="w-full border-b border-zinc-200 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent transition-colors text-zinc-600"
                    id="fecha_fin" name="fecha_fin" required>
            </div>
            <div class="md:col-span-2">
                <button type="submit"
                    class="w-full py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors rounded">
                    Crear Ciclo
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Tabla de Ciclos -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
    <div class="p-6 border-b border-zinc-100">
        <div class="text-zinc-600 font-medium flex items-center">
            <i class="fas fa-calendar-alt mr-2 text-zinc-400"></i> Historial de Ciclos
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 font-medium">Nombre del Ciclo</th>
                    <th class="px-6 py-3 font-medium">Fecha de Inicio</th>
                    <th class="px-6 py-3 font-medium">Fecha de Fin</th>
                    <th class="px-6 py-3 font-medium">Estado</th>
                    <th class="px-6 py-3 font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 text-sm text-slate-700">
                <?php while ($row = $result_ciclos->fetch_assoc()): ?>
                    <tr class="hover:bg-zinc-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-zinc-900"><?php echo htmlspecialchars($row['nombre_ciclo']); ?>
                        </td>
                        <td class="px-6 py-4 font-mono text-xs"><?php echo $row['fecha_inicio']; ?></td>
                        <td class="px-6 py-4 font-mono text-xs"><?php echo $row['fecha_fin']; ?></td>
                        <td class="px-6 py-4">
                            <?php
                            $estado = htmlspecialchars($row['estado']);
                            $badge_color = 'bg-zinc-100 text-zinc-500';
                            $dot_color = 'bg-zinc-400';

                            if ($estado == 'activo') {
                                $badge_color = 'bg-emerald-50 text-emerald-700';
                                $dot_color = 'bg-emerald-500';
                            } elseif ($estado == 'cerrado') {
                                $badge_color = 'bg-rose-50 text-rose-700';
                                $dot_color = 'bg-rose-500';
                            }
                            ?>
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo $dot_color; ?>"></span>
                                <?php echo ucfirst($estado); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <form action="../../back/admin_actions_ciclos.php" method="POST"
                                class="flex items-center gap-3">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="ciclo_id" value="<?php echo $row['id']; ?>">
                                <select name="nuevo_estado"
                                    class="text-xs border border-zinc-200 rounded px-2 py-1 focus:border-zinc-900 outline-none bg-white"
                                    onchange="this.form.submit()">
                                    <option value="">Estado...</option>
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