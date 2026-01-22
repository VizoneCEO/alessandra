<?php
// front/admin/body_boletos.php
// This module manages tickets sold for events.

require_once __DIR__ . '/../../back/db_connect.php';

// Fetch Events for Filter
$active_events = [];
$history_events = [];

// Active
$res_act = $conn->query("SELECT * FROM finanzas_eventos WHERE activo = 1 ORDER BY fecha DESC");
if ($res_act) {
    while ($r = $res_act->fetch_assoc())
        $active_events[] = $r;
}

// History
$res_hist = $conn->query("SELECT * FROM finanzas_eventos WHERE activo = 0 ORDER BY fecha DESC");
if ($res_hist) {
    while ($r = $res_hist->fetch_assoc())
        $history_events[] = $r;
}
?>

<div class="mb-8 flex flex-col md:flex-row justify-between items-end animate-fade-in-up">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Gestión de Boletos</h3>
        <p class="text-zinc-500 font-light text-sm">Control y seguimiento de boletos emitidos por evento.</p>
    </div>

    <!-- Header Stats (Placeholder logic) -->
    <div class="flex gap-4 mt-4 md:mt-0">
        <div class="bg-zinc-900 text-white px-5 py-3 rounded-lg shadow-lg">
            <p class="text-[10px] uppercase tracking-widest opacity-70">Boletos Vendidos</p>
            <p class="text-2xl font-serif" id="totalTickets">0</p>
        </div>
    </div>
</div>

<!-- TABS -->
<div class="flex space-x-1 bg-zinc-100 p-1 rounded-lg w-fit mb-4 animate-fade-in-up">
    <button onclick="switchMode('active')" id="tab-active"
        class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm">
        Boletos Activos
    </button>
    <button onclick="switchMode('history')" id="tab-history"
        class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900">
        Histórico
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden animate-fade-in-up">
    <!-- Toolbar -->
    <div
        class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
        <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500 flex items-center">
            <i class="fas fa-ticket-alt mr-2"></i>
            <span id="listTitle">Listado de Boletos (Activos)</span>
        </h6>

        <div class="flex gap-4 w-full md:w-auto">
            <!-- Filter Active -->
            <div class="relative flex-1 md:w-64" id="container-filter-active">
                <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                <select id="filterEventActive" onchange="loadTickets()"
                    class="w-full pl-8 pr-3 py-2 bg-white border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors appearance-none cursor-pointer">
                    <option value="">Todos los Eventos Activos</option>
                    <?php foreach ($active_events as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>">
                            <?php echo htmlspecialchars($ev['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i
                    class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs pointer-events-none"></i>
            </div>

            <!-- Filter History -->
            <div class="relative flex-1 md:w-64 hidden" id="container-filter-history">
                <i class="fas fa-history absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                <select id="filterEventHistory" onchange="loadTickets()"
                    class="w-full pl-8 pr-3 py-2 bg-zinc-50 border border-zinc-200 rounded text-xs focus:border-zinc-500 outline-none transition-colors appearance-none cursor-pointer">
                    <option value="">Todos los Eventos Cerrados</option>
                    <?php foreach ($history_events as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>">
                            <?php echo htmlspecialchars($ev['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i
                    class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs pointer-events-none"></i>
            </div>

            <div class="relative flex-1 md:w-32">
                <i class="fas fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                <select id="filterTicketType" onchange="filterTable()"
                    class="w-full pl-8 pr-3 py-2 bg-white border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors appearance-none cursor-pointer">
                    <option value="">Todos los Tipos</option>
                    <option value="Administrativo">Administrativo</option>
                    <option value="Docente">Docente</option>
                    <option value="Staff">Staff</option>
                    <option value="Invitado">Invitado</option>
                    <option value="Alumno">Alumno</option>
                    <option value="Modelo">Modelo</option>
                </select>
                <i
                    class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs pointer-events-none"></i>
            </div>
        </div>

        <div class="relative flex-1 md:w-48">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
            <input type="text" id="searchTicket" placeholder="Buscar folio o alumno..." onkeyup="filterTable()"
                class="w-full pl-8 pr-3 py-2 bg-white border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors">
        </div>

        <div>
            <button onclick="exportTickets()"
                class="h-full px-4 py-2 bg-emerald-600 text-white text-xs font-bold uppercase rounded shadow hover:bg-emerald-700 transition-colors flex items-center gap-2">
                <i class="fas fa-file-excel"></i> Exportar
            </button>
        </div>
    </div>
</div>

<!-- Table -->
<div class="overflow-x-auto min-h-[400px]">
    <table class="w-full text-left">
        <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
            <tr>
                <th class="px-6 py-4 font-medium">Folio / Asiento</th>
                <th class="px-6 py-4 font-medium">Tipo</th>
                <th class="px-6 py-4 font-medium">Evento</th>
                <th class="px-6 py-4 font-medium">Alumno / Titular</th>
                <th class="px-6 py-4 font-medium">Estado Pago</th>
                <th class="px-6 py-4 font-medium">Estado Uso</th>
                <th class="px-6 py-4 font-medium text-right">Fecha Emisión</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-50 text-sm" id="ticketsBody">
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-zinc-400 italic">Cargando boletos...</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="px-6 py-3 border-t border-zinc-100 bg-zinc-50 text-xs text-zinc-400 text-right">
    <span id="ticketCount">0</span> registros mostrados
</div>
</div>


<script>
    let currentMode = 'active';

    document.addEventListener('DOMContentLoaded', () => {
        loadTickets();
    });

    function switchMode(mode) {
        currentMode = mode;

        // Update Tabs
        const btnActive = document.getElementById('tab-active');
        const btnHistory = document.getElementById('tab-history');

        if (mode === 'active') {
            btnActive.className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm";
            btnHistory.className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900";

            document.getElementById('container-filter-active').classList.remove('hidden');
            document.getElementById('container-filter-history').classList.add('hidden');
            document.getElementById('listTitle').innerText = "Listado de Boletos (Activos)";
        } else {
            btnHistory.className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm";
            btnActive.className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900";

            document.getElementById('container-filter-history').classList.remove('hidden');
            document.getElementById('container-filter-active').classList.add('hidden');
            document.getElementById('listTitle').innerText = "Histórico de Boletos (Cerrados)";
        }

        // Reset search
        document.getElementById('searchTicket').value = '';

        loadTickets();
    }

    function loadTickets() {
        const tbody = document.getElementById('ticketsBody');

        let eventId = '';
        if (currentMode === 'active') {
            eventId = document.getElementById('filterEventActive').value;
        } else {
            eventId = document.getElementById('filterEventHistory').value;
        }

        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-zinc-400 italic">Actualizando...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'fetch_tickets');
        formData.append('mode', currentMode);
        if (eventId) formData.append('event_id', eventId);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderTickets(data.data);
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-rose-500">Error: ${data.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-rose-500">Error de conexión</td></tr>';
            });
    }

    function toggleStatus(id) {
        const formData = new FormData();
        formData.append('action', 'toggle_ticket_status');
        formData.append('ticket_id', id);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadTickets(); // Reload table
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function renderTickets(tickets) {
        const tbody = document.getElementById('ticketsBody');
        const totalEl = document.getElementById('totalTickets');
        const countEl = document.getElementById('ticketCount');

        totalEl.innerText = tickets.length;
        countEl.innerText = tickets.length;

        if (tickets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-zinc-400 italic">No se encontraron boletos.</td></tr>';
            return;
        }

        let html = '';
        tickets.forEach(t => {
            // Status Badge
            let status = 'Emitido'; // Default assumption
            let badgeClass = 'bg-emerald-100 text-emerald-700';

            // Usage Status
            let uso = t.estado_uso || 'Disponible';
            let isHistory = currentMode === 'history';

            // Infer Type based on Profile and Concept
            let tipo = 'Staff'; // Default fallback
            let concepto = t.cargo_concepto || '';
            let perfil = parseInt(t.perfil_id) || 0;

            if (concepto.includes('Invitados')) {
                tipo = 'Invitado';
            } else if (concepto.includes('Modelos')) {
                tipo = 'Modelo';
            } else {
                // User-based Logic
                if (perfil === 1) tipo = 'Administrativo';
                else if (perfil === 2) tipo = 'Alumno';
                else if (perfil === 3) tipo = 'Docente';
                else if (perfil === 4) tipo = 'Staff'; // Assuming 4 is Staff, or others are Staff
                else {
                    // Fallback to concept if profile logic fails or external
                    if (concepto.includes('Staff')) tipo = 'Staff';
                    else tipo = 'Generico';
                }
            }

            // Format Folio
            let folioDisplay = t.folio_asiento > 0 ? '#' + String(t.folio_asiento).padStart(4, '0') : '-';

            let btnColor = (uso === 'Disponible') ? 'bg-zinc-900 text-white hover:bg-zinc-700' : 'bg-zinc-200 text-zinc-500 hover:bg-zinc-300';
            let btnIcon = (uso === 'Disponible') ? 'fa-check' : 'fa-undo';

            if (isHistory) {
                btnColor = 'bg-zinc-100 text-zinc-400 cursor-not-allowed';
            }

            let actionBtn = isHistory
                ? `<span class="px-3 py-1 text-[10px] uppercase font-bold text-zinc-400 flex items-center gap-1"><i class="fas ${btnIcon}"></i> ${uso}</span>`
                : `<button onclick="toggleStatus(${t.id})" class="px-3 py-1 rounded text-[10px] uppercase font-bold tracking-wider ${btnColor} transition-colors flex items-center gap-1"><i class="fas ${btnIcon}"></i> ${uso}</button>`;

            html += `<tr class="hover:bg-zinc-50 transition-colors ticket-row" data-search="${t.alumno.toLowerCase()} ${t.folio_asiento} ${tipo.toLowerCase()}" data-tipo="${tipo}">
            <td class="px-6 py-4 font-mono font-bold text-lg text-zinc-900">${folioDisplay}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 rounded bg-zinc-100 border border-zinc-200 text-zinc-600 text-[10px] font-bold uppercase tracking-wider">${tipo}</span>
            </td>
            <td class="px-6 py-4 text-zinc-600 font-medium">${t.evento}</td>
            <td class="px-6 py-4 font-bold text-zinc-800">${t.alumno}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider ${badgeClass}">${status}</span>
            </td>
            <td class="px-6 py-4">
                ${actionBtn}
            </td>
            <td class="px-6 py-4 text-right text-zinc-500 font-mono text-xs">
                ${t.fecha_emision || '--'}
                <div class="flex justify-end gap-2 mt-2">
                    <button onclick="viewQR('${t.id}', '${t.alumno}', '${t.folio_asiento}')" class="w-8 h-8 rounded-full bg-white border border-zinc-200 text-zinc-400 hover:text-zinc-900 hover:border-zinc-900 transition-all shadow-sm flex items-center justify-center" title="Ver QR de Acceso">
                        <i class="fas fa-qrcode"></i>
                    </button>
                    <button onclick="shareTicket('${t.id}', '${t.evento}', '${t.alumno}')" class="w-8 h-8 rounded-full bg-white border border-zinc-200 text-zinc-400 hover:text-blue-600 hover:border-blue-600 transition-all shadow-sm flex items-center justify-center" title="Compartir Boleto">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    ${!isHistory ? `
                    <button onclick="deleteTicket('${t.id}')" class="w-8 h-8 rounded-full bg-white border border-zinc-200 text-zinc-400 hover:text-rose-600 hover:border-rose-600 transition-all shadow-sm flex items-center justify-center" title="Eliminar Boleto">
                        <i class="fas fa-trash-alt"></i>
                    </button>` : ''}
                </div>
            </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function deleteTicket(id) {
        if (!confirm('¿Estás seguro de que deseas eliminar este boleto? Esta acción es irreversible y liberará el folio si es el último generado.')) return;

        const formData = new FormData();
        formData.append('action', 'delete_ticket');
        formData.append('ticket_id', id);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Boleto eliminado correctamente.');
                    loadTickets();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error de conexión'));
    }

    function filterTable() {
        const term = document.getElementById('searchTicket').value.toLowerCase();
        const typeFilter = document.getElementById('filterTicketType').value;

        document.querySelectorAll('.ticket-row').forEach(row => {
            const searchData = row.dataset.search || '';
            const rowType = row.dataset.tipo || '';

            const matchTerm = searchData.includes(term);
            const matchType = !typeFilter || rowType === typeFilter;

            if (matchTerm && matchType) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    function viewQR(id, name, folio) {
        // Mock QR View
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=TICKET:${id}`;

        let modal = document.getElementById('qrModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'qrModal';
            modal.className = 'fixed inset-0 z-[70] bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center hidden';
            modal.innerHTML = `
                <div class="bg-white p-8 rounded-xl shadow-2xl flex flex-col items-center animate-fade-in-up max-w-sm w-full mx-4">
                    <h3 class="font-serif italic text-2xl mb-2">Acceso al Evento</h3>
                    <p class="text-xs text-zinc-500 mb-6 text-center" id="qrSubtitle">...</p>
                    <div class="bg-white p-2 border border-zinc-100 rounded-lg shadow-inner mb-6">
                        <img id="qrImage" src="" class="w-48 h-48 object-contain">
                    </div>
                    <button onclick="document.getElementById('qrModal').classList.add('hidden')" class="px-6 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded-full hover:bg-zinc-800 transition-all">Cerrar</button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        document.getElementById('qrSubtitle').innerText = name + (folio > 0 ? (' #' + String(folio).padStart(4, '0')) : '');
        document.getElementById('qrImage').src = qrUrl;
        modal.classList.remove('hidden');
    }

    function shareTicket(id, event, name) {
        const text = `Hola ${name}, aquí tienes tu acceso para el evento "${event}". Tu ID de boleto es: ${id}.`;
        if (navigator.share) {
            navigator.share({
                title: 'Boleto de Acceso',
                text: text,
                url: window.location.href
            }).catch(console.error);
        } else {
            navigator.clipboard.writeText(text).then(() => alert('Enlace copiado al portapapeles'));
        }
    }

    function exportTickets() {
        const eventId = (currentMode === 'active')
            ? document.getElementById('filterEventActive').value
            : document.getElementById('filterEventHistory').value;
        const type = document.getElementById('filterTicketType').value;

        // Build URL
        let url = `../../back/admin_actions_finanzas.php?action=export_tickets&mode=${currentMode}`;
        if (eventId) url += `&event_id=${eventId}`;
        if (type) url += `&type=${encodeURIComponent(type)}`;

        window.location.href = url;
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