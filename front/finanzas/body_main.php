<?php
// Include DB connection safely
require_once __DIR__ . '/../../back/db_connect.php';

// 1. Total Alumnos Activos
$sql_users = "SELECT COUNT(*) as total FROM Usuarios WHERE perfil_id = 3"; // AND is_active = 1 ideally
$res_users = $conn->query($sql_users);
$total_alumnos = $res_users->fetch_assoc()['total'];

// 2. Ingresos del Mes (y Filtros para Gráfica)
$filter_start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$filter_end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');

// Ingresos Totales en Rango
$sql_income = "SELECT SUM(total) as total_ingreso 
               FROM finanzas_cargos 
               WHERE estado = 'Pagado' 
               AND fecha_pago BETWEEN '$filter_start 00:00:00' AND '$filter_end 23:59:59'";
$res_income = $conn->query($sql_income);
$ingresos_rango = $res_income->fetch_assoc()['total_ingreso'] ?? 0;

// Gráfica: Ingresos por Día
$sql_chart = "SELECT DATE(fecha_pago) as dia, SUM(total) as monto 
              FROM finanzas_cargos 
              WHERE estado = 'Pagado' 
              AND fecha_pago BETWEEN '$filter_start 00:00:00' AND '$filter_end 23:59:59'
              GROUP BY DATE(fecha_pago) ORDER BY dia ASC";
$res_chart = $conn->query($sql_chart);
$chart_labels = [];
$chart_data = [];
while ($row = $res_chart->fetch_assoc()) {
    $chart_labels[] = date('d M', strtotime($row['dia']));
    $chart_data[] = $row['monto'];
}

// Tabla: Ingresos por Concepto (Tag)
$sql_tags = "SELECT concepto, SUM(total) as monto, COUNT(*) as qty 
             FROM finanzas_cargos 
             WHERE estado = 'Pagado' 
             AND fecha_pago BETWEEN '$filter_start 00:00:00' AND '$filter_end 23:59:59'
             GROUP BY concepto ORDER BY monto DESC LIMIT 10";
$res_tags = $conn->query($sql_tags);
$tags_data = [];
while ($row = $res_tags->fetch_assoc()) {
    $tags_data[] = $row;
}


// 3. Cartera Vencida y Pagos Pendientes (Montos)
$sql_debt = "SELECT 
                SUM(CASE WHEN estado = 'Vencido' THEN total ELSE 0 END) as total_vencido,
                SUM(CASE WHEN estado = 'Pago Pendiente' THEN total ELSE 0 END) as total_pendiente
             FROM finanzas_cargos";
$res_debt = $conn->query($sql_debt);
$row_debt = $res_debt->fetch_assoc();

$monto_vencido = $row_debt['total_vencido'] ?? 0;
$monto_pendiente = $row_debt['total_pendiente'] ?? 0;

// Trends (Static for UI polish)
$trend_alumnos = "";
$trend_ingresos = "";

// 4. Desempeño por Cuenta (KPIs Solicitados)
$accounts_stats = [];
$sql_accounts = "SELECT id, banco, titular FROM Finanzas_Cuentas";
$res_acc = $conn->query($sql_accounts);

if ($res_acc) {
    while ($acc = $res_acc->fetch_assoc()) {
        // Alumnos Asignados
        $sql_s = "SELECT COUNT(*) as c FROM Usuarios WHERE cuenta_deposito_id = " . $acc['id'];
        $students = $conn->query($sql_s)->fetch_assoc()['c'];

        // Dinero Recolectado (Mes Actual)
        // Usamos monto_original + recargos ya que monto_pagado puede ser 0 si se validó administrativamente sin flujo de caja explícito
        $sql_i = "SELECT SUM(monto_original + recargos) as t FROM finanzas_cargos 
                  WHERE cuenta_receptora_id = " . $acc['id'] . " 
                  AND estado = 'Pagado' 
                  AND MONTH(fecha_pago) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha_pago) = YEAR(CURRENT_DATE())";
        $income = $conn->query($sql_i)->fetch_assoc()['t'] ?? 0;

        $accounts_stats[] = [
            'banco' => $acc['banco'],
            'titular' => $acc['titular'],
            'students' => $students,
            'income' => $income
        ];
    }
}
?>

<div class="mb-10">
    <h2 class="font-serif text-4xl text-zinc-900 mb-2">Resumen Financiero</h2>
    <p class="text-zinc-500 font-light text-sm">Estado financiero de la institución.</p>
</div>

<!-- SECTION: Desempeño por Cuenta (New Request) -->
<h3 class="font-bold text-zinc-500 text-xs uppercase tracking-widest mb-4 border-b border-zinc-100 pb-2">
    <i class="fas fa-university mr-2"></i> Balance por Cuenta (Mes Actual)
</h3>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
    <?php foreach ($accounts_stats as $acc): ?>
        <div
            class="bg-white p-6 rounded-xl border border-zinc-200 shadow-sm relative overflow-hidden group hover:border-zinc-900 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h5 class="font-bold text-zinc-900"><?php echo htmlspecialchars($acc['banco']); ?></h5>
                    <p class="text-[10px] text-zinc-400 uppercase tracking-widest">
                        <?php echo htmlspecialchars($acc['titular']); ?>
                    </p>
                </div>
                <div class="bg-zinc-100 p-2 rounded-full text-zinc-600">
                    <i class="fas fa-wallet text-sm"></i>
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <p class="text-xs text-zinc-400 font-light mb-1">Recaudado este mes</p>
                    <p class="font-serif text-2xl font-bold text-emerald-600">
                        $<?php echo number_format($acc['income'], 2); ?>
                    </p>
                </div>
                <div class="pt-3 border-t border-zinc-50 flex items-center justify-between">
                    <span class="text-xs text-zinc-500">Alumnos Asignados</span>
                    <span class="bg-zinc-900 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                        <?php echo $acc['students']; ?> Alumnos
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- KPI Review Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">

    <!-- KPI 1: Alumnos Activos (Read Only) -->
    <div
        class="bg-white p-6 rounded-xl border border-zinc-100 shadow-sm transition-all duration-300 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="fas fa-users text-8xl text-zinc-900"></i>
        </div>
        <h6 class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-4">Total Alumnos Activos</h6>
        <div class="flex items-baseline mb-2">
            <span class="font-serif text-4xl font-bold text-zinc-900"><?php echo $total_alumnos; ?></span>
        </div>
    </div>

    <!-- KPI 2: Ingresos del Periodo -->
    <div
        class="bg-white p-6 rounded-xl border border-zinc-100 shadow-sm relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="fas fa-chart-line text-8xl text-emerald-900"></i>
        </div>
        <h6 class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-4">Ingresos (Rango)</h6>
        <div class="flex items-baseline mb-2">
            <span
                class="font-serif text-4xl font-bold text-zinc-900">$<?php echo number_format($ingresos_rango); ?></span>
        </div>
        <p class="text-[10px] text-zinc-400 font-light">En fechas seleccionadas</p>
    </div>

    <!-- KPI 3: Cartera Vencida -->
    <div
        class="bg-white p-6 rounded-xl border border-zinc-100 shadow-sm relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="fas fa-exclamation-circle text-8xl text-rose-900"></i>
        </div>
        <h6 class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-4">Cartera Vencida</h6>
        <div class="flex items-baseline mb-2">
            <span
                class="font-serif text-4xl font-bold text-rose-700">$<?php echo number_format($monto_vencido, 2); ?></span>
        </div>
        <p class="text-[10px] text-zinc-400 font-light">Deuda Vencida Total</p>
    </div>

    <!-- KPI 4: Pagos Pendientes (Future Income) -->
    <div
        class="bg-white p-6 rounded-xl border border-zinc-100 shadow-sm relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="fas fa-clock text-8xl text-amber-900"></i>
        </div>
        <h6 class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-4">Por Cobrar</h6>
        <div class="flex items-baseline mb-2">
            <span
                class="font-serif text-4xl font-bold text-amber-600">$<?php echo number_format($monto_pendiente, 2); ?></span>
        </div>
        <p class="text-[10px] text-zinc-400 font-light">Pagos Pendientes (No Vencidos)</p>
    </div>
</div>

<!-- Income Analysis Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
    <!-- Chart Section -->
    <div class="lg:col-span-2 bg-white p-8 rounded-xl border border-zinc-100 shadow-sm">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
            <h3 class="font-serif text-xl text-zinc-900 mb-4 md:mb-0">Análisis de Ingresos</h3>

            <!-- Date Filter Form -->
            <form method="GET" action="dashboard.php"
                class="flex gap-2 items-center bg-zinc-50 p-1 rounded-lg border border-zinc-200">
                <input type="hidden" name="page" value="main">
                <input type="date" name="start" value="<?php echo $filter_start; ?>"
                    class="bg-transparent border-none text-xs text-zinc-600 focus:ring-0">
                <span class="text-zinc-300">-</span>
                <input type="date" name="end" value="<?php echo $filter_end; ?>"
                    class="bg-transparent border-none text-xs text-zinc-600 focus:ring-0">
                <button type="submit"
                    class="px-3 py-1 bg-zinc-900 text-white text-xs rounded hover:bg-zinc-800 transition-colors">
                    <i class="fas fa-filter"></i>
                </button>
            </form>
        </div>
        <div class="relative h-72 w-full">
            <canvas id="incomeChart"></canvas>
        </div>
    </div>

    <!-- Tags Table -->
    <div class="bg-white p-8 rounded-xl border border-zinc-100 shadow-sm">
        <h3 class="font-serif text-xl text-zinc-900 mb-6">Desglose por Concepto</h3>
        <div class="overflow-y-auto max-h-72 pr-2">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-zinc-400 border-b border-zinc-100">
                    <tr>
                        <th class="pb-2 text-left">Concepto</th>
                        <th class="pb-2 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50">
                    <?php if (empty($tags_data)): ?>
                        <tr>
                            <td colspan="2" class="py-4 text-center text-zinc-400 text-xs">Sin datos en este rango</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tags_data as $tag): ?>
                            <tr>
                                <td class="py-3 text-zinc-700">
                                    <span class="block font-medium truncate max-w-[150px]"
                                        title="<?php echo htmlspecialchars($tag['concepto']); ?>">
                                        <?php echo htmlspecialchars($tag['concepto']); ?>
                                    </span>
                                    <span class="text-[10px] text-zinc-400"><?php echo $tag['qty']; ?> pagos</span>
                                </td>
                                <td class="py-3 text-right font-mono text-emerald-600 font-bold">
                                    $<?php echo number_format($tag['monto'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart JS Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('incomeChart').getContext('2d');
    const incomeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Ingresos ($)',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: '#10b981', // Emerald 500
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2, 4], color: '#f4f4f5' },
                    ticks: { callback: function (value) { return '$' + value; }, font : { size : 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
</script>

<!-- Quick Actions - Simplified for Finance -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <a href="dashboard.php?page=finanzas"
        class="group flex items-center p-6 bg-zinc-50 border border-zinc-200 rounded-lg hover:bg-zinc-900 hover:border-zinc-900 transition-all duration-300">
        <div
            class="h-12 w-12 rounded-full bg-white border border-zinc-200 flex items-center justify-center text-zinc-900 group-hover:bg-zinc-800 group-hover:border-zinc-700 group-hover:text-white transition-colors">
            <i class="fas fa-wallet text-xl"></i>
        </div>
        <div class="ml-6">
            <h4 class="font-serif text-xl text-zinc-900 group-hover:text-white mb-1 transition-colors">Cobranza y Ajustes</h4>
            <p class="text-xs text-zinc-500 group-hover:text-zinc-400 font-light">Gestionar pagos, becas y adeudos.</p>
        </div>
        <div class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity transform group-hover:translate-x-2">
            <i class="fas fa-arrow-right text-white"></i>
        </div>
    </a>
</div>
