<?php
// front/alumno/body_notificaciones.php (also used by profesor)

$user_id = $_SESSION['user_id'];

// Fetch Messages
$sql_msgs = "SELECT m.*, u.nombre_completo as remitente_nombre, p.nombre_perfil as remitente_perfil 
             FROM Mensajes m 
             JOIN Usuarios u ON m.remitente_id = u.id 
             JOIN Perfiles p ON u.perfil_id = p.id
             WHERE m.destinatario_id = ? AND m.deleted_at IS NULL 
             ORDER BY m.fecha DESC";
$stmt = $conn->prepare($sql_msgs);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Mis Notificaciones</h3>
    <p class="text-zinc-500 font-light text-sm">Mensajes recibidos de la administración.</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
    <div class="divide-y divide-zinc-50">
        <?php if (count($messages) > 0): ?>
            <?php foreach ($messages as $msg): ?>
                <div onclick="openNotification(<?php echo htmlspecialchars(json_encode($msg)); ?>)"
                    class="group p-4 hover:bg-zinc-50 cursor-pointer transition-colors flex items-start gap-4 <?php echo $msg['leido'] == 0 ? 'bg-amber-50/50' : ''; ?>">

                    <!-- Icon -->
                    <div
                        class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center <?php echo $msg['leido'] == 0 ? 'bg-amber-100 text-amber-600' : 'bg-zinc-100 text-zinc-400'; ?>">
                        <i class="fas fa-envelope<?php echo $msg['leido'] == 0 ? '' : '-open'; ?>"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <h5
                                    class="text-sm font-bold text-zinc-900 mb-0.5 <?php echo $msg['leido'] == 0 ? '' : 'font-medium'; ?>">
                                    <?php echo htmlspecialchars($msg['asunto']); ?>
                                </h5>
                                <p class="text-xs text-zinc-500">
                                    De: <span class="font-medium text-zinc-700">
                                        <?php echo htmlspecialchars($msg['remitente_nombre']); ?>
                                    </span>
                                    <span class="bg-zinc-100 text-zinc-400 text-[10px] px-1.5 rounded ml-1">
                                        <?php echo htmlspecialchars($msg['remitente_perfil']); ?>
                                    </span>
                                </p>
                            </div>
                            <span class="text-[10px] text-zinc-400 font-mono whitespace-nowrap ml-2">
                                <?php echo date('d M H:i', strtotime($msg['fecha'])); ?>
                            </span>
                        </div>
                        <p class="text-xs text-zinc-600 mt-2 line-clamp-2">
                            <?php echo htmlspecialchars(strip_tags($msg['cuerpo'])); ?>
                        </p>
                    </div>

                    <?php if ($msg['leido'] == 0): ?>
                        <div class="w-2 h-2 rounded-full bg-amber-500 flex-shrink-0 mt-2"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-zinc-50 rounded-full flex items-center justify-center mx-auto mb-4 text-zinc-300">
                    <i class="fas fa-inbox text-2xl"></i>
                </div>
                <p class="text-zinc-500 text-sm italic">No tienes notificaciones recibidas.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Notification Modal -->
<div id="notificationModal"
    class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in-up">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span
                        class="text-[10px] uppercase tracking-widest font-bold text-zinc-400 mb-1 block">Notificación</span>
                    <h2 id="modal_asunto" class="text-xl font-bold text-zinc-900 leading-tight"></h2>
                </div>
                <button onclick="closeNotification()" class="text-zinc-400 hover:text-zinc-900 transition-colors">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>

            <div class="flex items-center text-xs text-zinc-500 gap-2 mb-6 border-b border-zinc-100 pb-4">
                <span class="font-bold text-zinc-700" id="modal_remitente"></span>
                <span>&bull;</span>
                <span id="modal_fecha"></span>
            </div>

            <div class="text-sm text-zinc-700 leading-relaxed whitespace-pre-wrap" id="modal_cuerpo"></div>
        </div>

        <div class="bg-zinc-50 px-6 py-4 flex justify-end">
            <button onclick="closeNotification()"
                class="px-4 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider rounded hover:bg-zinc-800 transition-colors">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
    function openNotification(msg) {
        document.getElementById('modal_asunto').textContent = msg.asunto;
        document.getElementById('modal_remitente').textContent = msg.remitente_nombre;
        document.getElementById('modal_fecha').textContent = msg.fecha;
        document.getElementById('modal_cuerpo').textContent = msg.cuerpo;

        document.getElementById('notificationModal').classList.remove('hidden');

        // Mark as read if unread
        if (msg.leido == 0) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('mensaje_id', msg.id);

            fetch('/alessandra/alessandra/back/mensajes_actions.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    // Ideally we'd update UI here without reload, but reload is safer to update badge
                    window.needsReload = true;
                }
            });
        }
    }

    function closeNotification() {
        document.getElementById('notificationModal').classList.add('hidden');
        if (window.needsReload) {
            location.reload();
        }
    }

    // Reuse animation styles from dashboard if available, or add here
</script>

<style>
    .animate-fade-in-up {
        animation: fadeInUp 0.3s ease-out;
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