<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use __DIR__ to safely locate the back directory regardless of where this script is included from
require_once __DIR__ . '/../../back/db_connect.php';

// 1. Fetch Students for Dropdown (Only Alumnos who don't have a config yet)
// Assuming perfil_id = 3 is Alumno based on previous context
$all_students = [];
$sql_dropdown = "SELECT u.id, u.nombre_completo as nombre 
                 FROM Usuarios u 
                 WHERE u.perfil_id = 3 
                 AND u.id NOT IN (SELECT alumno_id FROM finanzas_asignaciones)
                 ORDER BY u.nombre_completo ASC";
$res_students = $conn->query($sql_dropdown);
if ($res_students) {
    while ($row = $res_students->fetch_assoc()) {
        $all_students[] = $row;
    }
}

// 2. Fetch Active Assignments (Configs)
$alumnos_config = [];
$sql_config = "SELECT f.*, u.nombre_completo as nombre, u.forma
               FROM finanzas_asignaciones f 
               JOIN Usuarios u ON f.alumno_id = u.id
               ORDER BY u.nombre_completo ASC";
$res_config = $conn->query($sql_config);
if ($res_config) {
    while ($row = $res_config->fetch_assoc()) {
        $alumnos_config[] = $row;
    }
}

// 3. Process Overdue Charges (Automatic Penalty)
$todayDate = date('Y-m-d');

// Step A: Mark ALL overdue pending charges as 'Vencido' (Visual Status)
$conn->query("UPDATE finanzas_cargos 
              SET estado = 'Vencido', updated_at = NOW() 
              WHERE fecha_vencimiento < '$todayDate' 
              AND estado = 'Pago Pendiente'");

// Step B: Apply Economics Penalty (Loss of Scholarship) only where applicable
// Exclude charges that have been manually adjusted (checking if notes are empty)
$conn->query("UPDATE finanzas_cargos 
              SET recargos = beca_aplicada, updated_at = NOW() 
              WHERE estado = 'Vencido' 
              AND beca_aplicada > 0 
              AND recargos = 0
              AND (notas_ajuste IS NULL OR notas_ajuste = '')");
// Note: We could log this, but for bulk efficiency we skip individual logs for now.

// 4. Fetch Charges (Cobranza)
$cargos_actuales = [];
$cargos_historicos = [];
$sql_cargos = "SELECT c.*, u.nombre_completo as nombre, u.forma, acc.banco as banco_receptor, acc.titular as titular_receptor
               FROM finanzas_cargos c
               JOIN Usuarios u ON c.alumno_id = u.id
               LEFT JOIN Finanzas_Cuentas acc ON c.cuenta_receptora_id = acc.id
               ORDER BY c.fecha_vencimiento ASC";
$res_cargos = $conn->query($sql_cargos);
if ($res_cargos) {
    $today = date('Y-m-d');
    while ($row = $res_cargos->fetch_assoc()) {
        // Validation: If overdue and pending, add penalty equal to scholarship
        $is_overdue = ($today > $row['fecha_vencimiento']) && ($row['estado'] !== 'Pagado') && ($row['estado'] !== 'Al corriente');
        $row['beca_status'] = 'active';

        // Check if beca_aplicada exists (it might be 0 for old records or no scholarship)
        $beca_val = isset($row['beca_aplicada']) ? floatval($row['beca_aplicada']) : 0;

        // Skip penalty if manually adjusted (notes present)
        $has_adjustment = !empty($row['notas_ajuste']);

        if ($is_overdue && $beca_val > 0 && !$has_adjustment) {
            $row['beca_status'] = 'lost';
            // Use DB total which already includes penalty via the update query above
            $row['total'] = floatval($row['total']);
        } else {
            $row['total'] = floatval($row['total']);
        }

        // Fix Partial Payments Display (Undefined keys)
        $row['pagado'] = floatval($row['monto_pagado']);
        $row['saldo'] = $row['total'] - $row['pagado'];
        if ($row['saldo'] < 0)
            $row['saldo'] = 0;

        if ($row['estado'] === 'Pagado' || $row['estado'] === 'Cancelado') {
            $cargos_historicos[] = $row;
        } else {
            $cargos_actuales[] = $row;
        }
    }

    // Sort History by updated_at DESC (Newest updates first)
    usort($cargos_historicos, function ($a, $b) {
        $dateA = $a['updated_at'] ?? $a['fecha_pago'] ?? $a['created_at'];
        $dateB = $b['updated_at'] ?? $b['fecha_pago'] ?? $b['created_at'];
        return strtotime($dateB) - strtotime($dateA);
    });
}

// 4. Fetch Cycles (Active/All)
$ciclos = [];
$sql_ciclos = "SELECT id, nombre_ciclo FROM Ciclos_Escolares ORDER BY id DESC"; // Assuming latest first
$res_ciclos = $conn->query($sql_ciclos);
if ($res_ciclos) {
    while ($row = $res_ciclos->fetch_assoc()) {
        $ciclos[] = $row;
    }
}
?>

<div class="mb-8 flex flex-col md:flex-row justify-between items-end">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Gestión Financiera</h3>
        <p class="text-zinc-500 font-light text-sm">Control de cuotas personalizadas y auditoría de excepciones.</p>
    </div>

    <!-- TABS DE NAVEGACIÓN -->
    <div class="flex space-x-1 bg-zinc-100 p-1 rounded-lg mt-4 md:mt-0">
        <button onclick="switchTab('config')" id="tab-config"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm">
            Configuración Cuotas
        </button>
        <button onclick="switchTab('cobranza')" id="tab-cobranza"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900">
            Cobranza y Ajustes
        </button>
        <button onclick="switchTab('historico')" id="tab-historico"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900">
            Histórico
        </button>
        <button onclick="switchTab('eventos')" id="tab-eventos"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900">
            Configuración de Eventos
        </button>
    </div>
</div>

<!-- ==========================================
     SECCIÓN A: CONFIGURACIÓN DE CUOTAS
     ========================================== -->
<div id="view-config" class="animate-fade-in-up">
    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <div
            class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <!-- Left: Title -->
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500 whitespace-nowrap">
                <i class="fas fa-sliders-h mr-2"></i> Set Up de Alumnos
            </h6>

            <!-- Right: Tools -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Search -->
                <div class="relative group">
                    <i
                        class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-zinc-400 group-focus-within:text-zinc-600"></i>
                    <input type="text" placeholder="Buscar..." id="searchConfig"
                        class="pl-9 pr-3 py-1.5 text-xs border border-zinc-200 rounded-md focus:border-zinc-900 outline-none bg-white w-32 focus:w-48 transition-all">
                </div>

                <!-- Filter -->
                <select id="filterBeca"
                    class="py-1.5 pl-3 pr-8 text-xs border border-zinc-200 rounded-md focus:border-zinc-900 outline-none bg-white cursor-pointer">
                    <option value="all">Estado: Todos</option>
                    <option value="con_beca">Con Beca</option>
                    <option value="sin_beca">Sin Beca</option>
                </select>

                <!-- Filter Sucursal -->
                <select id="filterSucursalConfig"
                    class="py-1.5 pl-3 pr-8 text-xs border border-zinc-200 rounded-md focus:border-zinc-900 outline-none bg-white cursor-pointer">
                    <option value="all">Forma: Todas</option>
                    <option value="online">Online</option>
                    <option value="presencial">Presencial</option>
                </select>

                <div class="h-6 w-px bg-zinc-200 mx-1"></div>

                <!-- Buttons -->
                <button onclick="openNewAssignmentModal()"
                    class="px-3 py-1.5 bg-white border border-zinc-300 text-zinc-700 text-[10px] font-bold uppercase tracking-widest rounded hover:bg-zinc-900 hover:text-white hover:border-zinc-900 transition-all shadow-sm">
                    <i class="fas fa-plus mr-1"></i> Asignar
                </button>

                <button onclick="saveAllChanges()"
                    class="bg-zinc-900 text-white px-4 py-1.5 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors shadow-lg shadow-zinc-200/50">
                    Guardar
                </button>
            </div>
        </div>
        <div class="mt-4 px-6 pb-2 text-xs text-zinc-400 font-mono text-right">
            <span id="configRecordCount" class="font-bold text-zinc-600">0</span> registros encontrados
        </div>


        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Alumno</th>
                        <th class="px-6 py-4 font-medium">Colegiatura Base</th>
                        <th class="px-6 py-4 font-medium">$ Beca</th>
                        <th class="px-6 py-4 font-medium bg-zinc-800">Total Mensual (Calc)</th>
                        <th class="px-6 py-4 font-medium text-right">Inscripción con Beca</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 text-sm">
                    <?php foreach ($alumnos_config as $alumno):
                        // Calculo 
                        $descuento = $alumno['beca_monto'];
                        $final = $alumno['colegiatura_base'] - $descuento;
                        $sucursalType = strtolower($alumno['forma'] ?? 'presencial');
                        ?>
                        <tr class="hover:bg-zinc-50 transition-colors group row-editable"
                            data-id="<?php echo $alumno['alumno_id'] ?: $alumno['id']; ?>"
                            data-sucursal="<?php echo $sucursalType; ?>">
                            <td class="px-6 py-4 font-bold text-zinc-800 border-r border-zinc-50">
                                <?php echo htmlspecialchars($alumno['nombre']); ?>
                            </td>

                            <!-- Inputs Minimalistas -->
                            <td class="px-6 py-4">
                                <div class="flex items-center text-zinc-400 focus-within:text-zinc-900 transition-colors">
                                    <span class="mr-1">$</span>
                                    <input type="number" value="<?php echo $alumno['colegiatura_base']; ?>"
                                        class="input-colegiatura w-32 border-b border-zinc-200 py-1 focus:border-zinc-900 outline-none bg-transparent font-medium text-zinc-900">
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center text-zinc-400 focus-within:text-blue-600 transition-colors">
                                    <span class="mr-1 text-blue-400 font-bold">$</span>
                                    <input type="number" value="<?php echo $alumno['beca_monto']; ?>"
                                        class="input-beca w-24 border-b border-zinc-200 py-1 focus:border-blue-600 outline-none bg-transparent font-bold text-blue-600 text-left">
                                </div>
                            </td>

                            <td class="px-6 py-4 bg-zinc-50 font-serif font-bold text-lg text-emerald-700">
                                $<?php echo number_format($final, 2); ?>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end text-zinc-400 focus-within:text-zinc-900 transition-colors">
                                    <span class="mr-1">$</span>
                                    <input type="number" value="<?php echo $alumno['inscripcion_base']; ?>"
                                        class="input-inscripcion w-32 border-b border-zinc-200 py-1 focus:border-zinc-900 outline-none bg-transparent font-medium text-zinc-900 text-right">
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-zinc-50 border-t border-zinc-100 text-xs text-center text-zinc-400 italic">
            * Los cambios afectarán la generación del próximo ciclo de facturación.
        </div>
    </div>
</div>

<!-- ==========================================
     SECCIÓN B: GESTIÓN DE COBRANZA
     ========================================== -->
<div id="view-cobranza" class="hidden animate-fade-in-up">
    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex justify-between items-center">
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500"><i
                    class="fas fa-file-invoice-dollar mr-2"></i> Cargos Actuales y Excepciones</h6>

            <div class="flex gap-2">
                <button onclick="openTicketModal('General')"
                    class="bg-white border border-zinc-200 text-zinc-600 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-50 hover:border-zinc-900 hover:text-zinc-900 transition-all shadow-sm">
                    <i class="fas fa-ticket-alt mr-2 text-violet-500"></i> Boletos General
                </button>
                <button onclick="openTicketModal('Staff')"
                    class="bg-white border border-zinc-200 text-zinc-600 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-50 hover:border-zinc-900 hover:text-zinc-900 transition-all shadow-sm">
                    <i class="fas fa-id-badge mr-2 text-indigo-500"></i> Boletos Staff
                </button>
                <button onclick="openTicketModal('Modelos')"
                    class="bg-white border border-zinc-200 text-zinc-600 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-50 hover:border-zinc-900 hover:text-zinc-900 transition-all shadow-sm">
                    <i class="fas fa-female mr-2 text-rose-500"></i> Boletos Modelos
                </button>
                <button onclick="openTicketModal('Invitados')"
                    class="bg-white border border-zinc-200 text-zinc-600 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-50 hover:border-zinc-900 hover:text-zinc-900 transition-all shadow-sm">
                    <i class="fas fa-user-friends mr-2 text-emerald-500"></i> Boletos Invitados
                </button>
                <button onclick="openRegistrationModal()"
                    class="bg-white border border-zinc-200 text-zinc-600 px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-50 hover:border-zinc-900 hover:text-zinc-900 transition-all shadow-sm">
                    <i class="fas fa-graduation-cap mr-2 text-blue-500"></i> Inscripciones
                </button>
                <button onclick="triggerMonthlyCharges()"
                    class="bg-zinc-900 text-white px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-700 transition-colors shadow-lg shadow-zinc-200">
                    <i class="fas fa-bolt mr-2 text-amber-400"></i> Generar Cargos
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div
            class="px-6 py-4 bg-white border-b border-zinc-100 flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="flex gap-4 w-full md:w-auto flex-1">
                <div class="relative w-full md:w-1/3">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                    <input type="text" id="filterStudent" placeholder="Buscar Alumno..."
                        class="w-full pl-8 pr-3 py-2 bg-zinc-50 border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors shadow-sm"
                        onkeyup="filterCharges()">
                </div>
                <div class="relative w-full md:w-1/3">
                    <i class="fas fa-tag absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                    <input type="text" id="filterConcept" placeholder="Filtrar Concepto..."
                        class="w-full pl-8 pr-3 py-2 bg-zinc-50 border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors shadow-sm"
                        onkeyup="filterCharges()">
                </div>
                <div class="relative w-full md:w-1/4">
                    <select id="filterStatus" onchange="filterCharges()"
                        class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors appearance-none cursor-pointer shadow-sm">
                        <option value="">Todos los Estados</option>
                        <option value="Verificar Pago">Verificar Pago</option>
                        <option value="Pago Pendiente">Pago Pendiente</option>
                        <option value="Vencido">Vencido</option>
                        <option value="Pagado">Pagado</option>
                        <option value="Al corriente">Al corriente</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                    <i
                        class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs pointer-events-none"></i>
                </div>
                <div class="relative w-full md:w-1/4">
                    <select id="filterSucursalCharges" onchange="filterCharges()"
                        class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors appearance-none cursor-pointer shadow-sm">
                        <option value="">Todas las Formas</option>
                        <option value="online">Online</option>
                        <option value="presencial">Presencial</option>
                    </select>
                    <i
                        class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs pointer-events-none"></i>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button id="btnBulkDelete" onclick="deleteSelectedCharges()"
                    class="hidden bg-rose-100 text-rose-600 px-3 py-1.5 rounded text-xs font-bold uppercase tracking-wider hover:bg-rose-200 transition-all shadow-sm">
                    <i class="fas fa-trash-alt mr-2"></i> Eliminar Selección (<span id="selectedCount">0</span>)
                </button>
                <div class="text-xs text-zinc-400 hidden md:block font-mono">
                    <span id="recordCount" class="font-bold text-zinc-600">0</span> registros
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium w-10">
                            <input type="checkbox" onclick="toggleAllCharges(this)"
                                class="rounded border-zinc-300 text-zinc-900 focus:ring-0 cursor-pointer">
                        </th>
                        <th class="px-6 py-4 font-medium">Alumno</th>
                        <th class="px-6 py-4 font-medium">Concepto</th>
                        <th class="px-6 py-4 font-medium">Vencimiento</th>
                        <th class="px-6 py-4 font-medium text-center">Estado</th>
                        <th class="px-6 py-4 font-medium text-right">Deuda Total</th>
                        <th class="px-6 py-4 font-medium text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 text-sm">
                    <?php foreach ($cargos_actuales as $cargo):
                        $rowClass = "";
                        $debtClass = "text-zinc-900";

                        if ($cargo['estado'] === 'Vencido') {
                            $rowClass = "bg-rose-50/30";
                            $debtClass = "text-rose-600";
                        }

                        // Highlight logic for Pending Verification (Moved Before Render)
                        $badgeColor = 'bg-zinc-100 text-zinc-500';
                        if ($cargo['estado'] === 'Al corriente')
                            $badgeColor = 'bg-emerald-100 text-emerald-700';
                        if ($cargo['estado'] === 'Pago Pendiente')
                            $badgeColor = 'bg-amber-100 text-amber-700';
                        if ($cargo['estado'] === 'Vencido')
                            $badgeColor = 'bg-rose-100 text-rose-700';
                        if ($cargo['estado'] === 'Parcialmente Pagado')
                            $badgeColor = 'bg-blue-100 text-blue-700';

                        if (!empty($cargo['comprobante_url']) && $cargo['estado'] !== 'Pagado') {
                            $badgeColor = 'bg-purple-600 text-white animate-pulse shadow-lg ring-2 ring-purple-300';
                            $cargo['estado'] = 'Verificar Pago'; // Visual override & Data Attribute Fix
                        }

                        $sucursalType = strtolower($cargo['forma'] ?? 'presencial');
                        ?>
                        <tr class="hover:bg-zinc-50 transition-colors group charge-row <?php echo $rowClass; ?>"
                            data-student="<?php echo strtolower(htmlspecialchars($cargo['nombre'])); ?>"
                            data-concept="<?php echo strtolower(htmlspecialchars($cargo['concepto'])); ?>"
                            data-status="<?php echo $cargo['estado']; ?>" data-sucursal="<?php echo $sucursalType; ?>">
                            <td class="px-6 py-4">
                                <input type="checkbox"
                                    class="charge-check rounded border-zinc-300 text-zinc-900 focus:ring-0 cursor-pointer"
                                    value="<?php echo $cargo['id']; ?>" onchange="updateBulkUI()">
                            </td>
                            <td class="px-6 py-4 font-bold text-zinc-800"><?php echo htmlspecialchars($cargo['nombre']); ?>
                            </td>
                            <td class="px-6 py-4 text-zinc-600">
                                <?php echo htmlspecialchars($cargo['concepto']); ?>
                                <?php if ($cargo['recargos'] > 0): ?>
                                    <span class="block text-[10px] text-rose-500 mt-1 font-bold">+
                                        $<?php echo number_format($cargo['recargos']); ?> Recargos</span>
                                <?php endif; ?>
                                <?php if (isset($cargo['beca_status']) && $cargo['beca_status'] === 'lost'): ?>
                                    <span
                                        class="block text-[10px] text-amber-600 mt-1 font-bold bg-amber-50 px-1 rounded w-fit border border-amber-100">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Beca Retirada (Mora)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-zinc-400 font-mono text-xs"><?php echo $cargo['fecha_vencimiento']; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span
                                    class="px-3 py-1.5 rounded-full text-[9px] font-bold uppercase tracking-widest whitespace-nowrap flex items-center justify-center <?php echo $badgeColor; ?>">
                                    <?php echo $cargo['estado']; ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 text-right font-serif font-bold text-lg <?php echo $debtClass; ?>">
                                <?php if ($cargo['estado'] === 'Pagado'): ?>
                                    <span class="text-emerald-600">$<?php echo number_format($cargo['total'], 2); ?></span>
                                <?php else: ?>
                                    $<?php echo number_format($cargo['total'], 2); ?>
                                    <?php if ($cargo['pagado'] > 0): ?>
                                        <div class="text-[10px] text-zinc-400 mt-1 font-sans font-normal">
                                            Pagado: <span
                                                class="text-emerald-600 font-bold">$<?php echo number_format($cargo['pagado'], 2); ?></span>
                                        </div>
                                        <div class="text-[10px] text-rose-500 font-sans font-bold">
                                            Restan: $<?php echo number_format($cargo['saldo'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button
                                        onclick="openPaymentModal(<?php echo $cargo['id']; ?>, '<?php echo $cargo['total']; ?>', '<?php echo $cargo['pagado']; ?>', '<?php echo $cargo['saldo']; ?>', '<?php echo htmlspecialchars($cargo['concepto']); ?>')"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-900 text-white hover:bg-emerald-600 hover:scale-110 transition-all shadow-md"
                                        title="Registrar Pago">
                                        <i class="fas fa-dollar-sign text-xs"></i>
                                    </button>

                                    <!-- Added Receipt Button -->
                                    <?php
                                    $hasReceipt = !empty($cargo['comprobante_url']);
                                    $isPaid = $cargo['estado'] === 'Pagado';
                                    $isPartial = $cargo['estado'] === 'Parcialmente Pagado' || $cargo['pagado'] > 0;

                                    $receiptColor = ($hasReceipt || $isPaid || $isPartial) ? 'text-blue-500 border-blue-500' : 'text-zinc-400 border-zinc-200';
                                    $receiptHover = ($hasReceipt || $isPaid || $isPartial) ? 'hover:bg-blue-50' : 'hover:border-blue-500 hover:text-blue-500';

                                    $receiptUrl = $cargo['comprobante_url'] ?? '';
                                    $payMethod = $cargo['metodo_pago'] ?? '';
                                    $payRef = $cargo['referencia_pago'] ?? '';
                                    $payDate = $cargo['fecha_pago'] ?? '';

                                    // Visual adjustment for partial: show paid so far if not fully paid
                                    $payAmount = ($isPartial && !$isPaid) ? $cargo['pagado'] : $cargo['total'];
                                    ?>
                                    <button
                                        onclick="openReceiptModal(<?php echo $cargo['id']; ?>, '<?php echo $receiptUrl; ?>', '<?php echo $cargo['estado']; ?>', '<?php echo $payMethod; ?>', '<?php echo $payRef; ?>', '<?php echo $payDate; ?>', '<?php echo $payAmount; ?>')"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-white border <?php echo $receiptColor; ?> <?php echo $receiptHover; ?> hover:scale-110 transition-all shadow-sm"
                                        title="Ver Recibo / Comprobante">
                                        <i class="fas fa-receipt text-xs"></i>
                                    </button>

                                    <button
                                        onclick="openHistoryModal(<?php echo $cargo['id']; ?>, '<?php echo htmlspecialchars($cargo['nombre']); ?>')"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-zinc-200 text-zinc-400 hover:border-violet-500 hover:text-violet-500 hover:scale-110 transition-all shadow-sm"
                                        title="Historial de Movimientos">
                                        <i class="fas fa-clock text-xs"></i>
                                    </button>

                                    <button
                                        onclick="openAdjustmentModal(<?php echo $cargo['id']; ?>, '<?php echo htmlspecialchars($cargo['nombre']); ?>', <?php echo $cargo['monto_original']; ?>, <?php echo $cargo['recargos']; ?>, <?php echo $cargo['beca_aplicada']; ?>)"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-zinc-200 text-zinc-400 hover:border-amber-500 hover:text-amber-500 hover:scale-110 transition-all"
                                        title="Ajustar / Condonar">
                                        <i class="fas fa-shield-alt text-xs"></i>
                                    </button>

                                    <button onclick="deleteCharge(<?php echo $cargo['id']; ?>)"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-zinc-200 text-zinc-400 hover:border-rose-500 hover:text-rose-500 hover:scale-110 transition-all shadow-sm"
                                        title="Eliminar Cargo">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     SECCIÓN C: HISTÓRICO DE PAGOS Y CANCELACIONES
     ========================================== -->
<div id="view-historico" class="hidden animate-fade-in-up">
    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <!-- Header & Filters -->
        <div
            class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500"><i
                        class="fas fa-history mr-2"></i> Historial</h6>
            </div>

            <div class="flex gap-2 w-full md:w-auto">
                <div class="relative flex-1">
                    <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-zinc-400 text-[10px]"></i>
                    <input type="text" id="histSearchStudent" placeholder="Buscar Alumno..."
                        class="w-full pl-6 pr-3 py-1.5 text-xs border border-zinc-200 rounded focus:border-zinc-900 outline-none"
                        onkeyup="filterHistory()">
                </div>
                <div class="relative flex-1">
                    <select id="filterSucursalHist" onchange="filterHistory()"
                        class="w-full px-3 py-1.5 text-xs border border-zinc-200 rounded focus:border-zinc-900 outline-none bg-white cursor-pointer">
                        <option value="">Todas las Formas</option>
                        <option value="online">Online</option>
                        <option value="presencial">Presencial</option>
                    </select>
                </div>
                <div class="relative flex-1">
                    <i class="fas fa-tag absolute left-2 top-1/2 -translate-y-1/2 text-zinc-400 text-[10px]"></i>
                    <input type="text" id="histSearchConcept" placeholder="Buscar Concepto..."
                        class="w-full pl-6 pr-3 py-1.5 text-xs border border-zinc-200 rounded focus:border-zinc-900 outline-none"
                        onkeyup="filterHistory()">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto min-h-[400px]">
            <table class="w-full text-left">
                <thead class="bg-zinc-100 text-zinc-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Alumno</th>
                        <th class="px-6 py-4 font-medium">Concepto</th>
                        <th class="px-6 py-4 font-medium">Cuenta Rec.</th>
                        <th class="px-6 py-4 font-medium">Fecha</th>
                        <th class="px-6 py-4 font-medium text-center">Estado</th>
                        <th class="px-6 py-4 font-medium text-right">Monto</th>
                        <th class="px-6 py-4 font-medium text-center">Evidencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 text-sm" id="historyTableBody">
                    <?php if (empty($cargos_historicos)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-zinc-400 italic">No hay registros.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cargos_historicos as $hist):
                            $hClass = "bg-white";
                            $hBadge = "bg-zinc-100 text-zinc-500";
                            if ($hist['estado'] === 'Pagado')
                                $hClass = "bg-emerald-50/10";
                            if ($hist['estado'] === 'Cancelado')
                                $hClass = "bg-rose-50/10";
                            $hBadge = $hist['estado'] === 'Pagado' ? "bg-emerald-100 text-emerald-700" : ($hist['estado'] === 'Cancelado' ? "bg-rose-100 text-rose-700" : "bg-zinc-100 text-zinc-500");
                            // Historical records in `cargos_historicos` array come from same query or need separate fix?
                            // Logic: The array $cargos_historicos is built from $res_cargos loop in PHP lines ~92.
                            // So it inherits 'forma' from $row['forma'].
                            $sucursalType = strtolower($hist['forma'] ?? 'presencial');
                            ?>
                            <tr class="hover:bg-zinc-50 transition-colors history-row <?php echo $hClass; ?>"
                                data-student="<?php echo strtolower(htmlspecialchars($hist['nombre'])); ?>"
                                data-concept="<?php echo strtolower(htmlspecialchars($hist['concepto'])); ?>"
                                data-sucursal="<?php echo $sucursalType; ?>">
                                <td class="px-6 py-4 font-bold text-zinc-700"><?php echo htmlspecialchars($hist['nombre']); ?>
                                </td>
                                <td class="px-6 py-4 text-zinc-600">
                                    <?php echo htmlspecialchars($hist['concepto']); ?>
                                    <div class="text-[10px] text-zinc-400 mt-1">
                                        <?php if ($hist['metodo_pago'])
                                            echo "Método: " . $hist['metodo_pago']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <?php if (!empty($hist['banco_receptor'])): ?>
                                        <p class="font-bold text-zinc-700"><?php echo htmlspecialchars($hist['banco_receptor']); ?>
                                        </p>
                                        <p class="text-[10px] text-zinc-500 uppercase">
                                            <?php echo htmlspecialchars($hist['titular_receptor']); ?>
                                        </p>
                                    <?php else: ?>
                                        <span class="text-zinc-300 italic">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-zinc-500 font-mono text-xs">
                                    <?php echo $hist['fecha_pago'] ? date('d/m/Y H:i', strtotime($hist['fecha_pago'])) : ($hist['updated_at'] ? date('d/m/Y', strtotime($hist['updated_at'])) : '-'); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $hBadge; ?>"><?php echo $hist['estado']; ?></span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-zinc-700">
                                    $<?php echo number_format($hist['total'], 2); ?></td>
                                <td class="px-6 py-4 text-center flex justify-center gap-2">
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-emerald-100 text-emerald-600 hover:scale-110"
                                        title="Ver Recibo"
                                        onclick="openReceiptModal(<?php echo $hist['id']; ?>, '<?php echo $hist['comprobante_url'] ?? ''; ?>', '<?php echo $hist['estado']; ?>', '<?php echo $hist['metodo_pago'] ?? ''; ?>', '<?php echo $hist['referencia_pago'] ?? ''; ?>', '<?php echo $hist['fecha_pago'] ?? ''; ?>', <?php echo $hist['total']; ?>)">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>

                                    <button onclick="openHistoryModal(<?php echo $hist['id']; ?>)"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-violet-50 text-violet-500 border border-violet-100 hover:bg-violet-100 hover:scale-110 transition-all"
                                        title="Ver Historial">
                                        <i class="fas fa-clock text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div
            class="px-6 py-3 border-t border-zinc-100 bg-zinc-50 flex justify-between items-center text-xs text-zinc-500">
            <span id="historyInfo">Mostrando 0-0 de 0</span>
            <div class="flex gap-2">
                <button onclick="prevHistPage()"
                    class="px-3 py-1 bg-white border border-zinc-200 rounded hover:bg-zinc-100 disabled:opacity-50"
                    id="btnPrevHist">Anterior</button>
                <button onclick="nextHistPage()"
                    class="px-3 py-1 bg-white border border-zinc-200 rounded hover:bg-zinc-100 disabled:opacity-50"
                    id="btnNextHist">Siguiente</button>
            </div>
        </div>
    </div>
</div>


<!-- ==========================================
     SECCIÓN D: CONFIGURACIÓN DE EVENTOS
     ========================================== -->
<div id="view-eventos" class="hidden animate-fade-in-up">
    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex justify-between items-center">
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500"><i
                    class="fas fa-calendar-alt mr-2"></i> Gestión de Eventos</h6>
            <button onclick="openAddEventModal()"
                class="bg-zinc-900 text-white px-4 py-2 rounded text-[10px] font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors shadow-lg">
                <i class="fas fa-plus mr-2"></i> Nuevo Evento
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium w-16 text-center">ID</th>
                        <th class="px-6 py-4 font-medium">Nombre del Evento</th>
                        <th class="px-6 py-4 font-medium text-center">Fecha</th>
                        <th class="px-6 py-4 font-medium text-center">Estado</th>
                        <th class="px-6 py-4 font-medium text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="eventsListTable" class="divide-y divide-zinc-100 text-sm">
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-zinc-400">Cargando eventos...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL DE AJUSTE MANUAL (Simulado) -->
<div id="adjustmentModal"
    class="fixed inset-0 z-50 hidden bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-zinc-100 flex justify-between items-center bg-zinc-50">
            <h5 class="font-bold text-zinc-900 flex items-center">
                <i class="fas fa-shield-alt text-amber-500 mr-2"></i> Ajuste de Crédito: <span id="modalStudentName"
                    class="ml-1 font-normal">Alumno</span>
            </h5>
            <button onclick="closeAdjustmentModal()" class="text-zinc-400 hover:text-rose-500 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-8">
            <div class="flex justify-between mb-6 p-4 bg-zinc-50 rounded-lg border border-zinc-100">
                <div class="text-center">
                    <p class="text-[10px] uppercase tracking-widest text-zinc-400 mb-1">Cargo Original</p>
                    <p class="font-serif text-lg text-zinc-700" id="modalOriginalAmount">$0.00</p>
                </div>
                <div class="text-center border-l border-zinc-200 pl-4">
                    <p class="text-[10px] uppercase tracking-widest text-rose-400 mb-1">Recargo Auto</p>
                    <p class="font-serif text-lg text-rose-600" id="modalSurcharge">$0.00</p>
                </div>
                <div class="text-center border-l border-zinc-200 pl-4">
                    <p class="text-[10px] uppercase tracking-widest text-zinc-900 mb-1 font-bold">Total Actual</p>
                    <p class="font-serif text-xl text-zinc-900 font-bold" id="modalTotalAmount">$0.00</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-zinc-500 mb-1">Recargos /
                        Penalización</label>
                    <input type="number" step="0.01"
                        class="w-full border-b-2 border-rose-200 py-2 text-xl font-serif font-bold text-rose-600 outline-none bg-transparent focus:border-rose-500"
                        value="0.00" id="modalRecargosInput" oninput="updateModalTotal('recargos')">
                </div>

                <input type="hidden" id="modalChargeId">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-zinc-500 mb-1">Monto Final a
                        Cobrar</label>
                    <input type="number" step="0.01"
                        class="w-full border-b-2 border-zinc-900 py-2 text-xl font-serif font-bold text-zinc-900 outline-none bg-transparent"
                        value="0.00" id="modalFinalInput" oninput="updateModalTotal('total')">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-zinc-500 mb-1">Motivo /
                        Autorización</label>
                    <input type="text" id="modalMotivo"
                        class="w-full border-b border-zinc-300 py-2 text-sm focus:border-zinc-900 outline-none bg-transparent placeholder-zinc-300"
                        placeholder="Ej. Error sistema...">
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-zinc-50 flex justify-end gap-3 border-t border-zinc-100">
            <button onclick="closeAdjustmentModal()"
                class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
            <button onclick="submitAdjustment()"
                class="px-6 py-2 bg-amber-400 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-amber-500 transition-colors shadow-lg">Aplicar
                Ajuste Manual</button>
        </div>
    </div>
</div>

<!-- MODAL NUEVA ASIGNACION -->
<div id="newAssignmentModal"
    class="hidden fixed inset-0 z-[60] bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in-up">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50">
            <h3 class="font-serif italic text-xl text-zinc-900">Nueva Asignación</h3>
            <p class="text-xs text-zinc-400 mt-1 font-light">Configura un nuevo esquema de pagos para un alumno.</p>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-5">
            <!-- Student Select -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Alumno</label>
                <select id="modalStudentId"
                    class="w-full border border-zinc-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-zinc-900 bg-white">
                    <option value="">Seleccionar Alumno...</option>
                    <?php foreach ($all_students as $std): ?>
                        <option value="<?php echo $std['id']; ?>"><?php echo htmlspecialchars($std['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Tuition -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Colegiatura
                        Base</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400">$</span>
                        <input type="number"
                            class="w-full pl-7 pr-3 py-2 border border-zinc-200 rounded-lg text-sm focus:outline-none focus:border-zinc-900 font-medium"
                            placeholder="0.00">
                    </div>
                </div>
                <!-- Registration -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Inscripción
                        Base</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400">$</span>
                        <input type="number"
                            class="w-full pl-7 pr-3 py-2 border border-zinc-200 rounded-lg text-sm focus:outline-none focus:border-zinc-900 font-medium"
                            placeholder="0.00">
                    </div>
                </div>
            </div>

            <!-- Scholarship -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-blue-600 mb-1">Monto Beca
                    Aplicable</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 font-bold">$</span>
                    <input type="number"
                        class="w-full pl-7 pr-3 py-2 border border-blue-100 bg-blue-50/50 rounded-lg text-sm focus:outline-none focus:border-blue-500 text-blue-700 font-bold"
                        placeholder="0.00">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Notas /
                    Observaciones</label>
                <textarea
                    class="w-full px-3 py-2 border border-zinc-200 rounded-lg text-sm focus:outline-none focus:border-zinc-900 min-h-[80px]"
                    placeholder="Detalles adicionales..."></textarea>
            </div>


        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-end gap-3">
            <button onclick="closeNewAssignmentModal()"
                class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
            <button onclick="saveAssignment()"
                class="px-6 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-zinc-800 transition-colors shadow-lg">Guardar
                Asignación</button>
        </div>
    </div>
</div>

</div>



<!-- MODAL GENERAR CARGOS MES (Correct Position) -->
<div id="generateChargesModal"
    class="hidden fixed inset-0 z-[60] bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-fade-in-up">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50">
            <h3 class="font-serif italic text-xl text-zinc-900">Generar Cobranza</h3>
            <p class="text-xs text-zinc-400 mt-1 font-light">Selecciona el periodo de facturación.</p>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Mes de Cobro</label>
                <select id="genMes"
                    class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-white">
                    <option value="Enero">Enero</option>
                    <option value="Febrero">Febrero</option>
                    <option value="Marzo">Marzo</option>
                    <option value="Abril">Abril</option>
                    <option value="Mayo">Mayo</option>
                    <option value="Junio">Junio</option>
                    <option value="Julio">Julio</option>
                    <option value="Agosto">Agosto</option>
                    <option value="Septiembre">Septiembre</option>
                    <option value="Octubre">Octubre</option>
                    <option value="Noviembre">Noviembre</option>
                    <option value="Diciembre">Diciembre</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Año</label>
                <input type="number" id="genAnio" value="<?php echo date('Y'); ?>"
                    class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Fecha Límite de
                    Pago</label>
                <input type="date" id="genFecha"
                    class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none">
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-end gap-3">
            <button onclick="document.getElementById('generateChargesModal').classList.add('hidden')"
                class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
            <button onclick="submitMonthlyCharges()"
                class="px-6 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-zinc-800 transition-colors shadow-lg">
                <i class="fas fa-bolt mr-1 text-amber-400"></i> Generar
            </button>
        </div>
    </div>
</div>
</div>

<!-- MODAL HISTORIAL -->
<div id="historyModal"
    class="fixed inset-0 z-[70] hidden bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b border-zinc-100 bg-zinc-50 flex justify-between items-center">
            <h3 class="font-bold text-zinc-900">Historial de Movimientos</h3>
            <button onclick="document.getElementById('historyModal').classList.add('hidden')"
                class="text-zinc-400 hover:text-rose-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-0 max-h-[60vh] overflow-y-auto bg-zinc-50/50" id="historyTimeline">
            <!-- Timeline injected via JS -->
            <div class="p-8 text-center text-zinc-400 text-xs">Cargando...</div>
        </div>
    </div>
</div>
</div>

<!-- MODAL DE PAGO (Efectivo / SPEI) -->
<div id="paymentModal"
    class="fixed inset-0 z-[80] hidden bg-zinc-900/90 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-fade-in-up">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50">
            <h3 class="font-bold text-zinc-900 text-lg">Registrar Pago</h3>
            <p class="text-xs text-zinc-400 mt-1" id="payModalTitle">Concepto...</p>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-zinc-100">
            <button onclick="setPaymentMethod('Efectivo')" id="tab-efectivo"
                class="flex-1 py-3 text-sm font-bold border-b-2 border-zinc-900 text-zinc-900 bg-zinc-50">
                <i class="fas fa-money-bill-wave mr-2"></i> Efectivo
            </button>
            <button onclick="setPaymentMethod('SPEI')" id="tab-spei"
                class="flex-1 py-3 text-sm font-bold border-b-2 border-transparent text-zinc-400 hover:text-zinc-600">
                <i class="fas fa-university mr-2"></i> SPEI / Transf.
            </button>
        </div>

        <!-- Body -->
        <div class="p-8">
            <input type="hidden" id="payChargeId">
            <input type="hidden" id="payMethod" value="Efectivo">

            <!-- Full Amount Display (Reference) -->
            <div class="p-4 bg-zinc-50 rounded-lg border border-zinc-100 text-center mb-6">
                <p class="text-[10px] uppercase tracking-widest text-zinc-400 mb-1">Total del Cargo</p>
                <p class="font-serif text-2xl font-bold text-zinc-400" id="payTotalDisplay">$0.00</p>

                <div id="payPartialInfo" class="hidden mt-2 pt-2 border-t border-zinc-200 flex justify-between px-4">
                    <span class="text-xs text-zinc-500">Pagado: <b class="text-emerald-600"
                            id="payPaidDisplay">$0.00</b></span>
                    <span class="text-xs text-zinc-500">Resta: <b class="text-rose-500"
                            id="payRemainingDisplay">$0.00</b></span>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1 text-center">Monto a
                    Pagar</label>
                <div class="relative max-w-[200px] mx-auto">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 font-serif text-xl">$</span>
                    <input type="number" id="payAmountInput" step="0.01"
                        class="w-full pl-8 pr-4 py-2 border-b-2 border-zinc-200 text-center font-serif text-3xl font-bold text-emerald-600 focus:border-zinc-900 outline-none bg-transparent"
                        placeholder="0.00">
                </div>
            </div>

            <!-- SPEI Fields (Hidden by default) -->
            <div id="speiFields" class="hidden mb-4 animate-fade-in">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Referencia /
                    Rastreo</label>
                <input type="text" id="payReference"
                    class="w-full border border-zinc-300 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none uppercase"
                    placeholder="Clave de Rastreo o Referencia bancaria">
            </div>

            <div id="cashFields" class="mb-4 text-center">
                <div class="mb-3 text-left">
                    <label class="block text-xs font-bold uppercase text-zinc-500 mb-1">Comentarios (Opcional)</label>
                    <textarea id="payComment"
                        class="w-full border border-zinc-300 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none resize-none h-20"
                        placeholder="Ej. Pago entregado por padre de familia..."></textarea>
                </div>
                <p class="text-xs text-zinc-400 bg-zinc-50 p-3 rounded border border-dashed border-zinc-200">
                    <i class="fas fa-print mr-1"></i> Se generará un comprobante de pago del sistema automáticamente.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-end gap-3">
            <button onclick="document.getElementById('paymentModal').classList.add('hidden')"
                class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
            <button onclick="confirmPayment()"
                class="px-6 py-2 bg-emerald-500 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-emerald-600 transition-colors shadow-lg">
                Confirmar Pago
            </button>
        </div>
    </div>
</div>

<!-- MODAL VISOR DE COMPROBANTE -->
<div id="receiptModal"
    class="fixed inset-0 z-[90] hidden bg-black/95 backdrop-blur flex items-center justify-center p-4">
    <div class="w-full max-w-2xl flex flex-col items-center">
        <!-- Controls -->
        <div class="w-full flex justify-between items-center mb-4 px-4">
            <h3 class="text-white font-bold"><i class="fas fa-image mr-2"></i> Comprobante de Pago</h3>
            <button onclick="document.getElementById('receiptModal').classList.add('hidden')"
                class="text-zinc-400 hover:text-white transition-colors">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>

        <!-- Image Container -->
        <div id="receiptImageContainer"
            class="bg-zinc-800 rounded-lg shadow-2xl overflow-hidden mb-6 border border-zinc-700 w-full flex items-center justify-center min-h-[300px] relative">
            <img id="receiptImage" src="" alt="Comprobante" class="max-w-full max-h-[70vh] object-contain hidden">

            <!-- Digital Receipt View -->
            <div id="digitalReceipt"
                class="hidden bg-white p-8 w-full max-w-sm mx-auto my-4 rounded shadow-lg text-zinc-900 border border-zinc-200">
                <div class="text-center border-b-2 border-dashed border-zinc-300 pb-4 mb-4">
                    <i class="fas fa-check-circle text-4xl text-emerald-500 mb-2"></i>
                    <h2 class="font-bold text-xl uppercase tracking-widest">Recibo de Pago</h2>
                    <p class="text-xs text-zinc-400">Alessandra Academy</p>
                </div>
                <div class="space-y-3 font-mono text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Fecha:</span>
                        <span class="font-bold" id="rcptDate">--/--/----</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Método:</span>
                        <span class="font-bold" id="rcptMethod">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Referencia:</span>
                        <span class="font-bold" id="rcptRef">--</span>
                    </div>
                    <div class="flex justify-between border-t border-zinc-100 pt-2 mt-2">
                        <span class="font-bold text-lg">Total Pagado:</span>
                        <span class="font-bold text-lg text-emerald-600" id="rcptAmount">$0.00</span>
                    </div>
                </div>
            </div>

            <div id="noReceiptMsg" class="hidden text-zinc-500 text-center">
                <i class="fas fa-ghost fa-3x mb-2"></i><br>No hay comprobante cargado.
            </div>
        </div>

        <!-- PDF View -->
        <div id="pdfView"
            class="hidden flex flex-col items-center justify-center p-10 text-center text-zinc-400 w-full">
            <div class="bg-zinc-900/50 p-6 rounded-full mb-4">
                <i class="fas fa-file-pdf text-5xl text-rose-500"></i>
            </div>
            <h4 class="text-white font-bold text-lg mb-1">Archivo PDF Detectado</h4>
            <p class="text-xs text-zinc-500 mb-6 max-w-xs">El comprobante se subió en formato PDF. Haz clic abajo para
                abrirlo en una nueva pestaña.</p>
            <a id="pdfButton" href="#" target="_blank"
                class="px-6 py-2.5 bg-zinc-700 hover:bg-zinc-600 text-white rounded-full text-xs font-bold uppercase tracking-widest transition-all hover:scale-105 shadow-lg border border-zinc-600">
                <i class="fas fa-external-link-alt mr-2"></i> Ver Documento
            </a>
        </div>
    </div>

    <!-- Validation Actions -->
    <div class="flex flex-col items-center gap-4" id="receiptActions">
        <input type="text" id="rejectReason" placeholder="Motivo de rechazo (Opcional si se valida)"
            class="w-full max-w-md px-4 py-3 bg-zinc-800 border border-zinc-700 rounded text-zinc-100 text-sm focus:border-white outline-none placeholder-zinc-500">

        <div class="flex gap-4">
            <input type="hidden" id="valChargeId">
            <button onclick="validateReceipt('rejected')"
                class="px-6 py-3 bg-rose-600 text-white font-bold rounded hover:bg-rose-500 transition-all shadow-lg hover:shadow-rose-500/20">
                <i class="fas fa-times mr-2"></i> Rechazar
            </button>
            <button onclick="validateReceipt('approved')"
                class="px-6 py-3 bg-emerald-600 text-white font-bold rounded hover:bg-emerald-500 transition-all shadow-lg hover:shadow-emerald-500/20">
                <i class="fas fa-check mr-2"></i> Validar Pago
            </button>
        </div>
    </div>
</div>


<!-- MODAL CANCELAR CARGO -->
<div id="cancelModal"
    class="hidden fixed inset-0 z-[80] bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-fade-in-up">
        <div class="px-6 py-5 border-b border-zinc-100 bg-rose-50 flex justify-between items-center">
            <h3 class="font-bold text-rose-700">Cancelar Cargo</h3>
            <button onclick="closeCancelModal()" class="text-rose-400 hover:text-rose-600"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <div class="mb-4 bg-amber-50 border border-amber-100 p-3 rounded text-xs text-amber-700">
                <i class="fas fa-exclamation-triangle mr-1"></i> Esta acción no se puede deshacer y quedará registrada.
            </div>
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-2">Motivo de
                Cancelación</label>
            <textarea id="cancelReason"
                class="w-full border border-zinc-300 rounded-lg p-3 text-sm focus:border-rose-500 outline-none resize-none"
                rows="3" placeholder="Ej. Error de captura, Alumno dado de baja..."></textarea>
            <input type="hidden" id="cancelChargeId">
        </div>
        <div class="px-6 py-4 bg-zinc-50 flex justify-end gap-2 border-t border-zinc-100">
            <button onclick="closeCancelModal()"
                class="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-800">Cerrar</button>
            <button onclick="confirmCancel(this)"
                class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded shadow hover:bg-rose-700">Eliminar
                Definitivamente</button>
        </div>
    </div>
</div>

<!-- MODAL VENTA BOLETOS -->
<div id="ticketModal"
    class="fixed inset-0 z-[65] hidden bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden animate-fade-in-up flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50 flex justify-between items-center">
            <div>
                <h3 class="font-serif italic text-xl text-zinc-900" id="ticketModalTitle">Venta de Boletos</h3>
                <p class="text-xs text-zinc-400 mt-1 font-light">Genera cargos por boletos a múltiples alumnos.</p>
            </div>
            <button onclick="document.getElementById('ticketModal').classList.add('hidden')"
                class="text-zinc-400 hover:text-rose-500">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Controls -->
        <div class="px-6 py-4 bg-white border-b border-zinc-100 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Costo
                    Unitario</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400">$</span>
                    <input type="number" id="ticketPrice" value="50.00"
                        class="w-full pl-7 pr-3 py-2 border border-zinc-200 rounded text-sm focus:border-zinc-900 outline-none font-bold text-zinc-900">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Cantidad</label>
                <input type="number" id="ticketGlobalQty" value="1" min="1"
                    class="w-full px-3 py-2 border border-zinc-200 rounded text-sm focus:border-zinc-900 outline-none font-bold text-center">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Evento</label>
                <select id="ticketEventSelectModal"
                    class="block w-full px-3 py-2 border border-zinc-200 rounded text-sm focus:border-zinc-900 outline-none bg-white text-ellipsis overflow-hidden">
                    <option value="">Seleccione un evento...</option>
                </select>
            </div>
        </div>

        <!-- Quick Select Toolbar (Tickets) -->
        <div class="px-6 py-3 bg-zinc-50 border-b border-zinc-100 flex items-center gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Selección Rápida:</span>
            <button onclick="setTicketSelection('all')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 shadow-sm transition-colors">
                Todos
            </button>
            <button onclick="setTicketSelection('online')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-emerald-600 hover:bg-emerald-50 hover:border-emerald-200 shadow-sm transition-colors">
                Online
            </button>
            <button onclick="setTicketSelection('presencial')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-blue-600 hover:bg-blue-50 hover:border-blue-200 shadow-sm transition-colors">
                Presencial
            </button>
            <button onclick="setTicketSelection('none')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 shadow-sm transition-colors">
                Ninguno
            </button>
        </div>

        <!-- Dynamic Content Area -->
        <div id="ticket-mode-students">
            <div class="mb-4">
                <input type="text" id="ticketSearch" placeholder="Buscar alumno..."
                    class="w-full border border-zinc-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-zinc-500"
                    onkeyup="filterTicketStudents()">
            </div>

            <div class="max-h-60 overflow-y-auto border border-zinc-200 rounded text-sm">
                <table class="w-full text-left">
                    <thead class="bg-zinc-50 sticky top-0">
                        <tr>
                            <th class="p-2 border-b">
                                <input type="checkbox" onclick="toggleAllTickets(this)">
                            </th>
                            <th class="p-2 border-b font-semibold text-zinc-600">Alumno</th>
                            <th class="p-2 border-b font-semibold text-zinc-600">Referencia</th>
                        </tr>
                    </thead>
                    <tbody id="ticketStudentList">
                        <!-- Populated by JS -->
                        <?php foreach ($alumnos_config as $alum):
                            $forma = strtolower($alum['forma'] ?? 'presencial');
                            ?>
                            <tr class="hover:bg-zinc-50 transition-colors ticket-row"
                                data-id="<?php echo $alum['alumno_id']; ?>" data-forma="<?php echo $forma; ?>">
                                <td class="px-6 py-3">
                                    <input type="checkbox"
                                        class="ticket-check rounded border-zinc-300 text-zinc-900 focus:ring-0"
                                        value="<?php echo $alum['alumno_id']; ?>" onchange="updateRowTotal(this)">
                                </td>
                                <td class="px-6 py-3 text-zinc-700 font-medium">
                                    <?php echo htmlspecialchars($alum['nombre']); ?>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <?php if ($forma === 'online'): ?>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wider">Online</span>
                                    <?php else: ?>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold uppercase tracking-wider">Presencial</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <input type="number" value="1" min="1"
                                        class="ticket-qty w-16 text-center border border-zinc-200 rounded py-1 text-xs focus:border-zinc-900 outline-none"
                                        oninput="updateRowTotal(this)">
                                </td>
                                <td class="px-6 py-3 text-right font-bold text-zinc-900 ticket-row-total text-zinc-300">
                                    $0.00
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="ticket-mode-staff" class="hidden">
            <div class="flex gap-4 mb-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="staffType" value="internal" checked onchange="toggleStaffInput()">
                    <span class="text-sm">Personal Registrado</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="staffType" value="external" onchange="toggleStaffInput()">
                    <span class="text-sm">Staff Externo</span>
                </label>
            </div>

            <div id="staff-internal-input">
                <div class="mb-4">
                    <input type="text" id="staffSearch" placeholder="Buscar staff..."
                        class="w-full border border-zinc-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-zinc-500"
                        onkeyup="filterStaffList()">
                </div>
                <div class="max-h-60 overflow-y-auto border border-zinc-200 rounded text-sm">
                    <table class="w-full text-left">
                        <thead class="bg-zinc-50 sticky top-0">
                            <tr>
                                <th class="p-2 border-b w-10">
                                    <input type="checkbox" onclick="toggleAllStaff(this)">
                                </th>
                                <th class="p-2 border-b font-semibold text-zinc-600">Nombre</th>
                            </tr>
                        </thead>
                        <tbody id="staffListTable">
                            <tr>
                                <td colspan="2" class="p-4 text-center text-zinc-400">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="staff-external-input" class="hidden">
                <label class="block text-xs font-bold uppercase text-zinc-500 mb-1">Nombre del Staff Externo</label>
                <input type="text" id="staffExternalName"
                    class="w-full border border-zinc-300 rounded px-3 py-2 text-sm" placeholder="Ej. Juan Perez">
            </div>
        </div>

        <div id="ticket-mode-model" class="hidden">
            <div class="mb-4">
                <label class="block text-xs font-bold uppercase text-zinc-500 mb-1">Nombre de la Modelo</label>
                <input type="text" id="modelName" class="w-full border border-zinc-300 rounded px-3 py-2 text-sm"
                    placeholder="Ej. Modelo Ana">
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-between items-center">
            <div class="text-xs text-zinc-400">
                <span id="ticketCount">0</span> alumnos seleccionados
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('ticketModal').classList.add('hidden')"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
                <button onclick="submitTicketSales()"
                    class="px-6 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-zinc-800 transition-colors shadow-lg">
                    Generar Cargos
                </button>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<!-- MODAL INSCRIPCIONES -->
<div id="registrationModal"
    class="fixed inset-0 z-[65] hidden bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden animate-fade-in-up flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50 flex justify-between items-center">
            <div>
                <h3 class="font-serif italic text-xl text-zinc-900">Generar Inscripciones</h3>
                <p class="text-xs text-zinc-400 mt-1 font-light">Cargos por concepto de inscripción anual/semestral.</p>
            </div>
            <button onclick="document.getElementById('registrationModal').classList.add('hidden')"
                class="text-zinc-400 hover:text-rose-500">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Controls -->
        <div class="px-6 py-4 bg-white border-b border-zinc-100 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Ciclo /
                    Periodo</label>
                <select id="regCycle"
                    class="w-full px-3 py-2 border border-zinc-200 rounded text-sm focus:border-zinc-900 outline-none bg-white">
                    <?php if (empty($ciclos)): ?>
                        <option value="">No hay ciclos activos</option>
                    <?php else: ?>
                        <?php foreach ($ciclos as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['nombre_ciclo']); ?>">
                                <?php echo htmlspecialchars($c['nombre_ciclo']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Fecha de
                    Vencimiento</label>
                <input type="date" id="regDueDate" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                    class="w-full px-3 py-2 border border-zinc-200 rounded text-sm focus:border-zinc-900 outline-none">
            </div>
        </div>

        <!-- Quick Select Toolbar -->
        <div class="px-6 py-3 bg-zinc-50 border-b border-zinc-100 flex items-center gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Selección Rápida:</span>
            <button onclick="setRegistrationSelection('all')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 shadow-sm transition-colors">
                Todos
            </button>
            <button onclick="setRegistrationSelection('online')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-emerald-600 hover:bg-emerald-50 hover:border-emerald-200 shadow-sm transition-colors">
                Online
            </button>
            <button onclick="setRegistrationSelection('presencial')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-blue-600 hover:bg-blue-50 hover:border-blue-200 shadow-sm transition-colors">
                Presencial
            </button>
            <button onclick="setRegistrationSelection('none')"
                class="px-2 py-1 rounded bg-white border border-zinc-200 text-xs font-medium text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 shadow-sm transition-colors">
                Ninguno
            </button>
            <div class="ml-auto text-xs text-zinc-400">
                <span id="regSelectedCount">0</span> seleccionados
            </div>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto p-0">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 sticky top-0 z-10 border-b border-zinc-100">
                    <tr>
                        <th class="px-6 py-3 w-10">
                            <input type="checkbox" onchange="toggleAllReg(this)"
                                class="rounded border-zinc-300 text-zinc-900 focus:ring-0">
                        </th>
                        <th class="px-6 py-3 font-medium uppercase text-xs tracking-wider">Alumno</th>
                        <th class="px-6 py-3 font-medium uppercase text-xs tracking-wider text-center">Forma</th>
                        <th class="px-6 py-3 w-32 font-medium uppercase text-xs tracking-wider text-right">Monto Base
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50">
                    <?php foreach ($alumnos_config as $alum):
                        $forma = strtolower($alum['forma'] ?? 'presencial');
                        ?>
                        <tr class="hover:bg-zinc-50 transition-colors reg-row" data-id="<?php echo $alum['alumno_id']; ?>"
                            data-forma="<?php echo $forma; ?>">
                            <td class="px-6 py-3">
                                <input type="checkbox" class="reg-check rounded border-zinc-300 text-zinc-900 focus:ring-0"
                                    onchange="updateRegCount()">
                            </td>
                            <td class="px-6 py-3 text-zinc-700 font-medium">
                                <?php echo htmlspecialchars($alum['nombre']); ?>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <?php if ($forma === 'online'): ?>
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wider">Online</span>
                                <?php else: ?>
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold uppercase tracking-wider">Presencial</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right font-bold text-zinc-900">
                                <input type="hidden" class="reg-amount" value="<?php echo $alum['inscripcion_base']; ?>">
                                $<?php echo number_format($alum['inscripcion_base'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-between items-center">
            <div class="text-xs text-zinc-400">
                <span id="regSelectedCount">0</span> alumnos seleccionados
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('registrationModal').classList.add('hidden')"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 hover:text-zinc-800 transition-colors">Cancelar</button>
                <button onclick="submitRegistrationCharges()"
                    class="px-6 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-zinc-800 transition-colors shadow-lg">
                    Generar Cargos
                </button>
            </div>
        </div>
    </div>
</div>


<!-- MODAL AGREGAR EVENTO (Simple input) -->
<div id="addEventModal"
    class="fixed inset-0 z-[70] hidden bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b border-zinc-100 bg-zinc-50">
            <h3 class="font-serif italic text-lg text-zinc-900">Nuevo Evento</h3>
        </div>
        <div class="p-6">
            <label class="block text-xs font-bold uppercase text-zinc-500 mb-1">Nombre</label>
            <input type="text" id="newEventName" class="w-full border border-zinc-300 rounded px-3 py-2 mb-4"
                placeholder="Ej. Gala de Invierno">
            <div class="flex gap-2 justify-end">
                <button onclick="document.getElementById('addEventModal').classList.add('hidden')"
                    class="px-4 py-2 text-xs font-bold uppercase text-zinc-500">Cancelar</button>
                <button onclick="submitNewEvent()"
                    class="px-4 py-2 bg-zinc-900 text-white rounded text-xs font-bold uppercase">Crear</button>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        // Hide all views
        document.getElementById('view-config').classList.add('hidden');
        document.getElementById('view-cobranza').classList.add('hidden');
        document.getElementById('view-historico').classList.add('hidden');
        document.getElementById('view-eventos').classList.add('hidden');

        // Reset tabs
        ['config', 'cobranza', 'historico', 'eventos'].forEach(t => {
            const btn = document.getElementById(`tab-${t}`);
            btn.className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900";
        });

        // Show active view
        document.getElementById(`view-${tab}`).classList.remove('hidden');

        // Active tab style
        const activeBtn = document.getElementById(`tab-${tab}`);
        activeBtn.className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm";

        if (tab === 'eventos') {
            loadEventsTable();
        }

        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
    }


    function openAdjustmentModal(id, name, original, surcharge, scholarship) {
        document.getElementById('modalChargeId').value = id;
        document.getElementById('modalStudentName').innerText = name;

        // Store raw values in dataset for calculation
        const modal = document.getElementById('adjustmentModal');
        modal.dataset.original = original;

        // Populate "Current Status" Info
        document.getElementById('modalOriginalAmount').innerText = '$' + original.toLocaleString('en-US') + '.00';
        document.getElementById('modalSurcharge').innerText = '$' + surcharge.toLocaleString('en-US') + '.00';
        document.getElementById('modalTotalAmount').innerText = '$' + (original + surcharge).toLocaleString('en-US') + '.00';

        // Determine Default Input Value for New Recargos
        let defaultRecargos = surcharge;

        // **User Request**: If current surcharge is 0, propose the scholarship amount as penalty
        // Ensure scholarship is a number
        const schValue = parseFloat(scholarship) || 0;
        if (defaultRecargos === 0 && schValue > 0) {
            defaultRecargos = schValue;
        }

        const newTotal = original + defaultRecargos;

        // Set inputs
        document.getElementById('modalRecargosInput').value = defaultRecargos.toFixed(2);
        document.getElementById('modalFinalInput').value = newTotal.toFixed(2);
        document.getElementById('modalMotivo').value = '';

        document.getElementById('adjustmentModal').classList.remove('hidden');
    }

    function updateModalTotal(source) {
        const modal = document.getElementById('adjustmentModal');
        const originalBase = parseFloat(modal.dataset.original);

        const recargosInput = document.getElementById('modalRecargosInput');
        const totalInput = document.getElementById('modalFinalInput');

        let recargos = parseFloat(recargosInput.value) || 0;
        let total = parseFloat(totalInput.value) || 0;

        if (source === 'recargos') {
            // If recargos change, Total = OriginalBase + NewRecargos
            total = originalBase + recargos;
            totalInput.value = total.toFixed(2);
        } else if (source === 'total') {
            // If total changes, Recargos = Total - OriginalBase
            recargos = total - originalBase;
            if (recargos < 0) recargos = 0; // Prevent negative recargos? Or allow discount? Allow for now but logic implies penalty.
            recargosInput.value = recargos.toFixed(2);
        }
    }

    function openHistoryModal(id, studentName) {
        document.getElementById('historyModal').classList.remove('hidden');
        const container = document.getElementById('historyTimeline');
        container.innerHTML = '<div class="p-8 text-center text-zinc-400 text-xs"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando historial...</div>';

        const formData = new FormData();
        formData.append('action', 'fetch_history');

        // Use GET parameter logic or append to URL since action is usually GET for fetch
        fetch('../../back/admin_actions_finanzas.php?action=fetch_history&charge_id=' + id)
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        if (data.data.events.length === 0 && data.data.payments.length === 0) {
                            container.innerHTML = '<div class="p-8 text-center text-zinc-400 text-xs">No hay movimientos registrados.</div>';
                            return;
                        }

                        let html = '<div class="relative pl-8 pr-6 py-6 border-l-2 border-zinc-200 ml-6 space-y-8">';

                        // Render Events
                        data.data.events.forEach(event => {
                            let icon = 'fa-circle';
                            let color = 'bg-zinc-200 text-zinc-400';
                            let borderColor = 'border-zinc-200';
                            let titleVal = event.tipo_evento;

                            if (event.tipo_evento === 'CREACION') {
                                icon = 'fa-plus';
                                color = 'bg-zinc-900 text-white';
                                borderColor = 'border-zinc-900';
                                titleVal = 'Cargo Generado';
                            } else if (event.tipo_evento === 'AJUSTE') {
                                icon = 'fa-wrench';
                                color = 'bg-amber-400 text-white';
                                borderColor = 'border-amber-400';
                                titleVal = 'Ajuste Manual';
                            } else if (event.tipo_evento === 'PAGO') {
                                icon = 'fa-check';
                                color = 'bg-emerald-500 text-white';
                                borderColor = 'border-emerald-500';
                                titleVal = 'Pago Recibido';
                            } else if (event.tipo_evento === 'VENCIMIENTO') {
                                icon = 'fa-clock';
                                color = 'bg-rose-500 text-white';
                                borderColor = 'border-rose-500';
                                titleVal = 'Vencimiento y Penalización';
                            } else if (event.tipo_evento === 'CANCELACION') {
                                icon = 'fa-trash-alt';
                                color = 'bg-rose-600 text-white';
                                borderColor = 'border-rose-600';
                                titleVal = 'Cancelación';
                            } else if (event.tipo_evento === 'RECORDATORIO' || event.tipo_evento === 'OTRO') {
                                icon = 'fa-bell';
                                color = 'bg-blue-400 text-white';
                                borderColor = 'border-blue-400';
                                titleVal = 'Notificación';
                            }

                            const dateObj = new Date(event.fecha_evento); // Fix date field name
                            const day = dateObj.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
                            const time = dateObj.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

                            html += `
                             <div class="relative">
                                 <span class="absolute -left-[41px] top-0 w-8 h-8 rounded-full flex items-center justify-center ${color} border-2 ${borderColor} shadow-sm z-10">
                                     <i class="fas ${icon} text-[10px]"></i>
                                 </span>
                                 <div>
                                     <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-0.5">${day} <span class="text-zinc-300 font-normal">| ${time}</span></p>
                                     <h5 class="text-xs font-bold text-zinc-800 mb-1">${titleVal}</h5>
                                     <p class="text-xs text-zinc-500 leading-relaxed font-light bg-white p-2 rounded border border-zinc-100 shadow-sm">
                                         ${event.descripcion}
                                     </p>
                                 </div>
                             </div>
                             `;
                        });

                        html += '</div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">${data.message}</div>`;
                    }
                } catch (e) {
                    console.error('JSON Error:', e);
                    console.log('Raw text:', text);
                    container.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">Error de datos. <br>Raw: ${text.substring(0, 50)}...</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<div class="p-4 text-center text-rose-500 text-xs">Error de conexión fatal.</div>';
            });
    }

    // --- PAYMENT MODAL LOGIC ---
    function openPaymentModal(id, total, pagado, saldo, concepto) {
        document.getElementById('payChargeId').value = id;
        document.getElementById('payModalTitle').innerText = concepto;

        // Display Info
        document.getElementById('payTotalDisplay').innerText = '$' + parseFloat(total).toFixed(2);

        // Setup Partial Info
        const pPaid = parseFloat(pagado) || 0;
        const pBal = parseFloat(saldo);
        // Note: Saldo passed might be string, ensure float. 
        // Check if balance is valid, otherwise use total - paid.

        if (pPaid > 0) {
            document.getElementById('payPartialInfo').classList.remove('hidden');
            document.getElementById('payPaidDisplay').innerText = '$' + pPaid.toFixed(2);
            document.getElementById('payRemainingDisplay').innerText = '$' + pBal.toFixed(2);
        } else {
            document.getElementById('payPartialInfo').classList.add('hidden');
        }

        // Default Input Amount = Remaining Balance
        document.getElementById('payAmountInput').value = pBal.toFixed(2);

        setPaymentMethod('Efectivo'); // Reset default
        document.getElementById('paymentModal').classList.remove('hidden');

        // Auto focus
        setTimeout(() => document.getElementById('payAmountInput').select(), 100);
    }

    function setPaymentMethod(method) {
        document.getElementById('payMethod').value = method;
        const tabEfectivo = document.getElementById('tab-efectivo');
        const tabSpei = document.getElementById('tab-spei');
        const cashFields = document.getElementById('cashFields');
        const speiFields = document.getElementById('speiFields');

        if (method === 'Efectivo') {
            tabEfectivo.className = "flex-1 py-3 text-sm font-bold border-b-2 border-zinc-900 text-zinc-900 bg-zinc-50";
            tabSpei.className = "flex-1 py-3 text-sm font-bold border-b-2 border-transparent text-zinc-400 hover:text-zinc-600";
            cashFields.classList.remove('hidden');
            speiFields.classList.add('hidden');
        } else {
            tabEfectivo.className = "flex-1 py-3 text-sm font-bold border-b-2 border-transparent text-zinc-400 hover:text-zinc-600";
            tabSpei.className = "flex-1 py-3 text-sm font-bold border-b-2 border-zinc-900 text-zinc-900 bg-zinc-50";
            cashFields.classList.add('hidden');
            speiFields.classList.remove('hidden');
        }
    }

    function confirmPayment() {
        const id = document.getElementById('payChargeId').value;
        const method = document.getElementById('payMethod').value;
        const reference = document.getElementById('payReference').value;
        const amount = document.getElementById('payAmountInput').value;
        const comment = document.getElementById('payComment').value;

        if (parseFloat(amount) < 0) {
            alert('Por favor verifica el monto a pagar (no puede ser negativo).');
            return;
        }

        if (method === 'SPEI' && !reference.trim()) {
            alert('Por favor ingresa la referencia o clave de rastreo.');
            return;
        }

        if (!confirm(`¿Confirma recibir pago por $${amount} en ${method}?`)) return;

        const formData = new FormData();
        formData.append('action', 'pay_charge');
        formData.append('charge_id', id);
        formData.append('metodo', method);
        formData.append('referencia', reference);
        formData.append('referencia', reference);
        formData.append('monto', amount);
        formData.append('nota', comment);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (method === 'Efectivo') {
                        // Generate System Receipt (Mock print)
                        // In a real app, this would open a PDF or a printable page
                        alert('Pago registrado. Generando recibo...');
                        // window.open('receipt_print.php?id=' + id, '_blank');
                    }
                    window.location.href = window.location.pathname + "?page=finanzas&tab=cobranza";
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => console.error(err));
    }

    // --- RECEIPT MODAL LOGIC ---
    function openReceiptModal(id, url, status, method, reference, date, amount) {
        document.getElementById('valChargeId').value = id;
        const imgContainer = document.getElementById('receiptImageContainer');
        const img = document.getElementById('receiptImage');
        const digital = document.getElementById('digitalReceipt');
        const pdfView = document.getElementById('pdfView');
        const noMsg = document.getElementById('noReceiptMsg');
        const actions = document.getElementById('receiptActions');

        // Reset
        document.getElementById('rejectReason').value = '';
        imgContainer.classList.add('hidden');
        img.classList.add('hidden');
        digital.classList.add('hidden');
        pdfView.classList.add('hidden');
        noMsg.classList.add('hidden');
        actions.classList.add('hidden');

        if (url) {
            // Check extension
            const ext = url.split('.').pop().toLowerCase();
            if (ext === 'pdf') {
                document.getElementById('pdfButton').href = url;
                pdfView.classList.remove('hidden');
                // Container remains hidden
            } else {
                img.src = url;
                img.classList.remove('hidden');
                imgContainer.classList.remove('hidden'); // Show Container
            }
            actions.classList.remove('hidden');
        } else if (status === 'Pagado') {
            // Show Digital Receipt
            digital.classList.remove('hidden');
            imgContainer.classList.remove('hidden'); // Show Container

            document.getElementById('rcptDate').innerText = date || 'N/A';
            document.getElementById('rcptMethod').innerText = method || 'N/A';
            document.getElementById('rcptRef').innerText = reference || 'N/A';
            document.getElementById('rcptAmount').innerText = '$' + parseFloat(amount).toFixed(2);
        } else {
            noMsg.classList.remove('hidden');
            imgContainer.classList.remove('hidden'); // Show Container
        }

        document.getElementById('receiptModal').classList.remove('hidden');
    }

    function validateReceipt(status) {
        const id = document.getElementById('valChargeId').value;
        const reasonInput = document.getElementById('rejectReason');
        let reason = reasonInput.value.trim();

        if (status === 'rejected') {
            if (reason === '') {
                alert('Por favor, ingresa el motivo del rechazo en el campo de texto.');
                reasonInput.focus();
                return;
            }
            if (!confirm('¿Confirmas que deseas rechazar este comprobante?')) return;
        } else {
            if (!confirm('¿Validar este pago?')) return;
        }

        const formData = new FormData();
        formData.append('action', 'validate_receipt');
        formData.append('charge_id', id);
        formData.append('status', status);
        if (reason) formData.append('reason', reason);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = window.location.pathname + "?page=finanzas&tab=cobranza";
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function submitAdjustment() {
        const id = document.getElementById('modalChargeId').value;
        const finalAmount = document.getElementById('modalFinalInput').value;
        const recargosAmount = document.getElementById('modalRecargosInput').value;
        const notes = document.getElementById('modalMotivo').value;

        if (!notes.trim()) return alert('Debes especificar un motivo para el ajuste.');

        const formData = new FormData();
        formData.append('action', 'adjust_charge');
        formData.append('charge_id', id);
        formData.append('new_total', finalAmount);
        formData.append('recargos_amount', recargosAmount);
        formData.append('notes', notes);

        fetch('../../back/admin_actions_finanzas.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Ajuste aplicado correctamente.');
                    window.location.href = window.location.pathname + '?page=finanzas&tab=cobranza';
                } else {
                    alert('Error: ' + data.message);
                }
            });

    }

    // --- TICKET MODAL LOGIC ---

    function filterTicketStudents() {
        const term = document.getElementById('ticketSearch').value.toLowerCase();
        const rows = document.querySelectorAll('#ticketStudentList .ticket-row');
        rows.forEach(row => {
            const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            if (name.includes(term)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    let currentTicketType = 'General';

    function openTicketModal(type = 'General') {
        currentTicketType = type;
        const titleMap = {
            'General': 'Venta de Boletos',
            'Staff': 'Boletos Staff',
            'Modelos': 'Boletos Modelos',
            'Invitados': 'Boletos Invitados'
        };
        // If type is Invitados OR Modelos, it's linked to students (logic stated by user: "related to student")
        // So Invitados and Modelos use Student Mode.
        const isStudentMode = (type === 'General' || type === 'Invitados' || type === 'Modelos');
        const isStaffMode = (type === 'Staff');
        // const isModelMode = (type === 'Modelos'); // Now generic student mode

        document.getElementById('ticketModalTitle').textContent = titleMap[type] || 'Venta de Boletos';
        document.getElementById('ticketModal').classList.remove('hidden');

        // Toggle UI Sections
        document.getElementById('ticket-mode-students').classList.toggle('hidden', !isStudentMode);
        document.getElementById('ticket-mode-staff').classList.toggle('hidden', !isStaffMode);
        document.getElementById('ticket-mode-model').classList.add('hidden'); // Always hide old model input

        // Reset Inputs
        if (isStudentMode) {
            document.querySelectorAll('.ticket-check').forEach(c => c.checked = false);
            // updateRowTotal(document.querySelector('.ticket-check')); // Reset totals - this might fail if no checks
            document.querySelectorAll('.ticket-row-total').forEach(el => {
                el.innerText = '$0.00';
                el.classList.add('text-zinc-300');
                el.classList.remove('text-zinc-900');
            });
            document.querySelectorAll('.ticket-qty').forEach(el => el.value = '1');
            updateTicketCount();
        }

        if (isStaffMode) {
            loadStaffList();
            document.getElementById('staffExternalName').value = '';
            document.querySelector('input[name="staffType"][value="internal"]').checked = true;
            toggleStaffInput();
        }

        loadTicketEvents();
    }

    function toggleStaffInput() {
        const type = document.querySelector('input[name="staffType"]:checked').value;
        document.getElementById('staff-internal-input').classList.toggle('hidden', type !== 'internal');
        document.getElementById('staff-external-input').classList.toggle('hidden', type !== 'external');
    }

    function loadStaffList() {
        const tbody = document.getElementById('staffListTable');
        tbody.innerHTML = '<tr><td colspan="2" class="p-4 text-center text-zinc-400">Cargando...</td></tr>';

        fetch('../../back/admin_actions_finanzas.php?action=fetch_staff')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    let html = '';
                    data.data.forEach(u => {
                        html += `
                        <tr class="hover:bg-zinc-50 transition-colors staff-row" data-search="${u.nombre_completo.toLowerCase()}">
                            <td class="px-6 py-3">
                                <input type="checkbox" class="staff-check rounded border-zinc-300 text-zinc-900 focus:ring-0" 
                                    value="${u.id}" onchange="updateStaffCount()">
                            </td>
                            <td class="px-6 py-3 text-zinc-700 font-medium">
                                ${u.nombre_completo}
                            </td>
                        </tr>`;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="2" class="p-4 text-center text-rose-500">Error al cargar staff</td></tr>';
                }
            })
            .catch(err => {
                console.error("Fetch staff error:", err);
                tbody.innerHTML = '<tr><td colspan="2" class="p-4 text-center text-rose-500">Error de conexión</td></tr>';
            });
    }

    function toggleAllStaff(source) {
        document.querySelectorAll('.staff-check').forEach(cb => {
            // Only toggle visible rows
            if (!cb.closest('tr').classList.contains('hidden')) {
                cb.checked = source.checked;
            }
        });
        updateStaffCount();
    }

    function filterStaffList() {
        const term = document.getElementById('staffSearch').value.toLowerCase();
        document.querySelectorAll('.staff-row').forEach(row => {
            const name = row.dataset.search || '';
            if (name.includes(term)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    function updateStaffCount() {
        // Optional: Update a counter if needed, or just let submit handle it.
        // For now, no specific "Staff Count" display requested, but good practice.
    }

    function toggleAllTickets(source) {
        document.querySelectorAll('.ticket-check').forEach(cb => {
            cb.checked = source.checked;
            updateRowTotal(cb);
        });
        updateTicketCount();
    }

    function setTicketSelection(mode) {
        const checkboxes = document.querySelectorAll('.ticket-check');
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (!row) return;
            const forma = row.dataset.forma || 'presencial';

            let shouldCheck = false;
            if (mode === 'all') {
                shouldCheck = true;
            } else if (mode === 'none') {
                shouldCheck = false;
            } else if (mode === 'online') {
                shouldCheck = (forma === 'online');
            } else if (mode === 'presencial') {
                shouldCheck = (forma === 'presencial');

            }

            cb.checked = shouldCheck;
            updateRowTotal(cb); // Update status and totals
        });
        updateTicketCount();
    }

    function updateRowTotal(el) {
        const row = el.closest('tr');
        const check = row.querySelector('.ticket-check');
        const qty = row.querySelector('.ticket-qty');
        const totalDisplay = row.querySelector('.ticket-row-total');
        const price = parseFloat(document.getElementById('ticketPrice').value) || 0;

        if (check.checked) {
            const total = (parseInt(qty.value) || 0) * price;
            totalDisplay.innerText = '$' + total.toFixed(2);
            totalDisplay.classList.remove('text-zinc-300');
            totalDisplay.classList.add('text-zinc-900');
        } else {
            totalDisplay.innerText = '$0.00';
            totalDisplay.classList.add('text-zinc-300');
            totalDisplay.classList.remove('text-zinc-900');
        }
        updateTicketCount();
    }

    function updateTicketCount() {
        const count = document.querySelectorAll('.ticket-check:checked').length;
        document.getElementById('ticketCount').innerText = count;
    }

    // Update totals when global price changes
    document.getElementById('ticketPrice').addEventListener('input', function () {
        document.querySelectorAll('.ticket-check').forEach(cb => updateRowTotal(cb));
    });

    // Update quantities when global quantity changes
    document.getElementById('ticketGlobalQty').addEventListener('input', function () {
        const val = this.value;
        document.querySelectorAll('.ticket-qty').forEach(qty => {
            qty.value = val;
            updateRowTotal(qty); // Recalc totals
        });
    });


    function loadTicketEvents() {
        const select = document.getElementById('ticketEventSelectModal');
        const currentVal = select.value;
        select.innerHTML = '<option value="">Cargando...</option>';

        const formData = new FormData();
        formData.append('action', 'get_events');
        formData.append('mode', 'active');

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let html = '<option value="">Seleccione un evento...</option>';
                    if (data.data.length === 0) {
                        html = '<option value="">No hay eventos activos</option>';
                    } else {
                        data.data.forEach(ev => {
                            const sel = ev.id == currentVal ? 'selected' : '';
                            html += `<option value="${ev.id}" ${sel}>${ev.nombre}</option>`;
                        });
                    }
                    select.innerHTML = html;
                } else {
                    select.innerHTML = '<option value="">Error al cargar: ' + data.message + '</option>';
                    console.error(data.message);
                }
            })
            .catch(err => {
                select.innerHTML = '<option value="">Error de conexión</option>';
                console.error(err);
            });
    }

    // --- EVENT MANAGER LOGIC ---

    // --- NEW EVENT MANAGEMENT TAB LOGIC ---

    function loadEventsTable() {
        const tbody = document.getElementById('eventsListTable'); // Ensure ID matches HTML in view-eventos
        if (!tbody) {
            // If tbody is missing (maybe I need to check if I added the HTML properly too), try to find it or fail gracefully
            console.error("eventsListTable not found in DOM");
            return;
        }
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-zinc-400">Cargando eventos...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'get_events');
        formData.append('mode', 'all');

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    renderEventsTable(data.data);
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-rose-500">Error al cargar eventos: ' + (data.message || 'Desconocido') + '</td></tr>';
                }
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-rose-500">Error de conexión</td></tr>';
            });
    }

    function renderEventsTable(events) {
        const tbody = document.getElementById('eventsListTable');
        if (events.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-zinc-400">No hay eventos registrados.</td></tr>';
            return;
        }

        let html = '';
        events.forEach(ev => {
            const isActive = ev.activo == 1; // Explicit check
            const rowClass = isActive ? 'hover:bg-zinc-50' : 'bg-zinc-50 opacity-75 grayscale';
            const statusBadge = isActive
                ? '<span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase">Activo</span>'
                : '<span class="px-2 py-1 rounded-full bg-zinc-200 text-zinc-500 text-[10px] font-bold uppercase">Cerrado</span>';

            const closeBtn = isActive
                ? `<button onclick="closeEvent(${ev.id})" class="w-8 h-8 rounded-full bg-white border border-zinc-200 text-zinc-400 hover:border-zinc-900 hover:text-zinc-900 shadow-sm transition-all" title="Cerrar Evento (Terminar)"><i class="fas fa-flag-checkered"></i></button>`
                : `<button disabled class="w-8 h-8 rounded-full bg-zinc-100 text-zinc-300 cursor-not-allowed"><i class="fas fa-flag-checkered"></i></button>`;

            html += `
                <tr class="border-b border-zinc-100 last:border-0 transition-colors ${rowClass}">
                    <td class="px-6 py-4 text-center text-xs text-zinc-400 font-mono">${ev.id}</td>
                    <td class="px-6 py-4 font-medium text-zinc-800">
                        <input type="text" value="${ev.nombre}" 
                            class="bg-transparent border-none focus:bg-white focus:ring-1 focus:ring-zinc-200 rounded px-2 py-1 w-full outline-none transition-all ${isActive ? 'cursor-text' : 'cursor-not-allowed'}"
                            onchange="updateEventName(${ev.id}, this)"
                            ${!isActive ? 'disabled' : ''}>
                    </td>
                    <td class="px-6 py-4 text-center text-xs text-zinc-500">
                        ${ev.fecha}
                    </td>
                    <td class="px-6 py-4 text-center">
                        ${statusBadge}
                    </td>
                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                        ${closeBtn}
                        <button onclick="deleteEvent(${ev.id})" class="w-8 h-8 rounded-full bg-white border border-zinc-200 text-zinc-400 hover:border-rose-500 hover:text-rose-500 shadow-sm transition-all" title="Eliminar Evento">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function submitNewEvent() {
        const nameInput = document.getElementById('newEventName');
        const nombre = nameInput.value.trim();
        if (!nombre) return alert('Ingresa un nombre para el evento');

        const formData = new FormData();
        formData.append('action', 'add_event');
        formData.append('nombre', nombre);
        formData.append('fecha', new Date().toISOString().split('T')[0]);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    nameInput.value = ''; // Reset
                    document.getElementById('addEventModal').classList.add('hidden');
                    loadEventsTable();
                    alert('Evento creado exitosamente');
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function updateEventName(id, input) {
        const newName = input.value.trim();
        if (!newName) return;

        const formData = new FormData();
        formData.append('action', 'edit_event');
        formData.append('id', id);
        formData.append('nombre', newName);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.classList.add('text-emerald-600');
                    setTimeout(() => input.classList.remove('text-emerald-600'), 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function closeEvent(id) {
        if (!confirm('¿Estás seguro de CERRAR este evento? \n\nUna vez cerrado, no aparecerá en las opciones de venta de boletos. Esta acción no elimina los registros de venta existentes.')) return;

        const formData = new FormData();
        formData.append('action', 'close_event');
        formData.append('id', id);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadEventsTable();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function deleteEvent(id) {
        if (!confirm('¿Eliminar evento permanentemente? \n\nSolo se pueden eliminar eventos sin boletos vendidos.')) return;

        const formData = new FormData();
        formData.append('action', 'delete_event');
        formData.append('id', id);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadEventsTable();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function openAddEventModal() {
        document.getElementById('addEventModal').classList.remove('hidden');
        document.getElementById('newEventName').focus();
    }


    function submitTicketSales() {
        const eventId = document.getElementById('ticketEventSelectModal').value;
        const select = document.getElementById('ticketEventSelectModal');
        const eventName = select.options[select.selectedIndex]?.text || 'Evento';
        const price = parseFloat(document.getElementById('ticketPrice').value) || 0;
        const globalQty = parseInt(document.getElementById('ticketGlobalQty').value) || 1;

        if (!eventId) {
            alert('Seleccione un evento.');
            return;
        }
        const minPrice = (currentTicketType === 'Staff') ? 0 : 0.01;
        if (price < minPrice) {
            alert('Especifique un precio válido.');
            return;
        }

        let salesData = [];
        let conceptPrefix = "Boletos: " + eventName;
        if (currentTicketType !== 'General') {
            conceptPrefix = currentTicketType + ": " + eventName;
        }

        if (currentTicketType === 'General' || currentTicketType === 'Invitados' || currentTicketType === 'Modelos') {
            // Student/Guest/Model Mode (Multiple Selection)
            const checks = document.querySelectorAll('.ticket-check:checked');
            if (checks.length === 0) {
                alert('Seleccione al menos un alumno.');
                return;
            }
            checks.forEach(chk => {
                const row = chk.closest('tr');
                const qty = parseInt(row.querySelector('.ticket-qty').value) || 1;
                salesData.push({
                    student_id: chk.value,
                    quantity: qty,
                    price: price
                });
            });

        } else if (currentTicketType === 'Staff') {
            const staffType = document.querySelector('input[name="staffType"]:checked').value;
            const qty = globalQty; // Use global quantity for staff/model

            if (staffType === 'internal') {
                const checks = document.querySelectorAll('.staff-check:checked');
                if (checks.length === 0) {
                    alert('Seleccione al menos un miembro del Staff');
                    return;
                }
                checks.forEach(chk => {
                    salesData.push({
                        student_id: chk.value,
                        quantity: qty,
                        price: price
                    });
                });
            } else {
                // External Staff
                const name = document.getElementById('staffExternalName').value.trim();
                if (!name) { alert('Ingrese el nombre del Staff Externo'); return; }
                salesData.push({
                    student_id: 0, // 0 for external
                    external_name: name,
                    quantity: qty,
                    price: price
                });
            }
        }

        if (salesData.length === 0) return;

        if (!confirm(`¿Confirmar generación de ${salesData.length} cargo(s)?`)) return;

        const formData = new FormData();
        formData.append('action', 'generate_ticket_charges');
        formData.append('data', JSON.stringify(salesData));
        formData.append('concept', conceptPrefix);
        formData.append('evento_id', eventId);

        fetch('../../back/admin_actions_finanzas.php?action=generate_ticket_charges', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Cargos generados exitosamente.');
                    document.getElementById('ticketModal').classList.add('hidden');
                    // Reload to show new charges
                    window.location.href = window.location.pathname + "?page=finanzas&tab=cobranza";
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error("Error generating ticket charges:", err);
                alert("Error al generar cargos de boletos. Consulte la consola para más detalles.");
            });
    }

    // --- REGISTRATION MODAL LOGIC ---
    function openRegistrationModal() {
        document.getElementById('registrationModal').classList.remove('hidden');
        document.querySelectorAll('.reg-check').forEach(c => c.checked = false);
        document.getElementById('regCount').innerText = '0';
    }



    function submitRegistrationCharges() {
        const cycle = document.getElementById('regCycle').value;
        const dueDate = document.getElementById('regDueDate').value;

        if (!cycle.trim()) {
            alert('Por favor especifica el Ciclo o Periodo (ej. 2025).');
            return;
        }

        const selected = [];
        document.querySelectorAll('.reg-check:checked').forEach(cb => {
            const row = cb.closest('tr');
            selected.push({
                student_id: row.dataset.id,
                amount: row.querySelector('.reg-amount').value
            });
        });

        if (selected.length === 0) {
            alert('Selecciona al menos un alumno.');
            return;
        }

        if (!confirm(`¿Generar cargos de Inscripción para ${selected.length} alumnos?\nCiclo: ${cycle}\nVencimiento: ${dueDate}`)) return;

        const formData = new FormData();
        formData.append('action', 'generate_registration_charges');
        formData.append('cycle', cycle);
        formData.append('due_date', dueDate);
        formData.append('data', JSON.stringify(selected));

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = window.location.pathname + "?page=finanzas&tab=cobranza";
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function closeAdjustmentModal() {
        document.getElementById('adjustmentModal').classList.add('hidden');
    }

    function openNewAssignmentModal() {
        document.getElementById('newAssignmentModal').classList.remove('hidden');
    }

    function saveAssignment() {
        const studentId = document.getElementById('modalStudentId').value;
        // Collect other values
        const tuition = document.querySelector('#newAssignmentModal input[placeholder="0.00"]').value; // Note: risky selector, better add IDs
        // Actually, let's fix the inputs to have IDs in next step or use relative.
        // Assuming order: Tuition, Registration, Scholarship
        const inputs = document.querySelectorAll('#newAssignmentModal input[type="number"]');
        const colegiatura = inputs[0].value;
        const inscripcion = inputs[1].value;
        const beca = inputs[2].value;
        const notas = document.querySelector('#newAssignmentModal textarea').value;

        if (!studentId) return alert('Selecciona un alumno');

        const formData = new FormData();
        formData.append('action', 'save_assignment');
        formData.append('alumno_id', studentId);
        formData.append('colegiatura', colegiatura);
        formData.append('inscripcion', inscripcion);
        formData.append('beca', beca);
        formData.append('notas', notas);

        fetch('../../back/admin_actions_finanzas.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Asignación guardada');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function triggerMonthlyCharges() {
        // PRE-SELECT CURRENT MONTH
        const months = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        const d = new Date();
        document.getElementById('genMes').value = months[d.getMonth()];
        // DEFAULT DUE DATE: 10th of current month
        const day = "10";
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        document.getElementById('genFecha').value = `${year}-${month}-${day}`;

        document.getElementById('generateChargesModal').classList.remove('hidden');
    }

    function submitMonthlyCharges() {
        const mes = document.getElementById('genMes').value;
        const anio = document.getElementById('genAnio').value;
        const fecha = document.getElementById('genFecha').value;

        if (!fecha) return alert('Selecciona una fecha de vencimiento');

        if (!confirm(`¿Generar cargos para ${mes} ${anio}?`)) return;

        const formData = new FormData();
        formData.append('action', 'generate_monthly_charges');
        formData.append('mes', mes);
        formData.append('anio', anio);
        formData.append('fecha_vencimiento', fecha);

        fetch('../../back/admin_actions_finanzas.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert(data.message);
                        window.location.href = window.location.pathname + '?page=finanzas&tab=cobranza';
                    } else {
                        alert('Atención: ' + data.message);
                        window.location.href = window.location.pathname + '?page=finanzas&tab=cobranza';
                    }
                } catch (e) {
                    console.error('Initial response:', text);
                    alert('Error del servidor (no JSON): ' + text.substring(0, 150) + '...');
                }
            })
            .catch(err => {
                alert('Error de red: ' + err);
            });
    }

    function saveAllChanges() {
        if (!confirm('¿Guardar los cambios en todos los montos editados?')) return;

        const rows = document.querySelectorAll('.row-editable');
        let modifications = [];

        rows.forEach(row => {
            const id = row.getAttribute('data-id');
            const colegiatura = row.querySelector('.input-colegiatura').value;
            const inscripcion = row.querySelector('.input-inscripcion').value;
            const beca = row.querySelector('.input-beca').value;

            modifications.push({
                alumno_id: id,
                colegiatura: colegiatura,
                inscripcion: inscripcion,
                beca: beca,
                notas: '' // Optional for bulk save
            });
        });

        const formData = new FormData();
        formData.append('action', 'bulk_save_assignments');
        formData.append('data', JSON.stringify(modifications));

        fetch('../../back/admin_actions_finanzas.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Cambios guardados correctamente');
                    location.reload(); // To update calculations
                } else {
                    alert('Error al guardar: ' + data.message);
                }
            });
    }

    // Filtering Logic
    const searchConfig = document.getElementById('searchConfig');
    const filterBeca = document.getElementById('filterBeca');
    const filterSucursalConfig = document.getElementById('filterSucursalConfig');

    if (searchConfig && filterBeca && filterSucursalConfig) {
        function filterConfigTable() {
            const searchText = searchConfig.value.toLowerCase();
            const filterState = filterBeca.value;
            const filterSucursal = filterSucursalConfig.value;
            const rows = document.querySelectorAll('#view-config .row-editable');
            let visibleCount = 0;

            rows.forEach(row => {
                const nameCell = row.querySelector('td:first-child');
                const name = nameCell ? nameCell.textContent.toLowerCase() : '';

                const becaInput = row.querySelector('.input-beca');
                const becaValue = becaInput ? parseFloat(becaInput.value) : 0;

                const sucursalType = row.dataset.sucursal || 'presencial';

                let matchesSearch = name.includes(searchText);
                let matchesFilter = true;
                let matchesSucursal = true;

                if (filterState === 'con_beca') {
                    matchesFilter = (becaValue > 0);
                } else if (filterState === 'sin_beca') {
                    matchesFilter = (becaValue <= 0 || isNaN(becaValue));
                }

                if (filterSucursal !== 'all') {
                    matchesSucursal = (sucursalType === filterSucursal);
                }

                if (matchesSearch && matchesFilter && matchesSucursal) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            const countElement = document.getElementById('configRecordCount');
            if (countElement) countElement.innerText = visibleCount;
        }

        searchConfig.addEventListener('keyup', filterConfigTable);
        filterBeca.addEventListener('change', filterConfigTable);
        filterSucursalConfig.addEventListener('change', filterConfigTable);
        // Initial count
        filterConfigTable();
    }

    function closeNewAssignmentModal() {
        document.getElementById('newAssignmentModal').classList.add('hidden');
    }

    // --- FILTER CHARGES LOGIC ---
    function filterCharges() {
        const termStudent = document.getElementById('filterStudent').value.toLowerCase();
        const termConcept = document.getElementById('filterConcept').value.toLowerCase();
        const termStatus = document.getElementById('filterStatus').value;
        const termSucursal = document.getElementById('filterSucursalCharges').value;

        const rows = document.querySelectorAll('.charge-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const student = row.dataset.student || '';
            const concept = row.dataset.concept || '';
            const status = row.dataset.status || '';
            const sucursal = row.dataset.sucursal || 'presencial';

            const matchStudent = student.includes(termStudent);
            const matchConcept = concept.includes(termConcept);
            const matchStatus = termStatus === '' || status === termStatus;
            const matchSucursal = termSucursal === '' || sucursal === termSucursal;

            if (matchStudent && matchConcept && matchStatus && matchSucursal) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('recordCount').innerText = visibleCount;
    }

    // Init count
    document.addEventListener('DOMContentLoaded', () => {
        // Wait a bit or run immediately
        setTimeout(filterCharges, 100);
    });

    // --- HISTORY PAGINATION & FILTERS ---
    let histCurrentPage = 1;
    const histPageSize = 50;

    function filterHistory() {
        histCurrentPage = 1; // Reset to page 1 on search
        renderHistoryTable();
    }

    function renderHistoryTable() {
        const termStudent = document.getElementById('histSearchStudent').value.toLowerCase();
        const termConcept = document.getElementById('histSearchConcept').value.toLowerCase();
        const termSucursal = document.getElementById('filterSucursalHist').value;

        const rows = document.querySelectorAll('.history-row');
        let visibleRows = [];

        // 1. Filter
        rows.forEach(row => {
            const student = row.dataset.student || '';
            const concept = row.dataset.concept || '';
            const sucursal = row.dataset.sucursal || 'presencial';

            const matchStudent = student.includes(termStudent);
            const matchConcept = concept.includes(termConcept);
            const matchSucursal = termSucursal === '' || sucursal === termSucursal;

            if (matchStudent && matchConcept && matchSucursal) {
                visibleRows.push(row);
                row.classList.remove('hidden-by-filter'); // Only used as marker if needed, but we toggle display manually
            } else {
                row.classList.add('hidden-by-filter');
                row.style.display = 'none';
            }
        });

        // 2. Paginate
        const total = visibleRows.length;
        const totalPages = Math.ceil(total / histPageSize);
        if (histCurrentPage > totalPages) histCurrentPage = totalPages || 1;
        if (histCurrentPage < 1) histCurrentPage = 1;

        const start = (histCurrentPage - 1) * histPageSize;
        const end = start + histPageSize;

        visibleRows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // 3. Update UI
        document.getElementById('historyInfo').innerText = `Mostrando ${Math.min(start + 1, total)}-${Math.min(end, total)} de ${total}`;
        document.getElementById('btnPrevHist').disabled = (histCurrentPage === 1);
        document.getElementById('btnNextHist').disabled = (histCurrentPage >= totalPages);
    }

    function prevHistPage() {
        if (histCurrentPage > 1) {
            histCurrentPage--;
            renderHistoryTable();
        }
    }

    function nextHistPage() {
        histCurrentPage++;
        renderHistoryTable();
    }

    // Init History
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(renderHistoryTable, 200);
    });

    // --- New Payment & Delete Actions ---

    function registerPayment(id, amount, concept) {
        // Simple confirmation for "Cash Payment"
        if (!confirm(`¿Registrar PAGO EN EFECTIVO por $${amount}?\nConcepto: ${concept}\n\nEsta acción marcará el cargo como PAGADO.`)) return;

        const formData = new FormData();
        formData.append('action', 'pay_charge');
        formData.append('charge_id', id);

        fetch('../../back/admin_actions_finanzas.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Pago registrado exitosamente.');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function deleteCharge(id) {
        openCancelModal([id]); // Pass as array for consistency
    }

    function deleteSelectedCharges() {
        const checkboxes = document.querySelectorAll('.charge-check:checked');
        const ids = Array.from(checkboxes).map(cb => cb.value);
        if (ids.length === 0) return;
        openCancelModal(ids);
    }

    // --- CUSTOM CANCEL MODAL LOGIC ---
    let pendingCancelIds = [];

    function openCancelModal(ids) {
        // IDs can be single ID or Array
        if (Array.isArray(ids)) {
            pendingCancelIds = ids;
        } else {
            pendingCancelIds = [ids];
        }

        document.getElementById('cancelReason').value = '';
        document.getElementById('cancelModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('cancelReason').focus(), 100);
    }

    function toggleAllCharges(source) {
        const checkboxes = document.querySelectorAll('.charge-check');
        checkboxes.forEach(cb => {
            // Only toggle visible rows
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = source.checked;
            }
        });
        updateBulkUI();
    }

    // --- REGISTRATION MODAL LOGIC ---
    function toggleAllReg(source) {
        document.querySelectorAll('.reg-check').forEach(cb => {
            // Only toggle visible rows if we implemented filtering, but here we just toggle all
            cb.checked = source.checked;
        });
        updateRegCount();
    }

    function setRegistrationSelection(mode) {
        const checkboxes = document.querySelectorAll('.reg-check');
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (!row) return;
            const forma = row.dataset.forma || 'presencial';

            if (mode === 'all') {
                cb.checked = true;
            } else if (mode === 'none') {
                cb.checked = false;
            } else if (mode === 'online') {
                cb.checked = (forma === 'online');
            } else if (mode === 'presencial') {
                cb.checked = (forma === 'presencial');
            }
        });
        updateRegCount();
    }

    function updateRegCount() {
        const count = document.querySelectorAll('.reg-check:checked').length;

        // Update new toolbar counter
        const elNew = document.getElementById('regSelectedCount');
        if (elNew) elNew.innerText = count;

        // Update old footer counter
        const elOld = document.getElementById('regCount');
        if (elOld) elOld.innerText = count;
    }

    function updateBulkUI() {
        const count = document.querySelectorAll('.charge-check:checked').length;
        const btn = document.getElementById('btnBulkDelete');
        document.getElementById('selectedCount').innerText = count;
        if (count > 0) btn.classList.remove('hidden');
        else btn.classList.add('hidden');
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').classList.add('hidden');
        pendingCancelIds = [];
    }

    function confirmCancel(btnElement) {
        const reason = document.getElementById('cancelReason').value.trim();

        if (!reason) {
            alert('Es obligatorio describir el motivo de la cancelación.');
            document.getElementById('cancelReason').focus();
            return;
        }

        // Disable button
        const btn = btnElement || document.querySelector('#cancelModal button.bg-rose-600');
        const originalText = btn ? btn.innerText : 'Eliminar';
        if (btn) {
            btn.innerText = "Cancelando...";
            btn.disabled = true;
        }

        const formData = new FormData();

        if (pendingCancelIds.length > 1) {
            formData.append('action', 'delete_charges_bulk');
            formData.append('ids', JSON.stringify(pendingCancelIds));
        } else {
            formData.append('action', 'delete_charge');
            formData.append('charge_id', pendingCancelIds[0]);
        }
        formData.append('reason', reason);

        fetch('../../back/admin_actions_finanzas.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text()) // Get raw text first
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error del servidor: ' + data.message);
                        if (btn) { btn.innerText = originalText; btn.disabled = false; }
                    }
                } catch (e) {
                    console.error('Server response:', text);
                    alert('Error inesperado (PHP): ' + text);
                    if (btn) { btn.innerText = originalText; btn.disabled = false; }
                }
            })
            .catch(err => {
                alert('Error de conexión: ' + err);
                if (btn) { btn.innerText = originalText; btn.disabled = false; }
            });
    }

    // LISTENER FOR TAB PARAM
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        let tab = urlParams.get('tab');
        const validTabs = ['config', 'cobranza', 'historico', 'eventos'];

        if (!tab || !validTabs.includes(tab)) {
            tab = 'config';
        }

        switchTab(tab);
    });

</script>

<style>
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>