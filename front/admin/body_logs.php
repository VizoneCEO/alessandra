<?php
// front/admin/body_logs.php
?>
<div class="mb-8 flex flex-col md:flex-row justify-between items-end animate-fade-in-up">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Bitácora de Actividades</h3>
        <p class="text-zinc-500 font-light text-sm">Registro detallado de acciones y eventos del sistema.</p>
    </div>
    <div class="flex gap-4 mt-4 md:mt-0">
        <div class="bg-zinc-50 border border-zinc-200 text-zinc-600 px-4 py-2 rounded-lg text-right">
            <p class="text-[10px] uppercase tracking-widest opacity-70">Total Registros</p>
            <p class="text-xl font-serif font-bold text-zinc-900" id="totalLogs">0</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-200 p-6 mb-8 animate-fade-in-up">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <!-- Date Range -->
        <div>
            <label class="block text-[10px] uppercase tracking-wider text-zinc-500 mb-1 font-bold">Desde</label>
            <input type="date" id="filterDateStart"
                class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none">
        </div>
        <div>
            <label class="block text-[10px] uppercase tracking-wider text-zinc-500 mb-1 font-bold">Hasta</label>
            <input type="date" id="filterDateEnd"
                class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none">
        </div>

        <!-- User -->
        <div>
            <label class="block text-[10px] uppercase tracking-wider text-zinc-500 mb-1 font-bold">Usuario</label>
            <select id="filterUser"
                class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-white">
                <option value="">Todos</option>
                <!-- Filled via JS -->
            </select>
        </div>

        <!-- Action -->
        <div>
            <label class="block text-[10px] uppercase tracking-wider text-zinc-500 mb-1 font-bold">Acción</label>
            <select id="filterAction"
                class="w-full border border-zinc-200 rounded px-3 py-2 text-sm focus:border-zinc-900 outline-none bg-white">
                <option value="">Todas</option>
                <!-- Filled via JS -->
            </select>
        </div>

        <!-- Search -->
        <div>
            <label class="block text-[10px] uppercase tracking-wider text-zinc-500 mb-1 font-bold">Búsqueda</label>
            <div class="relative">
                <input type="text" id="filterSearch" placeholder="Descripción..."
                    class="w-full border border-zinc-200 rounded pl-8 pr-3 py-2 text-sm focus:border-zinc-900 outline-none">
                <i class="fas fa-search absolute left-3 top-2.5 text-zinc-400 text-xs"></i>
            </div>
        </div>
    </div>
    <div class="mt-4 flex justify-end gap-2">
        <button onclick="resetFilters()"
            class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-400 hover:text-zinc-600 transition-colors">Limpiar</button>
        <button onclick="loadLogs(1)"
            class="px-6 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-zinc-700 transition-colors shadow-lg shadow-zinc-900/10">Filtrar
            Resultados</button>
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden animate-fade-in-up">
    <div class="overflow-x-auto min-h-[400px]">
        <table class="w-full text-left">
            <thead class="bg-zinc-50 text-zinc-500 text-[10px] uppercase tracking-wider border-b border-zinc-100">
                <tr>
                    <th class="px-6 py-3 font-bold">Fecha / Hora</th>
                    <th class="px-6 py-3 font-bold">Usuario</th>
                    <th class="px-6 py-3 font-bold">Acción</th>
                    <th class="px-6 py-3 font-bold">Descripción</th>
                    <th class="px-6 py-3 font-bold">IP</th>
                </tr>
            </thead>
            <tbody id="logsBody" class="divide-y divide-zinc-50 text-sm">
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-zinc-400 italic">Cargando registros...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="px-6 py-4 border-t border-zinc-100 bg-zinc-50 flex justify-between items-center"
        id="paginationControls">
        <span class="text-xs text-zinc-500" id="pageInfo">Página 1 de 1</span>
        <div class="flex gap-2">
            <button id="btnPrev" onclick="changePage(-1)" disabled
                class="w-8 h-8 flex items-center justify-center rounded border border-zinc-200 bg-white text-zinc-400 hover:text-zinc-900 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"><i
                    class="fas fa-chevron-left"></i></button>
            <button id="btnNext" onclick="changePage(1)" disabled
                class="w-8 h-8 flex items-center justify-center rounded border border-zinc-200 bg-white text-zinc-400 hover:text-zinc-900 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"><i
                    class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<script>
    let currentPage = 1;
    let totalPages = 1;

    document.addEventListener('DOMContentLoaded', () => {
        loadUsers();
        loadActions();
        loadLogs(1);
    });

    function loadUsers() {
        fetch('../../back/admin_actions_logs.php?action=fetch_users')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const sel = document.getElementById('filterUser');
                    data.data.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.text = u.nombre_completo;
                        sel.appendChild(opt);
                    });
                }
            });
    }

    function loadActions() {
        fetch('../../back/admin_actions_logs.php?action=fetch_actions')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const sel = document.getElementById('filterAction');
                    data.data.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a;
                        opt.text = a;
                        sel.appendChild(opt);
                    });
                }
            });
    }

    function loadLogs(page) {
        const tbody = document.getElementById('logsBody');
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-zinc-400 flex justify-center items-center gap-2"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'fetch_logs');
        formData.append('page', page);

        const user = document.getElementById('filterUser').value;
        const start = document.getElementById('filterDateStart').value;
        const end = document.getElementById('filterDateEnd').value;
        const actionType = document.getElementById('filterAction').value;
        const search = document.getElementById('filterSearch').value;

        if (user) formData.append('user_id', user);
        if (start) formData.append('date_start', start);
        if (end) formData.append('date_end', end);
        if (actionType) formData.append('action_type', actionType);
        if (search) formData.append('search', search);

        fetch('../../back/admin_actions_logs.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderLogs(data.data.logs);
                    updatePagination(data.data.pagination);
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500">${data.message}</td></tr>`;
                }
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500">Error de conexión</td></tr>`;
                console.error(err);
            });
    }

    function renderLogs(logs) {
        const tbody = document.getElementById('logsBody');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-zinc-400 italic">No se encontraron registros.</td></tr>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            const date = new Date(log.fecha);
            const dateStr = date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
            const timeStr = date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

            // Color coding for actions
            let badgeColor = 'bg-zinc-100 text-zinc-600';
            const acc = log.accion.toUpperCase();
            if (acc.includes('DELETE') || acc.includes('ELIMINAR') || acc.includes('CANCEL')) badgeColor = 'bg-rose-100 text-rose-700';
            else if (acc.includes('CREATE') || acc.includes('INSERT') || acc.includes('ADD') || acc.includes('AGREGAR')) badgeColor = 'bg-emerald-100 text-emerald-700';
            else if (acc.includes('UPDATE') || acc.includes('EDIT') || acc.includes('MODIFICAR')) badgeColor = 'bg-amber-100 text-amber-700';
            else if (acc.includes('LOGIN')) badgeColor = 'bg-blue-100 text-blue-700';

            html += `<tr class="hover:bg-zinc-50 transition-colors group">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-xs font-bold text-zinc-900">${dateStr}</div>
                    <div class="text-[10px] text-zinc-400 font-mono">${timeStr}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded-full bg-zinc-200 flex items-center justify-center text-[10px] text-zinc-500 font-bold mr-2">
                            ${log.usuario_nombre ? log.usuario_nombre.charAt(0) : '?'}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-zinc-900">${log.usuario_nombre || 'Desconocido'}</p>
                            <p class="text-[10px] text-zinc-400">ID: ${log.usuario_id}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded text-[10px] uppercase font-bold tracking-wider ${badgeColor}">${log.accion}</span>
                </td>
                <td class="px-6 py-4">
                    <p class="text-xs text-zinc-600 leading-relaxed">${log.detalle || ''}</p>
                </td>
                <td class="px-6 py-4 text-xs font-mono text-zinc-400">
                    ${log.ip_address || '-'}
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function updatePagination(pagination) {
        currentPage = pagination.current_page;
        totalPages = pagination.total_pages;
        document.getElementById('totalLogs').innerText = pagination.total_records;
        document.getElementById('pageInfo').innerText = `Página ${currentPage} de ${totalPages}`;

        document.getElementById('btnPrev').disabled = (currentPage <= 1);
        document.getElementById('btnNext').disabled = (currentPage >= totalPages);
    }

    function changePage(delta) {
        if ((delta === -1 && currentPage > 1) || (delta === 1 && currentPage < totalPages)) {
            loadLogs(currentPage + delta);
        }
    }

    function resetFilters() {
        document.getElementById('filterDateStart').value = '';
        document.getElementById('filterDateEnd').value = '';
        document.getElementById('filterUser').value = '';
        document.getElementById('filterAction').value = '';
        document.getElementById('filterSearch').value = '';
        loadLogs(1);
    }

    // Auto-search on enter
    document.getElementById('filterSearch').addEventListener('keyup', (e) => {
        if (e.key === 'Enter') loadLogs(1);
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