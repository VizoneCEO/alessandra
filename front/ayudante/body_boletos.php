<?php
// front/ayudante/body_boletos.php
require_once __DIR__ . '/../../back/db_connect.php';

// Fetch Events
$active_events = [];
$res_act = $conn->query("SELECT * FROM finanzas_eventos WHERE activo = 1 ORDER BY fecha DESC");
if ($res_act)
    while ($r = $res_act->fetch_assoc())
        $active_events[] = $r;

$history_events = [];
$res_hist = $conn->query("SELECT * FROM finanzas_eventos WHERE activo = 0 ORDER BY fecha DESC");
if ($res_hist)
    while ($r = $res_hist->fetch_assoc())
        $history_events[] = $r;
?>

<style>
    /* Hide delete buttons via CSS */
    .fa-trash-alt,
    .fa-trash,
    .delete-btn {
        display: none !important;
    }
</style>

<div class="mb-8 flex flex-col md:flex-row justify-between items-end animate-fade-in-up">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Gestión de Boletos</h3>
        <p class="text-zinc-500 font-light text-sm">Control y seguimiento de boletos emitidos.</p>
    </div>
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
        class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm">Boletos
        Activos</button>
    <button onclick="switchMode('history')" id="tab-history"
        class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900">Histórico</button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden animate-fade-in-up">
    <div
        class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
        <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500 flex items-center"><i
                class="fas fa-ticket-alt mr-2"></i> <span id="listTitle">Listado de Boletos</span></h6>
        <div class="flex gap-4 w-full md:w-auto">
            <select id="filterEventActive" onchange="loadTickets()"
                class="w-full pl-3 pr-3 py-2 bg-white border border-zinc-200 rounded text-xs">
                <option value="">Todos los Eventos Activos</option>
                <?php foreach ($active_events as $ev): ?>
                    <option value="<?php echo $ev['id']; ?>">
                        <?php echo htmlspecialchars($ev['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select id="filterEventHistory" onchange="loadTickets()"
                class="w-full pl-3 pr-3 py-2 bg-zinc-50 border border-zinc-200 rounded text-xs hidden">
                <option value="">Todos los Eventos Cerrados</option>
                <?php foreach ($history_events as $ev): ?>
                    <option value="<?php echo $ev['id']; ?>">
                        <?php echo htmlspecialchars($ev['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
                    <td colspan="7" class="px-6 py-8 text-center text-zinc-400 italic">Cargando boletos...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    let currentMode = 'active';

    document.addEventListener('DOMContentLoaded', () => { loadTickets(); });

    function switchMode(mode) {
        currentMode = mode;
        if (mode === 'active') {
            document.getElementById('tab-active').className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm";
            document.getElementById('tab-history').className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900";
            document.getElementById('filterEventActive').classList.remove('hidden');
            document.getElementById('filterEventHistory').classList.add('hidden');
        } else {
            document.getElementById('tab-history').className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all bg-white text-zinc-900 shadow-sm";
            document.getElementById('tab-active').className = "px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all text-zinc-500 hover:text-zinc-900";
            document.getElementById('filterEventHistory').classList.remove('hidden');
            document.getElementById('filterEventActive').classList.add('hidden');
        }
        loadTickets();
    }

    function loadTickets() {
        const tbody = document.getElementById('ticketsBody');
        let eventId = (currentMode === 'active') ? document.getElementById('filterEventActive').value : document.getElementById('filterEventHistory').value;
        const formData = new FormData();
        formData.append('action', 'fetch_tickets');
        formData.append('mode', currentMode);
        if (eventId) formData.append('event_id', eventId);

        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) renderTickets(data.data);
                else tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-rose-500">Error: ${data.message}</td></tr>`;
            });
    }

    function toggleStatus(id) {
        const formData = new FormData();
        formData.append('action', 'toggle_ticket_status');
        formData.append('ticket_id', id);
        fetch('../../back/admin_actions_finanzas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => { if (data.success) loadTickets(); else alert(data.message); });
    }

    function renderTickets(tickets) {
        const tbody = document.getElementById('ticketsBody');
        document.getElementById('totalTickets').innerText = tickets.length;
        if (tickets.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-zinc-400 italic">No se encontraron boletos.</td></tr>'; return; }

        let html = '';
        tickets.forEach(t => {
            // Simplified rendering logic matching admin but without delete button
            let status = 'Emitido';
            let usageBtn = `<button onclick="toggleStatus(${t.id})" class="px-3 py-1 rounded text-[10px] uppercase font-bold tracking-wider bg-zinc-900 text-white hover:bg-zinc-700 transition-colors flex items-center gap-1"><i class="fas fa-undo"></i> ${t.estado_uso || 'Disponible'}</button>`;

            html += `<tr class="hover:bg-zinc-50 transition-colors">
                <td class="px-6 py-4 font-mono font-bold text-lg text-zinc-900">#${String(t.folio_asiento).padStart(4, '0')}</td>
                <td class="px-6 py-4"><span class="px-2 py-1 rounded bg-gray-100 text-gray-800 border-gray-200 border text-[10px] font-bold uppercase tracking-wider mb-1">${t.perfil_id == 3 ? 'ALUMNO' : 'GENERICO'}</span></td>
                <td class="px-6 py-4 text-zinc-600 font-medium">${t.evento}</td>
                <td class="px-6 py-4 font-bold text-zinc-800">${t.alumno}</td>
                <td class="px-6 py-4"><span class="px-2 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider bg-emerald-100 text-emerald-700">Emitido</span></td>
                <td class="px-6 py-4">${usageBtn}</td>
                <td class="px-6 py-4 text-right text-zinc-500 font-mono text-xs">
                    ${t.fecha_emision || '--'}
                    <!-- NO DELETE BUTTON HERE -->
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }
</script>