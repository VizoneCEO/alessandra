<?php
require '../../back/db_connect.php';

// Obtener TODOS los ciclos escolares para el selector
$todos_ciclos = $conn->query("SELECT id, nombre_ciclo, estado FROM Ciclos_Escolares ORDER BY fecha_inicio DESC")->fetch_all(MYSQLI_ASSOC);

// Determinar el ciclo activo por default
$ciclo_activo_id = null;
foreach ($todos_ciclos as $ciclo) {
    if ($ciclo['estado'] == 'activo') {
        $ciclo_activo_id = $ciclo['id'];
        break;
    }
}
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Auditoría de Reprobación</h3>
    <p class="text-zinc-500 font-light text-sm">Vista en tiempo real de alumnos con promedio inferior a la escala aprobatoria dictada.</p>
</div>

<!-- Barra Principal: Filtros y Exportación -->
<div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-6 mb-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-end">
        
        <!-- Contexto: Ciclo y Calificación -->
        <div class="lg:col-span-8 flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <label class="block text-xs font-bold uppercase tracking-widest text-zinc-400 mb-2"><i class="fas fa-calendar-alt mr-1"></i> Ciclo Escolar</label>
                <select id="filtroCiclo" class="w-full text-sm border-b border-zinc-200 py-2 focus:border-zinc-900 focus:outline-none bg-transparent" onchange="fetchReprobados()">
                    <?php foreach ($todos_ciclos as $ciclo): ?>
                        <option value="<?php echo $ciclo['id']; ?>" <?php echo ($ciclo['id'] == $ciclo_activo_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ciclo['nombre_ciclo']) . ($ciclo['estado'] == 'activo' ? ' (Activo)' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-bold uppercase tracking-widest text-zinc-400 mb-2"><i class="fas fa-balance-scale-left mr-1"></i> Mínima Aprobatoria (0-100)</label>
                <input type="number" id="filtroMinCalificacion" value="7.0" step="0.1" class="w-full text-sm font-bold text-rose-600 border-b border-rose-200 py-2 focus:border-rose-500 focus:outline-none bg-transparent" oninput="debounceFetch()" placeholder="Ej. 7.0 o 70">
            </div>
        </div>

        <!-- Export Action -->
        <div class="lg:col-span-4 border-t lg:border-t-0 lg:border-l border-zinc-100 lg:pl-6 pt-4 lg:pt-0 flex justify-end">
            <button onclick="exportarTabla()" class="px-6 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold text-xs uppercase tracking-widest rounded hover:bg-emerald-100 transition-colors shadow-sm flex items-center justify-center w-full lg:w-auto">
                <i class="fas fa-file-excel mr-2 text-emerald-500"></i> Exportar a CSV
            </button>
        </div>
    </div>
</div>

<!-- Tabla de Resultados -->
<div class="bg-white border border-zinc-100 rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="tablaReprobadosHtml">
            <thead>
                <tr class="bg-zinc-50/50 border-b border-zinc-100 text-xs font-bold uppercase tracking-widest text-zinc-500">
                    <th class="px-6 py-4">Materia</th>
                    <th class="px-6 py-4">Profesor Asignado</th>
                    <th class="px-6 py-4">Alumno</th>
                    <th class="px-6 py-4 text-center">Promedio (AVG)</th>
                    <th class="px-6 py-4 text-center">Estatus</th>
                </tr>
            </thead>
            <tbody id="reprobadosBody" class="divide-y divide-zinc-50">
                <tr>
                    <td colspan="5" class="py-12 text-center text-zinc-400 text-sm">
                        <i class="fas fa-spinner fa-spin mr-2"></i> Cargando datos...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    let debounceTimer;

    function debounceFetch() {
        clearTimeout(debounceTimer);
        document.getElementById('reprobadosBody').innerHTML = '<tr><td colspan="5" class="py-12 text-center text-zinc-400 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i> Actualizando cálculos...</td></tr>';
        debounceTimer = setTimeout(fetchReprobados, 600); // Wait 600ms after user stops typing
    }

    function fetchReprobados() {
        let ciclo_id = document.getElementById('filtroCiclo').value;
        let calif_min = document.getElementById('filtroMinCalificacion').value;

        if (!ciclo_id || calif_min === '') return;

        let formData = new FormData();
        formData.append('action', 'fetch_reprobados');
        formData.append('ciclo_id', ciclo_id);
        formData.append('min_calificacion', calif_min);

        fetch('../../back/admin_actions_reportes.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            let tbody = document.getElementById('reprobadosBody');
            tbody.innerHTML = '';
            
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-rose-500 text-sm">${data.message}</td></tr>`;
                return;
            }

            if (data.reprobados.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-emerald-600 text-sm italic font-serif"><i class="fas fa-check-circle mr-2 mb-2 text-2xl block"></i>No se registran alumnos con promedio inferior a ${calif_min} en este ciclo.</td></tr>`;
                return;
            }

            data.reprobados.forEach(item => {
                let badge = `<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider">Reprobado</span>`;
                
                let tr = document.createElement('tr');
                tr.className = "hover:bg-zinc-50/50 transition-colors";
                tr.innerHTML = `
                    <td class="px-6 py-4 text-sm font-bold text-zinc-900">${item.nombre_materia}</td>
                    <td class="px-6 py-4 text-sm text-zinc-500">${item.profesor_nombre}</td>
                    <td class="px-6 py-4 text-sm font-medium text-zinc-700">${item.alumno_nombre}</td>
                    <td class="px-6 py-4 text-sm font-mono text-center font-bold text-rose-600">${parseFloat(item.promedio).toFixed(2)}</td>
                    <td class="px-6 py-4 text-center">${badge}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            document.getElementById('reprobadosBody').innerHTML = `<tr><td colspan="5" class="py-12 text-center text-rose-500 text-sm">Error de conexión con el servidor.</td></tr>`;
        });
    }

    function exportarTabla() {
        let tabla = document.getElementById("tablaReprobadosHtml");
        let th_str = "";
        let lineas = [];

        // Leer Thead
        let thead_cells = tabla.querySelectorAll('thead th');
        let thead_arr = [];
        thead_cells.forEach(c => thead_arr.push('"' + c.innerText.trim() + '"'));
        lineas.push(thead_arr.join(","));

        // Leer Tbody
        let tr_body = tabla.querySelectorAll('tbody tr');
        let empty_state = false;
        tr_body.forEach(tr => {
            if(tr.cells.length === 1) empty_state = true;
            if(!empty_state){
                let arr = [];
                for(let i=0; i<tr.cells.length; i++){
                    let text = tr.cells[i].innerText.replace(/"/g, '""').trim();
                    arr.push('"' + text + '"');
                }
                lineas.push(arr.join(","));
            }
        });

        if(empty_state) {
            Swal.fire('Atención', 'No hay datos para exportar.', 'info');
            return;
        }

        let csvContent = "data:text/csv;charset=utf-8,\uFEFF" + lineas.join("\n");
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "auditoria_reprobados.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('DOMContentLoaded', fetchReprobados);
</script>
