<?php
// Strict Security Check for UI Include
if ($_SESSION['perfil_id'] != 1) {
    echo "<div class='p-8 text-center text-rose-500 font-bold'>Acceso Denegado.</div>";
    exit();
}

$backupDir = __DIR__ . '/../../backups/';
$files = glob($backupDir . '*.sql');

// Sort by time desc
usort($files, function ($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>

<div class="mb-8 flex justify-between items-end">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Copias de Seguridad</h3>
        <p class="text-zinc-500 font-light text-sm">Gestiona respaldos y restauración de la base de datos.</p>
    </div>
    <form action="../../back/backup_actions.php" method="POST" onsubmit="handleFormSubmit(event)">
        <input type="hidden" name="action" value="trigger_backup">
        <button type="submit"
            class="bg-indigo-600 text-white px-4 py-2 rounded text-xs font-bold uppercase tracking-wider hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-200">
            <i class="fas fa-database mr-2"></i> Generar Respaldo Ahora
        </button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
    <div class="p-6 border-b border-zinc-100 bg-zinc-50">
        <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-400">Historial de Respaldos</h6>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-white border-b border-zinc-100 text-xs uppercase tracking-wider text-zinc-500">
                <tr>
                    <th class="px-6 py-3 font-medium">Archivo</th>
                    <th class="px-6 py-3 font-medium">Fecha</th>
                    <th class="px-6 py-3 font-medium">Tamaño</th>
                    <th class="px-6 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-50 text-sm text-zinc-700">
                <?php if (count($files) > 0): ?>
                    <?php foreach ($files as $file):
                        $filename = basename($file);
                        $size = round(filesize($file) / 1024, 2) . ' KB';
                        $date = date('d M Y, H:i:s', filemtime($file));
                        ?>
                        <tr class="hover:bg-zinc-50 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs">
                                <?php echo htmlspecialchars($filename); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo $date; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo $size; ?>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-3">

                                <form action="../../back/backup_actions.php" method="POST" onsubmit="handleFormSubmit(event)"
                                    data-confirm="⚠️ ¿PELIGRO: Estás seguro de RESTAURAR este respaldo? Se perderán los datos actuales creados después de esta fecha.">
                                    <input type="hidden" name="action" value="restore_backup">
                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename); ?>">
                                    <button type="submit"
                                        class="text-amber-600 hover:text-amber-800 font-bold text-xs uppercase tracking-wide flex items-center">
                                        <i class="fas fa-undo-alt mr-1"></i> Restaurar
                                    </button>
                                </form>

                                <form action="../../back/backup_actions.php" method="POST" onsubmit="handleFormSubmit(event)"
                                    data-confirm="¿Eliminar este archivo de respaldo permanentemente?">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename); ?>">
                                    <button type="submit" class="text-rose-400 hover:text-rose-600 transition-colors"
                                        title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-zinc-400 italic text-xs">
                            No hay respaldos disponibles.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 p-4 bg-amber-50 border border-amber-100 rounded-lg text-amber-800 text-xs">
    <p class="font-bold mb-1"><i class="fas fa-info-circle mr-1"></i> Información Importante</p>
    <ul class="list-disc list-inside opacity-80 space-y-1">
        <li>Los respaldos se generan automáticamente cada vez que se ejecuta el script de mantenimiento.</li>
        <li>Los archivos con antigüedad mayor a 15 días son eliminados automáticamente.</li>
        <li>La restauración reemplaza completamente la base de datos actual. Úsalo con precaución.</li>
    </ul>
</div>