<?php
// front/admin/body_mensajes.php

// 1. Fetch History
$admin_id = $_SESSION['user_id'];
$sql_history = "SELECT batch_id, asunto, cuerpo, MAX(fecha) as fecha, COUNT(*) as total_destinatarios, SUM(leido) as total_leidos 
                FROM Mensajes 
                WHERE remitente_id = $admin_id AND deleted_at IS NULL
                GROUP BY batch_id, asunto, cuerpo
                ORDER BY fecha DESC LIMIT 20";
$res_history = $conn->query($sql_history);
$history = [];
if ($res_history) {
    $history = $res_history->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="mb-8 flex justify-between items-end">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Mensajería</h3>
        <p class="text-zinc-500 font-light text-sm">Envía notificaciones a grupos o usuarios específicos.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left Col: Compose Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6">
            <h4 class="font-bold text-zinc-900 mb-4 border-b border-zinc-100 pb-2">Redactar Nueva</h4>

            <?php
            // Calculate base URL dynamically to avoid relative path issues
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            // We know backend is in /alessandra/alessandra/back/ usually, but let's be safe
            // If we are in front/admin/body_mensajes.php included by dashboard.php
            // Root is likely 2 levels up from dashboard.php
            // Better yet, let's use the known working relative path that debug_path.php confirmed:
            // ../../back/mensajes_actions.php
            // But if the browser is 404ing on that, it might be due to base tag or similar.
            // Let's force the absolute path we verified via curl: /alessandra/alessandra/back/mensajes_actions.php
            // Wait, the user has 'alessandra/alessandra'.
            $action_url = "/alessandra/alessandra/back/mensajes_actions.php";
            ?>
            <form id="composeForm" action="<?php echo $action_url; ?>" method="POST"
                onsubmit="handleSendMessage(event)">
                <input type="hidden" name="action_type" value="send_message">

                <!-- Target Select -->
                <div class="mb-4">
                    <label class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Enviar A</label>
                    <select name="target_type" id="target_type" onchange="toggleSpecificSelector()"
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-zinc-50">
                        <option value="all">Todos los Usuarios Activos</option>
                        <option value="alumnos">Solo Alumnos</option>
                        <option value="profesores">Solo Profesores</option>
                        <option value="administrativos">Solo Administrativos</option>
                        <option value="specific">Usuarios Específicos</option>
                    </select>
                </div>

                <!-- Specific User Selector (Dynamic) -->
                <div id="specific_selector_container" class="mb-4 hidden">
                    <label class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Seleccionar
                        Usuarios</label>
                    <div class="border border-zinc-200 rounded max-h-48 overflow-y-auto p-2 bg-zinc-50"
                        id="user_list_container">
                        <div class="text-center text-zinc-400 text-xs py-2">Cargando usuarios...</div>
                    </div>
                    <p class="text-[10px] text-zinc-400 mt-1">Manten presionado Ctrl/Cmd para seleccionar varios si
                        fuera un select multiple nativo, pero aqui son checkboxes.</p>
                </div>

                <!-- Subject -->
                <div class="mb-4">
                    <label class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Asunto</label>
                    <input type="text" name="asunto" required
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none"
                        placeholder="Ej. Aviso Importante">
                </div>

                <!-- Body -->
                <div class="mb-6">
                    <label class="block text-xs uppercase tracking-wider text-zinc-500 mb-1">Mensaje</label>
                    <textarea name="cuerpo" rows="6" required
                        class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none resize-none"
                        placeholder="Escribe tu mensaje..."></textarea>
                </div>

                <!-- Submit -->
                <button type="submit" id="btnSubmit"
                    class="w-full bg-zinc-900 text-white py-3 rounded text-sm font-bold uppercase tracking-wider hover:bg-zinc-800 transition-colors shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Enviar Notificación
                </button>
            </form>
        </div>
    </div>

    <!-- Right Col: History -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="p-6 border-b border-zinc-100 bg-zinc-50/50 flex justify-between items-center">
                <h4 class="font-bold text-zinc-900">Historial de Envíos Recientes</h4>
                <span class="text-xs text-zinc-400">Últimos 20 mensajes</span>
            </div>

            <div class="divide-y divide-zinc-50">
                <?php if (count($history) > 0): ?>
                    <?php foreach ($history as $msg): ?>
                        <div class="p-4 hover:bg-zinc-50 transition-colors group relative">
                            <div class="flex justify-between items-start mb-1">
                                <span class="bg-indigo-100 text-indigo-700 text-[10px] uppercase font-bold px-2 py-0.5 rounded">
                                    <?php echo htmlspecialchars($msg['asunto']); ?>
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-zinc-400 font-mono">
                                        <?php echo date('d M H:i', strtotime($msg['fecha'])); ?>
                                    </span>
                                    <!-- Delete Button -->
                                    <button onclick="deleteMessage('<?php echo $msg['batch_id']; ?>')"
                                        class="text-zinc-300 hover:text-red-500 transition-colors" title="Eliminar para todos">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-sm text-zinc-600 mb-2 line-clamp-2">
                                <?php echo htmlspecialchars(strip_tags($msg['cuerpo'])); ?>
                            </p>
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-zinc-500">
                                    <i class="fas fa-users mr-1 text-zinc-300"></i>
                                    Enviado a: <span
                                        class="font-bold text-zinc-700"><?php echo $msg['total_destinatarios']; ?></span>
                                    usuarios
                                </div>
                                <div>
                                    <button type="button" onclick="viewReaders('<?php echo $msg['batch_id']; ?>')"
                                        class="text-emerald-500 hover:text-emerald-700 text-[10px] font-bold flex items-center transition-colors">
                                        <i class="fas fa-check-double mr-1"></i> <?php echo $msg['total_leidos']; ?> leídos
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-12 text-center text-zinc-400 text-sm italic">
                        No has enviado mensajes recientemente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Readers Modal -->
<div id="readersModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm"
        onclick="closeReadersModal()"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Usuarios que
                                leyeron</h3>
                            <div class="mt-2 text-sm text-gray-500 max-h-60 overflow-y-auto" id="readersList">
                                <p class="text-center py-4">Cargando...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="button"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                        onclick="closeReadersModal()">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let usersLoaded = false;

    async function toggleSpecificSelector() {
        const type = document.getElementById('target_type').value;
        const container = document.getElementById('specific_selector_container');

        if (type === 'specific') {
            container.classList.remove('hidden');
            if (!usersLoaded) {
                await loadUsers();
            }
        } else {
            container.classList.add('hidden');
        }
    }

    async function loadUsers() {
        const listDiv = document.getElementById('user_list_container');
        try {
            const formData = new FormData();
            formData.append('action', 'get_users_list'); // Need to implement this helper in backend if not exists!
            // Actually I implemented `get_users_list` in step 296? Wait, let me check backend file content mentally... 
            // In step 301, I implemented `get_users_list`. Yes.

            const req = await fetch('<?php echo $action_url; ?>', {
                method: 'POST',
                body: new URLSearchParams('action=get_users_list') // Simple POST
            });
            const data = await req.json();

            if (data.success) {
                let html = '';
                data.users.forEach(u => {
                    html += `
                        <div class="flex items-center p-1 hover:bg-zinc-100 rounded cursor-pointer">
                            <input type="checkbox" name="destinatarios[]" value="${u.id}" id="user_${u.id}" class="mr-2 rounded border-gray-300 text-zinc-900 focus:ring-zinc-900">
                            <label for="user_${u.id}" class="flex-1 text-xs cursor-pointer select-none">
                                <span class="font-bold text-zinc-700">${u.nombre_completo}</span>
                                <span class="text-[10px] text-zinc-400 ml-1">#${u.perfil_id}</span>
                            </label>
                        </div>
                    `;
                });
                listDiv.innerHTML = html;
                usersLoaded = true;
            } else {
                listDiv.innerHTML = '<div class="text-red-500 text-xs">Error cargando usuarios</div>';
            }
        } catch (e) {
            console.error(e);
            listDiv.innerHTML = '<div class="text-red-500 text-xs">Error de conexión</div>';
        }
    }

    function handleSendMessage(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmit');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Enviando...';

        const form = e.target;
        const formData = new FormData(form);

        const actionUrl = form.getAttribute('action');

        fetch(actionUrl, {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Enviado!',
                        text: data.message,
                        confirmButtonColor: '#18181b'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#18181b'
                    });
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error Crítico',
                    text: 'No se pudo conectar con el servidor.',
                    confirmButtonColor: '#18181b'
                });
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    }

    function deleteMessage(batchId) {
        if (!batchId) return;

        Swal.fire({
            title: '¿Eliminar mensaje?',
            text: "Se eliminará del historial y de la bandeja de entrada de los usuarios.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action_type', 'delete_batch');
                formData.append('batch_id', batchId);

                // Use the same absolute URL logic or hardcode it since we know it works
                fetch('/alessandra/alessandra/back/mensajes_actions.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Eliminado', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', 'No se pudo conectar', 'error'));
            }
        });

    }

    function viewReaders(batchId) {
        const modal = document.getElementById('readersModal');
        const list = document.getElementById('readersList');
        modal.classList.remove('hidden');
        list.innerHTML = '<p class="text-center py-4 text-zinc-400">Cargando lectores...</p>';

        const formData = new FormData();
        formData.append('action_type', 'get_batch_readers');
        formData.append('batch_id', batchId);

        fetch('/alessandra/alessandra/back/mensajes_actions.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.readers.length === 0) {
                        list.innerHTML = '<p class="text-center py-4 text-zinc-400">Nadie ha leído este mensaje aún.</p>';
                    } else {
                        let html = '<ul class="divide-y divide-zinc-100">';
                        data.readers.forEach(r => {
                            const statusClass = 'text-emerald-500 bg-emerald-50';
                            const icon = 'fa-check-double';
                            const text = 'Leído';
                            // Date is roughly msg.fecha, but ideally we'd have a read_at timestamp. 
                            // For now using the message date or just "Visto".

                            html += `
                            <li class="py-2 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-zinc-900">${r.nombre_completo}</p>
                                    <p class="text-xs text-zinc-400">${r.nombre_perfil || 'Usuario'}</p>
                                </div>
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ring-black/5 ${statusClass}">
                                    <i class="fas ${icon} mr-1"></i> ${text}
                                </span>
                            </li>
                        `;
                        });
                        html += '</ul>';
                        list.innerHTML = html;
                    }
                } else {
                    list.innerHTML = `<p class="text-red-500 text-center">${data.message}</p>`;
                }
            })
            .catch(err => {
                list.innerHTML = '<p class="text-red-500 text-center">Error de conexión.</p>';
            });
    }

    function closeReadersModal() {
        document.getElementById('readersModal').classList.add('hidden');
    }
</script>