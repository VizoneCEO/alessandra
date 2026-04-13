<?php
require_once '../../back/db_connect.php';

// Obtener Sucursales para el filtro
$sucursales = $conn->query("SELECT id, nombre_sucursal FROM Sucursales ORDER BY nombre_sucursal ASC")->fetch_all(MYSQLI_ASSOC);
// Obtener Ciclos Escolares para Reprobados (Opcional, igual que el panel real)
$ciclos = $conn->query("SELECT id, nombre_ciclo, estado FROM Ciclos_Escolares ORDER BY fecha_inicio DESC")->fetch_all(MYSQLI_ASSOC);

$ciclo_activo_id = '';
foreach ($ciclos as $c) {
    if ($c['estado'] === 'activo') {
        $ciclo_activo_id = $c['id'];
        break;
    }
}
?>

<div class="mb-10">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Central de Reportes (CSV)</h3>
    <p class="text-zinc-500 font-light text-sm">Descarga información estratégica unificada adaptada para su lectura profunda en Excel.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">

    <!-- Tarjeta 1: Padrón General -->
    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 border border-emerald-100/50 flex flex-col overflow-hidden">
        <div class="p-6 border-b border-zinc-50 bg-emerald-50/30 flex-1">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-emerald-100 text-emerald-600 rounded-lg w-12 h-12 flex items-center justify-center text-xl shadow-sm">
                    <i class="fas fa-users"></i>
                </div>
                <span class="text-[10px] uppercase font-bold tracking-widest text-emerald-500 border border-emerald-200 px-2 py-1 rounded-full bg-emerald-50">Base de Datos</span>
            </div>
            <h5 class="font-serif text-xl font-bold text-zinc-900 mb-2">Padrón de Alumnos</h5>
            <p class="text-xs text-zinc-500 font-light leading-relaxed mb-6">Unifica información biográfica, contacto (teléfono/dirección) e interrelaciones de emergencia en todo el alumnado registrado.</p>

            <form action="../../back/admin_actions_export.php" method="POST" class="space-y-4">
                <input type="hidden" name="report_type" value="padron_alumnos">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider font-bold text-zinc-400 mb-1">Filtro de Modalidad</label>
                    <select name="modalidad" class="w-full text-xs font-medium border border-zinc-200 rounded py-2 px-3 focus:outline-none focus:border-zinc-500 transition-colors bg-white">
                        <option value="all" selected>Todas las modalidades</option>
                        <option value="presencial">Presencial exclusiva</option>
                        <option value="online">Online / Remota</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider font-bold text-zinc-400 mb-1">Sede / Sucursal</label>
                    <select name="sucursal_id" class="w-full text-xs font-medium border border-zinc-200 rounded py-2 px-3 focus:outline-none focus:border-zinc-500 transition-colors bg-white">
                        <option value="all" selected>Cualquier sede</option>
                        <?php foreach($sucursales as $suc): ?>
                            <option value="<?php echo $suc['id']; ?>"><?php echo htmlspecialchars($suc['nombre_sucursal']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Spacing hack for flex down -->
                <div class="pt-2"></div>
        </div>
        <div class="p-4 border-t border-zinc-100 bg-white mt-auto">
            <button type="submit" class="w-full py-2.5 bg-emerald-600 text-white font-bold text-xs uppercase tracking-widest rounded shadow-sm hover:bg-emerald-700 transition flex items-center justify-center">
                <i class="fas fa-file-excel mr-2"></i> Generar Archivo Excel
            </button>
        </div>
        </form>
    </div>

    <!-- Tarjeta 2: Reporte Financiero -->
    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 border border-blue-100/50 flex flex-col overflow-hidden">
        <div class="p-6 border-b border-zinc-50 bg-blue-50/30 flex-1">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-blue-100 text-blue-600 rounded-lg w-12 h-12 flex items-center justify-center text-xl shadow-sm">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                <span class="text-[10px] uppercase font-bold tracking-widest text-blue-500 border border-blue-200 px-2 py-1 rounded-full bg-blue-50">Cobranza</span>
            </div>
            <h5 class="font-serif text-xl font-bold text-zinc-900 mb-2">Reporte Financiero</h5>
            <p class="text-xs text-zinc-500 font-light leading-relaxed mb-6">Saldos agregados por Alumno, sumatorias de tickets pagados, deudas acumuladas e historial de su último pago concretado.</p>

            <form action="../../back/admin_actions_export.php" method="POST" class="space-y-4">
                <input type="hidden" name="report_type" value="financiero">
                <div class="flex items-start bg-blue-50/50 p-3 rounded border border-blue-100 mt-8">
                    <i class="fas fa-info-circle text-blue-400 mt-0.5 mr-2"></i>
                    <p class="text-[10px] text-blue-800 leading-tight">Este reporte emite toda la matrícula histórica omitiendo perfiles no relacionados a cobros.</p>
                </div>
        </div>
        <div class="p-4 border-t border-zinc-100 bg-white mt-auto">
            <button type="submit" class="w-full py-2.5 bg-emerald-600 text-white font-bold text-xs uppercase tracking-widest rounded shadow-sm hover:bg-emerald-700 transition flex items-center justify-center">
                <i class="fas fa-file-excel mr-2"></i> Generar Archivo Excel
            </button>
        </div>
        </form>
    </div>

    <!-- Tarjeta 3: Reporte de Reprobados -->
    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 border border-rose-100/50 flex flex-col overflow-hidden">
        <div class="p-6 border-b border-zinc-50 bg-rose-50/30 flex-1">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-rose-100 text-rose-600 rounded-lg w-12 h-12 flex items-center justify-center text-xl shadow-sm">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <span class="text-[10px] uppercase font-bold tracking-widest text-rose-500 border border-rose-200 px-2 py-1 rounded-full bg-rose-50">Academia</span>
            </div>
            <h5 class="font-serif text-xl font-bold text-zinc-900 mb-2">Auditoría de Reprobados</h5>
            <p class="text-xs text-zinc-500 font-light leading-relaxed mb-6">Genera una matriz comparativa con alumnos por debajo del umbral de aprobación definido.</p>

            <form action="../../back/admin_actions_export.php" method="POST" class="space-y-4">
                <input type="hidden" name="report_type" value="reprobados">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider font-bold text-zinc-400 mb-1">Mínima Aprobatoria</label>
                    <input type="number" step="0.1" name="min_calificacion" value="7.0" class="w-full text-xs font-bold text-rose-600 border border-rose-200 rounded py-2 px-3 focus:outline-none focus:border-rose-400 transition-colors bg-white">
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider font-bold text-zinc-400 mb-1">Ciclo Escolar</label>
                    <select name="ciclo_id" class="w-full text-xs font-medium border border-zinc-200 rounded py-2 px-3 focus:outline-none focus:border-zinc-500 transition-colors bg-white" required>
                        <?php foreach($ciclos as $suc): ?>
                            <option value="<?php echo $suc['id']; ?>" <?php echo $suc['id'] == $ciclo_activo_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($suc['nombre_ciclo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
        </div>
        <div class="p-4 border-t border-zinc-100 bg-white mt-auto">
            <button type="submit" class="w-full py-2.5 bg-emerald-600 text-white font-bold text-xs uppercase tracking-widest rounded shadow-sm hover:bg-emerald-700 transition flex items-center justify-center">
                <i class="fas fa-file-excel mr-2"></i> Generar Archivo Excel
            </button>
        </div>
        </form>
    </div>

</div>
