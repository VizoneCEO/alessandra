<?php
// Enable Error Reporting for Debugging
ini_set('display_errors', 0); // Turning off display_errors now that we fixed the query
error_reporting(E_ALL);

require_once '../../back/db_connect.php';

$student_id = $_SESSION['user_id'];

// Fetch Tickets
// We join with Event and User to get details.
// Removed 'hora' and 'lugar' as they don't exist in the DB schema yet.
$sql = "SELECT b.*, e.nombre as evento, e.fecha as fecha_evento
FROM finanzas_boletos b
JOIN finanzas_eventos e ON b.evento_id = e.id
WHERE b.alumno_id = $student_id
ORDER BY e.fecha DESC, b.folio_asiento ASC";

$tickets = [];
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $tickets[] = $r;
    }
}
?>

<!-- QR Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div class="max-w-4xl mx-auto animate-fade-in-up">

    <div class="mb-8">
        <h3 class="font-serif text-3xl text-zinc-900 italic">Mis Boletos</h3>
        <p class="text-zinc-500 font-light text-sm mt-1">Aquí encontrarás tus entradas digitales. Presenta el código QR
            al ingresar.</p>
    </div>

    <?php if (empty($tickets)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-12 text-center">
            <div class="h-16 w-16 bg-zinc-50 rounded-full flex items-center justify-center mx-auto mb-4 text-zinc-300">
                <i class="fas fa-ticket-alt text-2xl"></i>
            </div>
            <p class="text-zinc-800 font-medium">No tienes boletos activos</p>
            <p class="text-xs text-zinc-400 mt-2">Tus entradas aparecerán aquí cuando se confirmen tus pagos de eventos.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($tickets as $t):
                // Generate Unique Token for QR (e.g. JSON with ID and Random Check)
                // For simplicity: JSON {id: 123, e: 1, f: 10}
                $qrData = json_encode([
                    'id' => $t['id'],
                    'ev' => $t['evento_id'],
                    'fol' => $t['folio_asiento'],
                    'usr' => $student_id
                ]);
                $folioPad = str_pad($t['folio_asiento'], 4, '0', STR_PAD_LEFT);
                $dateFormatted = date('d M, Y', strtotime($t['fecha_evento']));
                $timeFormatted = 'Hora por definir'; // Placeholder
                // Use default location as placeholder
                $location = 'Auditorio Principal';

                // Status Logic
                $state = $t['estado_uso'] ?? 'Disponible';
                $isUsed = ($state === 'Usado');
                $statusColor = $isUsed ? 'bg-zinc-200 text-zinc-500' : 'bg-emerald-100 text-emerald-700';
                $statusIcon = $isUsed ? 'fa-check-circle' : 'fa-ticket-alt';
                $opacityClass = $isUsed ? 'opacity-75 grayscale' : '';
                ?>
                <div id="ticket-card-<?php echo $t['id']; ?>"
                    class="bg-white rounded-2xl shadow-sm border border-zinc-200 overflow-hidden flex flex-col relative group hover:shadow-md transition-shadow <?php echo $opacityClass; ?>">
                    <!-- Status Badge Absolute -->
                    <div class="absolute top-4 right-4 z-20">
                        <span
                            class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo $statusColor; ?> flex items-center gap-1 shadow-sm">
                            <i class="fas <?php echo $statusIcon; ?>"></i> <?php echo $state; ?>
                        </span>
                    </div>
                    <!-- Ticket Header / Event Info -->
                    <div class="bg-zinc-900 p-5 text-white relative overflow-hidden">
                        <img src="../multimedia/boleto.jpeg" class="absolute inset-0 w-full h-full object-cover opacity-50 mix-blend-overlay">
                        <div class="absolute -right-4 -top-8 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
                        <h4 class="font-bold text-lg leading-tight mb-1 relative z-10">
                            <?php echo htmlspecialchars($t['evento']); ?>
                        </h4>
                        <div class="flex items-center text-xs text-zinc-100 gap-3 mt-2 relative z-10">
                            <span class="flex items-center"><i class="fas fa-calendar mr-1.5 opacity-80"></i>
                                <?php echo $dateFormatted; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Ticket Body -->
                    <div class="p-6 flex flex-row items-center gap-6">
                        <!-- QR Code Area -->
                        <div class="flex-shrink-0">
                            <canvas id="qr-<?php echo $t['id']; ?>"
                                class="border-4 border-white shadow-sm rounded-lg w-28 h-28 bg-zinc-50"></canvas>
                        </div>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] uppercase tracking-widest text-zinc-400 font-bold mb-1">Folio</p>
                            <p class="font-mono text-2xl text-zinc-900 font-bold tracking-tight mb-3">#
                                <?php echo $folioPad; ?>
                            </p>

                            <p class="text-[10px] uppercase tracking-widest text-zinc-400 font-bold mb-1">Alumno</p>
                            <p class="text-sm font-medium text-zinc-700 truncate">
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Cut Line Visual -->
                    <div class="relative h-4 w-full flex items-center justify-between px-2">
                        <div class="h-4 w-4 bg-slate-50 rounded-full -ml-3 border-r border-zinc-200"></div>
                        <div class="flex-1 border-t-2 border-dashed border-zinc-200 mx-2"></div>
                        <div class="h-4 w-4 bg-slate-50 rounded-full -mr-3 border-l border-zinc-200"></div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="bg-zinc-50 p-4 flex justify-between items-center text-xs">
                        <span class="text-zinc-500 font-medium"><i class="fas fa-map-marker-alt mr-1 text-rose-500"></i>
                            <?php echo htmlspecialchars($location); ?>
                        </span>

                        <button
                            onclick="downloadTicket(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['evento']); ?>', '<?php echo $t['folio_asiento']; ?>')"
                            class="text-zinc-400 hover:text-emerald-600 transition-colors flex items-center gap-1 font-medium">
                            <i class="fas fa-download"></i> Descargar Boleto
                        </button>
                    </div>
                </div>

                <script>
                    (function () {
                        var qr = new QRious({
                            element: document.getElementById('qr-<?php echo $t['id']; ?>'),
                            value: '<?php echo $qrData; ?>',
                            size: 150,
                            level: 'H'
                        });
                    })();
                </script>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function downloadTicket(id, eventName, folio) {
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=TICKET:${id}`;
        const folioText = folio > 0 ? '#' + String(folio).padStart(4, '0') : '--';
        const typeLabel = 'ALUMNO';
        const studentName = '<?php echo htmlspecialchars($_SESSION['user_name']); ?>';

        const tempDiv = document.createElement('div');
        tempDiv.style.position = 'fixed';
        tempDiv.style.left = '-9999px';
        tempDiv.style.top = '0';
        tempDiv.style.width = '800px';
        tempDiv.style.height = '600px';
        tempDiv.style.display = 'flex';
        tempDiv.style.backgroundColor = '#ffffff';
        tempDiv.style.zIndex = '9999';

        // Styles matching Admin
        tempDiv.innerHTML = `
            <div style="width: 50%; height: 100%; position: relative; overflow: hidden;">
                <img src="../multimedia/boleto.jpeg" style="width: 100%; height: 100%; object-fit: fill;"> 
            </div>
            <div style="width: 50%; height: 100%; padding: 40px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: white; font-family: 'Times New Roman', serif;">
                <img src="../multimedia/logoA.png" style="width: 50px; margin-bottom: 20px; opacity: 0.8;">
                <h2 style="font-size: 32px; color: #18181b; margin: 0 0 5px 0;">Bienvenido</h2>
                <p style="font-style: italic; color: #71717a; margin: 0 0 25px 0; font-size: 14px;">Acceso al Evento</p>
                
                <div style="margin-bottom: 15px; width: 100%;">
                    <p style="font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a1a1aa; margin: 0; font-family: sans-serif;">Tipo De Boleto</p>
                    <p style="font-size: 16px; font-weight: bold; color: #18181b; margin: 4px 0 0 0; font-family: sans-serif;">${typeLabel}</p>
                </div>

                <div style="margin-bottom: 15px; width: 100%;">
                     <p style="font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a1a1aa; margin: 0; font-family: sans-serif;">Titular</p>
                     <p style="font-size: 15px; font-weight: bold; color: #18181b; margin: 4px 0 0 0; font-family: sans-serif;">${studentName}</p>
                </div>

                <div style="margin-bottom: 25px;">
                     <p style="font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a1a1aa; margin: 0; font-family: sans-serif;">Asiento</p>
                     <p style="font-family: monospace; font-size: 18px; color: #18181b; font-weight: bold; margin: 4px 0 0 0;">${folioText}</p>
                </div>
                
                <div style="padding: 8px; border: 1px solid #f4f4f5; border-radius: 8px;">
                    <img src="${qrUrl}" style="width: 120px; height: 120px;">
                </div>
            </div>
        `;

        document.body.appendChild(tempDiv);

        const imgs = tempDiv.querySelectorAll('img');
        const promises = [];
        imgs.forEach(img => {
            if (!img.complete) {
                promises.push(new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                }));
            }
        });

        Promise.all(promises).then(() => {
            setTimeout(() => {
                html2canvas(tempDiv, { useCORS: true, scale: 2 }).then(canvas => {
                    const link = document.createElement('a');
                    link.download = `Boleto_${eventName}_${folio}.png`;
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                    document.body.removeChild(tempDiv);
                });
            }, 500);
        });
    }
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