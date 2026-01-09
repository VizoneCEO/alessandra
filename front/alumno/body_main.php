<?php
// --- LOGICA DE FINANZAS (WIDGET) ---
$user_id = $_SESSION['user_id'];
$deuda = 0;
$statusText = 'Al corriente';
$statusColor = 'text-emerald-400';
$statusIcon = 'fa-check-circle';

// Query Real
$sqlFin = "SELECT 
            SUM(total) as total_deuda, 
            COUNT(CASE WHEN estado = 'Vencido' THEN 1 END) as num_vencidos
           FROM finanzas_cargos 
           WHERE alumno_id = ? AND estado IN ('Pago Pendiente', 'Vencido')";

if ($stmt = $conn->prepare($sqlFin)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($total_deuda, $num_vencidos_db);

    if ($stmt->fetch()) {
        $deuda = $total_deuda ?? 0;
        $num_vencidos = $num_vencidos_db ?? 0;

        if ($deuda > 0) {
            if ($num_vencidos > 0) {
                $statusText = 'Pagos Vencidos';
                $statusColor = 'text-rose-400 animate-pulse';
                $statusIcon = 'fa-exclamation-triangle';
            } else {
                $statusText = 'Pago Pendiente';
                $statusColor = 'text-amber-400';
                $statusIcon = 'fa-clock';
            }
        }
    }
    $stmt->close();
}
?>

<!-- Welcome Section -->
<div class="mb-10">
    <div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-8 lg:p-12 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-8 opacity-5">
            <i class="fas fa-quote-right text-9xl"></i>
        </div>

        <h1 class="font-serif text-4xl lg:text-5xl text-slate-900 mb-4">
            Hola de nuevo, <span
                class="italic text-amber-600"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </h1>
        <p class="text-slate-500 font-light text-lg">Bienvenido a tu panel de control académico.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:pb-0">

    <!-- Notifications Section -->
    <div>
        <h4 class="text-sm uppercase tracking-widest text-slate-400 font-bold mb-4 flex items-center">
            <i class="fas fa-bell mr-2"></i> Notificaciones
        </h4>

        <?php
        // --- LOGIC: Check Documents ---
        $doc_alerts = [];

        // 1. Get Uploaded Docs Status
        $sql_notif_docs = "SELECT tipo_documento, estado, mensaje_rechazo FROM Documentos_Alumno WHERE alumno_id = $user_id";
        $res_notif_docs = $conn->query($sql_notif_docs);
        $uploaded_map = [];
        while ($row = $res_notif_docs->fetch_assoc()) {
            $uploaded_map[$row['tipo_documento']] = $row;
        }

        // 2. Define Required
        $req_docs_check = [
            'Acta de Nacimiento',
            'CURP',
            'Identificación Oficial',
            'Certificado de Bachillerato',
            'Comprobante de Domicilio'
        ];

        // 3. Analyze Status
        $rejected_list = [];
        $missing_count = 0;

        foreach ($req_docs_check as $docName) {
            if (isset($uploaded_map[$docName])) {
                if ($uploaded_map[$docName]['estado'] === 'Rechazado') {
                    $rejected_list[] = $docName;
                }
            } else {
                $missing_count++;
            }
        }

        // 4. GENERATE NOTIFICATION CARDS
        $has_notifications = false;

        // A) REJECTED DOCS (Critical)
        if (!empty($rejected_list)):
            $has_notifications = true;
            $countRej = count($rejected_list);
            $docText = $countRej === 1 ? $rejected_list[0] : "$countRej documentos";
            ?>
            <div
                class="bg-white border-l-4 border-rose-500 shadow-sm rounded-r-lg p-6 flex items-start group hover:shadow-md transition-shadow mb-4">
                <div class="flex-shrink-0 mr-4">
                    <div
                        class="h-10 w-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-500 animate-pulse">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div>
                    <p class="text-slate-800 font-bold mb-1">Documentación Rechazada</p>
                    <p class="text-slate-500 text-sm font-light mb-3">
                        La administración ha rechazado: <strong class="text-rose-600"><?php echo $docText; ?></strong>.
                        Por favor revisa el motivo y sube el archivo nuevamente.
                    </p>
                    <a href="dashboard.php?view=documentos"
                        class="inline-flex items-center text-xs font-bold text-rose-600 uppercase tracking-wider hover:text-rose-800 transition-colors">
                        Ir a corregir <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // B) MISSING DOCS (Warning)
        if ($missing_count > 0):
            $has_notifications = true;
            ?>
            <div
                class="bg-white border-l-4 border-amber-400 shadow-sm rounded-r-lg p-6 flex items-start group hover:shadow-md transition-shadow mb-4">
                <div class="flex-shrink-0 mr-4">
                    <div class="h-10 w-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-500">
                        <i class="fas fa-folder-open"></i>
                    </div>
                </div>
                <div>
                    <p class="text-slate-800 font-bold mb-1">Expediente Incompleto</p>
                    <p class="text-slate-500 text-sm font-light mb-3">
                        Tienes <strong class="text-amber-600"><?php echo $missing_count; ?> documentos</strong> pendientes
                        de cargar.
                        Completa tu expediente lo antes posible.
                    </p>
                    <a href="dashboard.php?view=documentos"
                        class="inline-flex items-center text-xs font-bold text-amber-600 uppercase tracking-wider hover:text-amber-800 transition-colors">
                        Subir documentos <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // C) DEFAULT (Clean)
        if (!$has_notifications):
            ?>
            <div
                class="bg-white border-l-4 border-emerald-400 shadow-sm rounded-r-lg p-6 flex items-start group hover:shadow-md transition-shadow">
                <div class="flex-shrink-0 mr-4">
                    <div class="h-10 w-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <p class="text-slate-800 font-medium mb-1">¡Todo al día!</p>
                    <p class="text-slate-500 text-sm font-light">
                        No tienes alertas pendientes. Tu expediente y pagos están en orden.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Finance Widget (REAL DATA) -->
    <div>
        <h4 class="text-sm uppercase tracking-widest text-slate-400 font-bold mb-4 flex items-center">
            <i class="fas fa-wallet mr-2"></i> Finanzas
        </h4>

        <div class="bg-zinc-950 rounded-xl shadow-lg p-6 text-white relative overflow-hidden group">
            <!-- decorative circles -->
            <div
                class="absolute -top-10 -right-10 w-40 h-40 bg-zinc-800 rounded-full opacity-50 blur-2xl group-hover:scale-110 transition-transform duration-700">
            </div>

            <div class="relative z-10">
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <p class="text-zinc-400 text-xs uppercase tracking-wider mb-1">Estado de Cuenta</p>
                        <h3 class="text-4xl font-serif text-white mb-2">
                            $<?php echo number_format($deuda, 2); ?> <span
                                class="text-sm text-zinc-500 font-sans font-normal">MXN</span>
                        </h3>
                        <p
                            class="<?php echo $statusColor; ?> text-xs mt-1 flex items-center font-bold uppercase tracking-wide">
                            <i class="fas <?php echo $statusIcon; ?> mr-2"></i> <?php echo $statusText; ?>
                        </p>
                    </div>
                    <div
                        class="h-12 w-12 border border-zinc-800 bg-zinc-900 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-receipt text-zinc-400"></i>
                    </div>
                </div>

                <a href="dashboard.php?view=finanzas"
                    class="block w-full text-center py-3 bg-white text-zinc-950 text-xs font-bold uppercase tracking-widest hover:bg-zinc-200 transition-colors rounded shadow-lg hover:shadow-white/10">
                    Ver Detalles y Pagar
                </a>
            </div>
        </div>
    </div>

</div>