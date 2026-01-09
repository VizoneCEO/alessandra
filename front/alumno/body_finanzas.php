<?php
require_once __DIR__ . '/../../back/db_connect.php';

$user_id = $_SESSION['user_id'];
$cargos = [];
$saldo_total = 0;
$tiene_vencidos = false;

// Fetch Charges
$sql = "SELECT * FROM finanzas_cargos WHERE alumno_id = ? ORDER BY fecha_vencimiento DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$today = date('Y-m-d');

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $is_overdue = ($today > $row['fecha_vencimiento']) && ($row['estado'] !== 'Pagado') && ($row['estado'] !== 'Al corriente');

        // Partial Payment Logic
        $row['pagado'] = floatval($row['monto_pagado'] ?? 0);
        $row['total'] = floatval($row['total']);
        $row['saldo'] = $row['total'] - $row['pagado'];
        if ($row['saldo'] < 0)
            $row['saldo'] = 0;

        // Visual Override
        if ($is_overdue && $row['estado'] !== 'Pagado' && $row['saldo'] > 0) {
            $visual_status = 'Vencido';
        } else {
            $visual_status = $row['estado'];
        }

        $row['visual_status'] = $visual_status;

        // Sum Debt (Remaining Balance)
        if ($row['estado'] !== 'Pagado' && $row['estado'] !== 'Cancelado' && $row['estado'] !== 'Al corriente') {
            $saldo_total += $row['saldo'];
            if ($visual_status === 'Vencido') {
                $tiene_vencidos = true;
            }
        }

        $cargos[] = $row;
    }
}

// Handle Payment Proof Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_proof') {
    $charge_id = intval($_POST['charge_id']);

    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/comprobantes/';

        // Ensure directory exists
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                $error = error_get_last();
                echo "<script>alert('Error: No se pudo crear el directorio de carga. " . addslashes($error['message']) . "');</script>";
                exit;
            }
            chmod($uploadDir, 0777);
        }

        $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
        $filename = 'comprobante_' . $charge_id . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644); // Ensure readable
            $dbPath = '../../uploads/comprobantes/' . $filename;
            $stmt = $conn->prepare("UPDATE finanzas_cargos SET comprobante_url = ?, metodo_pago = 'Transferencia (Pendiente)', fecha_pago = NOW() WHERE id = ? AND alumno_id = ?");
            $stmt->bind_param("sii", $dbPath, $charge_id, $user_id);
            if ($stmt->execute()) {
                echo "<script>alert('Comprobante subido correctamente. En espera de validación.'); window.location.href = window.location.href;</script>";
            } else {
                echo "<script>alert('Error al guardar en base de datos.');</script>";
            }
        } else {
            $error = error_get_last();
            echo "<script>alert('Error al mover el archivo al servidor. Detalles: " . addslashes($error['message']) . "');</script>";
        }
    } else {
        echo "<script>alert('Error en la subida: Código " . $_FILES['comprobante']['error'] . "');</script>";
    }
}
?>

<!-- Hero Section: Widget de Estado -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-8 mb-10 relative overflow-hidden">
    <div class="flex flex-col md:flex-row justify-between items-end md:items-center relative z-10">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-zinc-400 font-bold mb-2">Saldo Total a Pagar</p>
            <h2 class="text-5xl font-serif text-zinc-900 mb-4">
                $<?php echo number_format($saldo_total, 2); ?>
            </h2>

            <?php if ($tiene_vencidos): ?>
                <div
                    class="inline-flex items-center px-3 py-1 bg-rose-50 border border-rose-100 rounded text-rose-700 text-xs font-bold uppercase tracking-wide animate-pulse">
                    <i class="fas fa-exclamation-circle mr-2"></i> Requiere Atención Inmediata
                </div>
            <?php elseif ($saldo_total > 0): ?>
                <div
                    class="inline-flex items-center px-3 py-1 bg-amber-50 border border-amber-100 rounded text-amber-600 text-xs font-bold uppercase tracking-wide">
                    <i class="fas fa-clock mr-2"></i> Pago Pendiente
                </div>
            <?php else: ?>
                <div
                    class="inline-flex items-center px-3 py-1 bg-emerald-50 border border-emerald-100 rounded text-emerald-700 text-xs font-bold uppercase tracking-wide">
                    <i class="fas fa-check-circle mr-2"></i> Al Corriente
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 md:mt-0 w-full md:w-auto">
            <button onclick="document.querySelector('table').scrollIntoView({behavior: 'smooth'})"
                class="w-full md:w-auto px-8 py-4 bg-zinc-950 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-all rounded shadow-lg flex items-center justify-center">
                <i class="fas fa-chevron-down mr-3"></i> Ver Cargos
            </button>
        </div>
    </div>

    <!-- Decorative -->
    <div
        class="absolute -bottom-20 -right-20 w-64 h-64 bg-zinc-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70">
    </div>
</div>

<!-- Historial de Transacciones -->
<!-- Historial de Transacciones -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
    <!-- Custom Style for Slide Animation -->
    <style>
        @keyframes slide-right {

            0%,
            100% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(5px);
            }
        }

        .animate-slide-hint {
            animation: slide-right 1.5s ease-in-out infinite;
        }
    </style>

    <div class="px-6 py-4 border-b border-zinc-50 bg-zinc-50/30 flex justify-between items-center">
        <h5 class="text-xs font-bold uppercase tracking-widest text-zinc-500">Movimientos</h5>
        <!-- Mobile Scroll Hint -->
        <div class="lg:hidden flex items-center text-zinc-400 animate-slide-hint">
            <span class="text-[9px] mr-2 uppercase tracking-wider font-bold">Desliza</span>
            <i class="fas fa-arrow-right text-xs"></i>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-zinc-100 text-xs uppercase tracking-wider text-zinc-400 font-light">
                    <th class="px-6 py-4 font-medium">Concepto</th>
                    <th class="px-6 py-4 font-medium">Vencimiento</th>
                    <th class="px-6 py-4 font-medium">Monto</th>
                    <th class="px-6 py-4 font-medium">Estado</th>
                    <th class="px-6 py-4 font-medium text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-50">
                <?php if (empty($cargos)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-zinc-400 text-xs uppercase tracking-widest">No
                            hay movimientos registrados</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cargos as $t):
                        $rowClass = "hover:bg-zinc-50/50 transition-colors group";
                        if ($t['visual_status'] === 'Pago Pendiente') {
                            $rowClass = "bg-emerald-100/30 hover:bg-emerald-100/50 transition-colors group";
                        }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="px-6 py-4 font-medium text-zinc-700">
                                <?php echo htmlspecialchars($t['concepto']); ?>
                            </td>
                            <td class="px-6 py-4 text-zinc-500 font-light">
                                <?php echo date('d M, Y', strtotime($t['fecha_vencimiento'])); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-zinc-600">
                                <!-- Show Total and Remaining if partial -->
                                <?php if ($t['pagado'] > 0 && $t['saldo'] > 0): ?>
                                    <div class="flex flex-col">
                                        <span>$<?php echo number_format($t['total'], 2); ?></span>
                                        <span class="text-[10px] text-rose-500 font-bold">Resta:
                                            $<?php echo number_format($t['saldo'], 2); ?></span>
                                    </div>
                                <?php else: ?>
                                    $<?php echo number_format($t['total'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $status = $t['visual_status'];
                                if ($status === 'Vencido'): ?>
                                    <span
                                        class="bg-rose-50 text-rose-600 px-2 py-1 rounded text-[10px] uppercase font-bold tracking-wider">Vencido</span>
                                <?php elseif ($status === 'Pago Pendiente'): ?>
                                    <span
                                        class="bg-amber-50 text-amber-600 px-2 py-1 rounded text-[10px] uppercase font-bold tracking-wider">Por
                                        Pagar</span>
                                <?php elseif ($status === 'Parcialmente Pagado'): ?>
                                    <span
                                        class="bg-blue-50 text-blue-600 px-2 py-1 rounded text-[10px] uppercase font-bold tracking-wider">Parcial</span>
                                <?php elseif ($status === 'Pagado' || $status === 'Al corriente'): ?>
                                    <span
                                        class="text-zinc-400 px-2 py-1 text-[10px] uppercase font-bold tracking-wider flex items-center">
                                        <i class="fas fa-check mr-1 text-emerald-500"></i> Pagado
                                    </span>
                                <?php elseif ($status === 'Cancelado'): ?>
                                    <span
                                        class="text-zinc-300 px-2 py-1 text-[10px] uppercase font-bold tracking-wider">Cancelado</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                                <!-- Receipt Link (Opens Modal) -->
                                <?php if (!empty($t['comprobante_url'])): ?>
                                    <button
                                        onclick="openReceiptModal('<?php echo $t['comprobante_url']; ?>', '<?php echo $t['metodo_pago'] ?? ''; ?>', '<?php echo $t['referencia_pago'] ?? ''; ?>', '<?php echo $t['fecha_pago'] ?? ''; ?>', '<?php echo $t['monto_pagado'] ?? 0; ?>')"
                                        class="text-zinc-400 hover:text-zinc-800 transition-colors" title="Ver Comprobante">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
                                <?php elseif ($t['pagado'] > 0): ?>
                                    <!-- Fallback Receipt (Digital Receipt) -->
                                    <button
                                        onclick="openReceiptModal('', '<?php echo $t['metodo_pago'] ?? 'Parcial'; ?>', '<?php echo $t['referencia_pago'] ?? 'Múltiple'; ?>', '<?php echo $t['fecha_pago'] ?? 'Varios'; ?>', '<?php echo $t['monto_pagado'] ?? 0; ?>')"
                                        class="text-zinc-300 hover:text-zinc-600 transition-colors" title="Ver Recibo Digital">
                                        <i class="fas fa-receipt"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Pay Button (If has balance and active) -->
                                <?php if (($status !== 'Pagado' && $status !== 'Al corriente' && $status !== 'Cancelado') && $t['saldo'] > 0): ?>
                                    <?php if (!empty($t['comprobante_url']) && strpos($t['metodo_pago'] ?? '', 'Pendiente') !== false): ?>
                                        <span class="text-[10px] text-zinc-400 italic">Verificando...</span>
                                    <?php else: ?>
                                        <button
                                            onclick="openPaymentModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['concepto']); ?>', <?php echo $t['saldo']; ?>)"
                                            class="<?php echo ($status === 'Vencido') ? 'text-rose-600 hover:text-rose-800' : 'text-amber-600 hover:text-amber-800'; ?> font-bold text-xs uppercase underline decoration-2 underline-offset-4 transition-colors">
                                            Pagar
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- History Button -->
                                <button
                                    onclick="openHistoryModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['concepto']); ?>')"
                                    class="text-zinc-400 hover:text-zinc-600 transition-colors ml-2" title="Ver Historial">
                                    <i class="fas fa-history"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de Historial -->
<div id="historyModal" class="fixed inset-0 z-[100] hidden">
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm" onclick="closeHistoryModal()"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-serif text-lg font-bold text-zinc-900" id="histTitle">Historial</h3>
                    <button onclick="closeHistoryModal()" class="text-zinc-400 hover:text-rose-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div id="histContent" class="space-y-4 max-h-[60vh] overflow-y-auto">
                    <!-- Content loaded via JS -->
                    <p class="text-center text-zinc-400 py-8">Cargando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script for History -->
<script>
    function openHistoryModal(id, studentName) {
        document.getElementById('histTitle').innerText = 'Historial: ' + studentName; // In admin it's studentName, here we can use concept or keep variable name logic

        const content = document.getElementById('histContent');
        content.innerHTML = '<p class="text-center text-zinc-400 py-8">Cargando...</p>';
        document.getElementById('historyModal').classList.remove('hidden');

        const formData = new FormData();
        formData.append('action', 'fetch_history');
        formData.append('charge_id', id);

        fetch('../../back/admin_actions_finanzas.php?t=' + Date.now(), {
            method: 'POST',
            body: formData
        })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        alert("Server Error (Not JSON): " + text.substring(0, 200));
                        throw new Error('Server returned non-JSON');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    let html = '';

                    // Payments
                    if (data.data.payments && data.data.payments.length > 0) {
                        html += '<h4 class="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-2 border-b border-emerald-100 pb-1">Pagos Registrados</h4>';
                        data.data.payments.forEach(p => {
                            html += `
                            <div class="flex justify-between items-center text-sm p-2 bg-emerald-50/50 rounded mb-1">
                                <div>
                                    <div class="font-bold text-zinc-700">$${parseFloat(p.monto).toFixed(2)}</div>
                                    <div class="text-xs text-zinc-400">${p.metodo_pago}</div>
                                </div>
                                <div class="text-right">
                                        <div class="text-xs text-zinc-500">${p.fecha_pago}</div>
                                        <div class="text-[10px] text-zinc-400 italic">${p.referencia || ''}</div>
                                </div>
                            </div>
                        `;
                        });
                        html += '<div class="mb-4"></div>';
                    }

                    // Events
                    if (data.data.events && data.data.events.length > 0) {
                        html += '<h4 class="text-xs font-bold uppercase tracking-widest text-zinc-500 mb-2 border-b border-zinc-100 pb-1">Bitácora de Eventos</h4>';
                        data.data.events.forEach(e => {
                            html += `
                                <div class="text-xs p-2 hover:bg-zinc-50 rounded">
                                    <div class="flex justify-between mb-1">
                                        <span class="font-bold text-zinc-700">${e.tipo_evento}</span>
                                        <span class="text-zinc-400 text-[10px]">${e.fecha_evento}</span>
                                    </div>
                                    <div class="text-zinc-600">${e.descripcion}</div>
                                </div>
                             `;
                        });
                    }

                    if (!html) html = '<p class="text-center text-zinc-400 py-4">No hay registros.</p>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p class="text-center text-rose-500">Error: ' + (data.message || 'Error desconocido') + '</p>';
                }
            })
            .catch(err => {
                content.innerHTML = '<p class="text-center text-rose-500">Error de conexión: ' + err.message + '</p>';
            });
    }

    function closeHistoryModal() {
        document.getElementById('historyModal').classList.add('hidden');
    }
</script>

<!-- Modal de Recibo -->
<div id="receiptModal" class="fixed inset-0 z-[100] hidden">
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm" onclick="closeReceiptModal()"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div
                class="relative w-full max-w-2xl bg-white rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 bg-zinc-50 border-b border-zinc-100 flex justify-between items-center">
                    <div>
                        <h3 class="font-serif text-lg font-bold text-zinc-900">Comprobante de Pago</h3>
                        <p class="text-xs text-zinc-400 font-light" id="receiptMeta">...</p>
                    </div>
                    <button onclick="closeReceiptModal()" class="text-zinc-400 hover:text-rose-500 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto bg-zinc-100/50 p-6 flex items-center justify-center min-h-[300px]">

                    <div id="receiptLoader" class="hidden text-zinc-400 flex flex-col items-center">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <span class="text-xs">Cargando...</span>
                    </div>

                    <!-- Image View -->
                    <div id="receiptImageContainer" class="hidden relative group max-w-full">
                        <img id="receiptImage" src="" alt="Comprobante"
                            class="max-w-full rounded shadow-sm max-h-[60vh] object-contain">
                        <a id="receiptDownload" href="" download
                            class="absolute top-2 right-2 bg-white/90 text-zinc-800 p-2 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-opacity hover:scale-110">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>

                    <!-- PDF View -->
                    <iframe id="pdfView" src="" class="hidden w-full h-[60vh] rounded border border-zinc-200"></iframe>

                    <!-- No Receipt / Digital Receipt -->
                    <div id="noReceiptMsg" class="hidden text-center max-w-md">
                        <div
                            class="w-16 h-16 bg-zinc-100 rounded-full flex items-center justify-center mx-auto mb-4 text-zinc-300">
                            <i class="fas fa-file-invoice-dollar text-2xl"></i>
                        </div>
                        <h4 class="font-bold text-zinc-900 mb-2">Pago Registrado sin Comprobante Digital</h4>
                        <p class="text-sm text-zinc-500 mb-6">Este pago fue registrado manualmente (Efectivo/Terminal) o
                            no se adjuntó un archivo.</p>

                        <div class="bg-white p-4 rounded border border-zinc-200 text-left text-sm space-y-2 shadow-sm">
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Monto:</span>
                                <span class="font-bold text-zinc-900" id="recAmount">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Fecha:</span>
                                <span class="text-zinc-700" id="recDate">...</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Método:</span>
                                <span class="text-zinc-700" id="recMethod">...</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Referencia:</span>
                                <span class="text-zinc-700 font-mono text-xs" id="recRef">...</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openReceiptModal(url, method, ref, date, amount) {
        const modal = document.getElementById('receiptModal');

        const imgContainer = document.getElementById('receiptImageContainer');
        const img = document.getElementById('receiptImage');
        const downloadBtn = document.getElementById('receiptDownload');

        const pdfView = document.getElementById('pdfView');
        const noMsg = document.getElementById('noReceiptMsg');

        // Metadata
        document.getElementById('receiptMeta').innerText = `${method} - ${date}`;

        // Reset Views
        imgContainer.classList.add('hidden');
        pdfView.classList.add('hidden');
        noMsg.classList.add('hidden');

        // Handle 'null' string potentially sent by PHP
        if (url && url !== 'null') {
            const ext = url.split('.').pop().toLowerCase();
            if (ext === 'pdf') {
                pdfView.src = url;
                pdfView.classList.remove('hidden');
            } else {
                img.src = url;
                downloadBtn.href = url;
                imgContainer.classList.remove('hidden');
            }
        } else {
            // Show "Digital Receipt" Details
            document.getElementById('recAmount').innerText = '$' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('recDate').innerText = date;
            document.getElementById('recMethod').innerText = method;
            document.getElementById('recRef').innerText = ref || 'N/A';
            noMsg.classList.remove('hidden');
        }

        modal.classList.remove('hidden');
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
        document.getElementById('pdfView').src = ''; // Stop PDF
    }
</script>

<!-- Modal de Pago -->
<div id="paymentModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <!-- Overlay -->
    <div class="fixed inset-0 bg-zinc-900/80 backdrop-blur-sm transition-opacity opacity-0 transition-opacity-300"
        id="paymentOverlay"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg scale-95 opacity-0 transition-transform-300"
                id="paymentPanel">

                <div class="bg-white px-8 py-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-serif text-xl font-bold text-zinc-900" id="modal-title">Realizar
                            Pago</h3>
                        <button onclick="closePaymentModal()" class="text-zinc-400 hover:text-rose-500"><i
                                class="fas fa-times"></i></button>
                    </div>

                    <div class="mb-6 bg-zinc-50 p-4 rounded border border-zinc-100">
                        <p class="text-[10px] uppercase tracking-widest text-zinc-400 mb-1">Concepto a
                            Pagar</p>
                        <p class="font-bold text-zinc-800 text-sm mb-2" id="modalConcept">...</p>
                        <p class="text-2xl font-serif text-zinc-900 font-bold" id="modalAmount">$0.00
                        </p>
                    </div>

                    <!-- Tabs -->
                    <div class="flex border-b border-zinc-200 mb-6">
                        <button onclick="switchMethod('card')" id="tabCard"
                            class="flex-1 py-2 text-xs font-bold uppercase tracking-widest border-b-2 border-zinc-900 text-zinc-900">Tarjeta</button>
                        <button onclick="switchMethod('transfer')" id="tabTransfer"
                            class="flex-1 py-2 text-xs font-bold uppercase tracking-widest border-b-2 border-transparent text-zinc-400 hover:text-zinc-600">Transferencia</button>
                    </div>

                    <!-- Card Form -->
                    <div id="methodCard">
                        <form id="cardForm"
                            onsubmit="event.preventDefault(); alert('Simulación: Pago con tarjeta procesado.'); closePaymentModal();"
                            class="space-y-4">
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">Titular</label>
                                <input type="text"
                                    class="w-full border-b border-zinc-300 py-2 text-zinc-900 focus:border-zinc-900 outline-none text-sm placeholder-zinc-300"
                                    placeholder="NOMBRE EN TARJETA">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">Número
                                    de Tarjeta</label>
                                <input type="text"
                                    class="w-full border-b border-zinc-300 py-2 text-zinc-900 focus:border-zinc-900 outline-none text-sm placeholder-zinc-300"
                                    placeholder="0000 0000 0000 0000">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">Exp.</label>
                                    <input type="text"
                                        class="w-full border-b border-zinc-300 py-2 text-zinc-900 focus:border-zinc-900 outline-none text-sm placeholder-zinc-300"
                                        placeholder="MM/YY">
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">CVC</label>
                                    <input type="password"
                                        class="w-full border-b border-zinc-300 py-2 text-zinc-900 focus:border-zinc-900 outline-none text-sm placeholder-zinc-300"
                                        placeholder="123">
                                </div>
                            </div>
                            <button type="submit"
                                class="w-full bg-zinc-900 text-white py-3 rounded text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors mt-4">
                                Pagar Ahora
                            </button>
                        </form>
                    </div>

                    <!-- Transfer Form -->
                    <div id="methodTransfer" class="hidden">
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="upload_proof">
                            <input type="hidden" name="charge_id" id="inputChargeId">

                            <div class="bg-blue-50 p-4 rounded text-blue-800 text-xs mb-4">
                                <p class="font-bold mb-1">Datos Bancarios:</p>
                                <p>Banco: BBVA</p>
                                <p>Cuenta: 0123456789</p>
                                <p>CLABE: 012012012345678901</p>
                                <p>Concepto: <span id="refConcept">MATRICULA</span></p>
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-1">Subir
                                    Comprobante</label>
                                <input type="file" name="comprobante" required accept="image/*,.pdf"
                                    class="w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200">
                            </div>

                            <button type="submit"
                                class="w-full bg-zinc-900 text-white py-3 rounded text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-colors mt-4">
                                Enviar Comprobante
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openPaymentModal(id, concept, amount) {
        document.getElementById('modalConcept').innerText = concept;
        document.getElementById('modalAmount').innerText = '$' + amount.toLocaleString('en-US') + '.00';
        document.getElementById('refConcept').innerText = 'PAGO-' + id;
        document.getElementById('inputChargeId').value = id;

        const modal = document.getElementById('paymentModal');
        const overlay = document.getElementById('paymentOverlay');
        const panel = document.getElementById('paymentPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closePaymentModal() {
        const modal = document.getElementById('paymentModal');
        const overlay = document.getElementById('paymentOverlay');
        const panel = document.getElementById('paymentPanel');

        overlay.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function switchMethod(method) {
        const tabCard = document.getElementById('tabCard');
        const tabTransfer = document.getElementById('tabTransfer');
        const divCard = document.getElementById('methodCard');
        const divTransfer = document.getElementById('methodTransfer');

        if (method === 'card') {
            tabCard.classList.add('border-zinc-900', 'text-zinc-900');
            tabCard.classList.remove('border-transparent', 'text-zinc-400');
            tabTransfer.classList.add('border-transparent', 'text-zinc-400');
            tabTransfer.classList.remove('border-zinc-900', 'text-zinc-900');

            divCard.classList.remove('hidden');
            divTransfer.classList.add('hidden');
        } else {
            tabTransfer.classList.add('border-zinc-900', 'text-zinc-900');
            tabTransfer.classList.remove('border-transparent', 'text-zinc-400');
            tabCard.classList.add('border-transparent', 'text-zinc-400');
            tabCard.classList.remove('border-zinc-900', 'text-zinc-900');

            divTransfer.classList.remove('hidden');
            divCard.classList.add('hidden');
        }
    }
</script>