<?php
// front/admin/body_boletos.php
// This module manages tickets sold for events.

require_once __DIR__ . '/../../back/db_connect.php';

// Fetch Events for Filter
$events = [];
$res_ev = $conn->query("SELECT * FROM finanzas_eventos WHERE activo = 1 ORDER BY fecha DESC");
if ($res_ev) {
    while ($r = $res_ev->fetch_assoc()) {
        $events[] = $r;
    }
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

<div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden animate-fade-in-up">
    <!-- Toolbar -->
    <div
        class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
        <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500">
            <i class="fas fa-ticket-alt mr-2"></i> Listado de Boletos
        </h6>

        <div class="flex gap-4 w-full md:w-auto">
            <div class="relative flex-1 md:w-64">
                <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                <select id="filterEvent" onchange="loadTickets()"
                    class="w-full pl-8 pr-3 py-2 bg-white border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors appearance-none cursor-pointer">
                    <option value="">Todos los Eventos Activos</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>">
                            <?php echo htmlspecialchars($ev['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i
                    class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs pointer-events-none"></i>
            </div>

            <div class="relative flex-1 md:w-48">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs"></i>
                <input type="text" id="searchTicket" placeholder="Buscar folio o alumno..." onkeyup="filterTable()"
                    class="w-full pl-8 pr-3 py-2 bg-white border border-zinc-200 rounded text-xs focus:border-zinc-900 outline-none transition-colors">
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto min-h-[400px]">
        <table class="w-full text-left">
            <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-medium">Folio / Asiento</th>
                    <th class="px-6 py-4 font-medium">Evento</th>
                    <th class="px-6 py-4 font-medium">Alumno Asignado</th>
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
    document.addEventListener('DOMContentLoaded', () => {
        loadTickets();
    });

    function loadTickets() {
        const eventId = document.getElementById('filterEvent').value;
        const tbody = document.getElementById('ticketsBody');

        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-zinc-400 italic">Actualizando...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'fetch_tickets');
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
            let btnColor = (uso === 'Disponible') ? 'bg-zinc-900 text-white hover:bg-zinc-700' : 'bg-zinc-200 text-zinc-500 hover:bg-zinc-300';
            let btnIcon = (uso === 'Disponible') ? 'fa-check' : 'fa-undo';
            
            html += `<tr class="hover:bg-zinc-50 transition-colors ticket-row" data-search="${t.alumno.toLowerCase()} ${t.folio_asiento}">
            <td class="px-6 py-4 font-mono font-bold text-lg text-zinc-900">#${String(t.folio_asiento).padStart(4, '0')}</td>
            <td class="px-6 py-4 text-zinc-600 font-medium">${t.evento}</td>
            <td class="px-6 py-4 font-bold text-zinc-800">${t.alumno}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider ${badgeClass}">${status}</span>
            </td>
            <td class="px-6 py-4">
                <button onclick="toggleStatus(${t.id})" class="px-3 py-1 rounded text-[10px] uppercase font-bold tracking-wider ${btnColor} transition-colors flex items-center gap-1">
                    <i class="fas ${btnIcon}"></i> ${uso}
                </button>
            </td>
            <td class="px-6 py-4 text-right text-zinc-400 font-mono text-xs">
                ${t.fecha_emision || '-'}
            </td>
        </tr>`;
        });
        
        tbody.innerHTML = html;
    }

    function filterTable() {
        const term = document.getElementById('searchTicket').value.toLowerCase();
        const rows = document.querySelectorAll('.ticket-row');
        let visible = 0;

        rows.forEach(row => {
            const text = row.dataset.search || '';
            if (text.includes(term)) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('ticketCount').innerText = visible;
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