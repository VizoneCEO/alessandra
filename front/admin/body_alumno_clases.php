<?php
// --- 1. OBTENER DATOS INICIALES ---
require '../../back/db_connect.php';

$alumno_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$categorias_principales = ['Actividades', 'Asistencia', 'Examenes'];

$nombre_alumno = "Alumno Desconocido";
$res_alumno = $conn->query("SELECT nombre_completo FROM Usuarios WHERE id = $alumno_id");
if ($res_alumno && $res_alumno->num_rows > 0) {
    $nombre_alumno = $res_alumno->fetch_assoc()['nombre_completo'];
}

if (!function_exists('getDetalleCalificacion')) {
    function getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales) {
        $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
        if ($clase_id_result->num_rows == 0) {
            return ['final' => 0, 'promedios_parciales' => [], 'items_desglose' => [], 'calif_por_parcial' => [1=>0, 2=>0, 3=>0]];
        }
        $clase_id = $clase_id_result->fetch_assoc()['clase_id'];

        $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_id")->fetch_all(MYSQLI_ASSOC);
        $ponderaciones = [];
        foreach ($categorias_db as $cat) {
            $ponderaciones[$cat['nombre_categoria']] = $cat;
        }

        $data_return = [
            'final' => 0.0,
            'promedios_parciales' => [],
            'items_desglose' => [],
            'calif_por_parcial' => [1 => 0.0, 2 => 0.0, 3 => 0.0]
        ];

        foreach ($categorias_principales as $cat_nombre) {
            $cat_id = $ponderaciones[$cat_nombre]['id'] ?? 0;

            for ($p = 1; $p <= 3; $p++) {
                $data_return['promedios_parciales'][$cat_nombre][$p] = 0;
                $data_return['items_desglose'][$cat_nombre][$p] = [];
            }

            if ($cat_id > 0) {
                $sql_items = "SELECT 
                                a.parcial, 
                                a.nombre_actividad, 
                                c.calificacion_obtenida 
                              FROM Calificaciones c
                              JOIN Actividades_Evaluables a ON c.actividad_id = a.id
                              WHERE c.inscripcion_id = ? AND a.categoria_id = ?
                              ORDER BY a.parcial, a.id";

                $stmt = $conn->prepare($sql_items);
                $stmt->bind_param("ii", $inscripcion_id, $cat_id);
                $stmt->execute();
                $result_items = $stmt->get_result();

                $califs_por_parcial = [1 => [], 2 => [], 3 => []];

                while ($item = $result_items->fetch_assoc()) {
                    $parcial = $item['parcial'];
                    $calif = (float)$item['calificacion_obtenida'];

                    $data_return['items_desglose'][$cat_nombre][$parcial][] = [
                        'nombre' => $item['nombre_actividad'],
                        'calif' => $calif
                    ];
                    $califs_por_parcial[$parcial][] = $calif;
                }
                $stmt->close();

                for ($p = 1; $p <= 3; $p++) {
                    if (count($califs_por_parcial[$p]) > 0) {
                        $data_return['promedios_parciales'][$cat_nombre][$p] = array_sum($califs_por_parcial[$p]) / count($califs_por_parcial[$p]);
                    }
                }
            }
        }

        for ($p = 1; $p <= 3; $p++) {
            foreach ($categorias_principales as $cat_nombre) {
                $ponderacion = ($ponderaciones[$cat_nombre]['ponderacion'] ?? 0) / 100;
                $promedio = $data_return['promedios_parciales'][$cat_nombre][$p] ?? 0;
                $data_return['calif_por_parcial'][$p] += ($promedio * $ponderacion);
            }
        }

        $suma_parciales = $data_return['calif_por_parcial'][1] + $data_return['calif_por_parcial'][2] + $data_return['calif_por_parcial'][3];
        if ($suma_parciales > 0) {
            $data_return['final'] = $suma_parciales / 3;
        } else {
            $data_return['final'] = 0.0;
        }

        return $data_return;
    }
}

$ciclo_activo = $conn->query("SELECT id FROM Ciclos_Escolares WHERE estado = 'activo' LIMIT 1")->fetch_assoc();
$mis_clases = [];

if ($ciclo_activo) {
    $ciclo_activo_id = $ciclo_activo['id'];
    $sql_clases = "SELECT 
                        c.id AS clase_id,
                        m.nombre_materia, 
                        u.nombre_completo AS profesor_nombre,
                        i.id AS inscripcion_id
                   FROM Inscripciones i
                   JOIN Clases c ON i.clase_id = c.id
                   JOIN Materias m ON c.materia_id = m.id
                   JOIN Usuarios u ON c.profesor_id = u.id
                   WHERE i.alumno_id = $alumno_id AND c.ciclo_id = $ciclo_activo_id
                   ORDER BY m.nombre_materia";
    $mis_clases = $conn->query($sql_clases)->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="mb-8 flex justify-between items-start">
    <div>
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Historial Académico</h3>
        <p class="text-zinc-500 font-light text-sm">Alumno: <strong><?php echo htmlspecialchars($nombre_alumno); ?></strong></p>
    </div>
    <a href="dashboard.php?page=usuarios" class="px-4 py-2 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest rounded hover:bg-zinc-800 transition-colors">
        <i class="fas fa-arrow-left mr-2"></i> Volver
    </a>
</div>

<?php if (!$ciclo_activo): ?>
    <div class="p-4 bg-amber-50 text-amber-800 border-l-4 border-amber-500 rounded text-sm">No hay ningún ciclo escolar activo en este momento.</div>
<?php elseif (empty($mis_clases)): ?>
    <div class="p-4 bg-white shadow-sm rounded border-l-4 border-zinc-300 text-zinc-600 text-sm">El alumno no está inscrito a ninguna clase en el ciclo escolar activo.</div>
<?php else: ?>
    <!-- GRID DE TARJETAS TAILWIND -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($mis_clases as $clase): ?>
            <?php
            $data_calificacion = getDetalleCalificacion($conn, $clase['inscripcion_id'], $categorias_principales);
            $calif_final = $data_calificacion['final'];
            $promedios_parciales = $data_calificacion['promedios_parciales'];
            $items_desglose = $data_calificacion['items_desglose'];
            $calif_por_parcial = $data_calificacion['calif_por_parcial'];
            $final_calif_class = ($calif_final < 7.5) ? 'text-rose-600' : 'text-emerald-700'; 
            
            $cl_id = intval($clase['clase_id']);
            $cat_db_w = $conn->query("SELECT nombre_categoria, ponderacion FROM Categorias_Calificacion WHERE clase_id = $cl_id")->fetch_all(MYSQLI_ASSOC);
            $p_act = 40; $p_asi = 20; $p_exa = 40;
            if (count($cat_db_w) > 0) {
                foreach($cat_db_w as $c_w) {
                    if ($c_w['nombre_categoria'] == 'Actividades') $p_act = floatval($c_w['ponderacion']);
                    if ($c_w['nombre_categoria'] == 'Asistencia') $p_asi = floatval($c_w['ponderacion']);
                    if ($c_w['nombre_categoria'] == 'Examenes') $p_exa = floatval($c_w['ponderacion']);
                }
            }
            ?>
            <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-shadow duration-300 flex flex-col h-full overflow-hidden border border-zinc-50">
                <div class="p-6 border-b border-zinc-50">
                    <h5 class="font-serif text-xl font-bold text-zinc-900 mb-1 leading-tight">
                        <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                    </h5>
                    <p class="text-sm italic text-zinc-500 font-light flex items-center">
                        <i class="fas fa-chalkboard-teacher mr-2 text-zinc-300"></i>
                        <?php echo htmlspecialchars($clase['profesor_nombre']); ?>
                    </p>
                    <div class="mt-3">
                        <button class="px-3 py-1.5 bg-orange-50 text-orange-600 border border-orange-200 text-[10px] font-bold uppercase tracking-widest rounded hover:bg-orange-100 hover:text-orange-700 transition-colors shadow-sm"
                                onclick="openExtraordinarioModal(<?php echo $clase['clase_id']; ?>, <?php echo $clase['inscripcion_id']; ?>, '<?php echo htmlspecialchars(addslashes($clase['nombre_materia'])); ?>', <?php echo $p_act; ?>, <?php echo $p_asi; ?>, <?php echo $p_exa; ?>)">
                            <i class="fas fa-magic text-orange-400 mr-1"></i> Calificación Extraordinaria
                        </button>
                    </div>>
                </div>
                <div class="flex-1 p-0">
                    <table class="w-full text-left text-sm">
                        <?php foreach ($categorias_principales as $cat_nombre): ?>
                            <?php
                            $cat_icon = ['Actividades' => 'fa-tasks', 'Asistencia' => 'fa-user-check', 'Examenes' => 'fa-file-alt'][$cat_nombre];
                            ?>
                            <tr class="bg-zinc-50/50 text-xs uppercase tracking-wide text-zinc-400 font-semibold">
                                <td colspan="3" class="px-6 py-2 pt-4">
                                    <i class="fas <?php echo $cat_icon; ?> mr-1 opacity-70"></i> 
                                    <?php echo $cat_nombre; ?>
                                </td>
                            </tr>
                            <?php for ($p = 1; $p <= 3; $p++): ?>
                                <?php
                                $promedio = $promedios_parciales[$cat_nombre][$p] ?? 0;
                                $items = $items_desglose[$cat_nombre][$p] ?? [];
                                $titulo_modal = "$cat_nombre - Parcial $p";
                                ?>
                                <tr class="border-b border-zinc-50 last:border-0 hover:bg-zinc-50 transition-colors">
                                    <td class="px-6 py-2 text-zinc-600 font-light w-1/3">Parcial <?php echo $p; ?></td>
                                    <td class="px-6 py-2 text-center w-1/3">
                                        <button type="button" 
                                                class="text-zinc-300 hover:text-zinc-800 transition-colors focus:outline-none"
                                                data-bs-target="#califModal"
                                                data-titulo="<?php echo htmlspecialchars($titulo_modal); ?>"
                                                data-items='<?php echo htmlspecialchars(json_encode($items), ENT_QUOTES, "UTF-8"); ?>'
                                                <?php echo empty($items) ? 'disabled' : ''; ?>
                                                title="Ver Detalles">
                                            <i class="fas fa-eye <?php echo empty($items) ? 'opacity-30 cursor-not-allowed' : ''; ?>"></i>
                                        </button>
                                    </td>
                                    <td class="px-6 py-2 text-right">
                                        <span class="font-mono text-zinc-700 font-medium">
                                            <?php echo number_format($promedio, 1); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        <?php endforeach; ?>
                         <tr class="bg-zinc-100/50 mt-2">
                             <td colspan="3" class="px-6 py-3 border-t border-zinc-100">
                                 <p class="text-xs uppercase tracking-widest text-zinc-400 font-bold mb-2">Promedios Parciales</p>
                                 <div class="flex justify-between text-xs font-mono text-zinc-600">
                                     <span>P1: <strong class="text-zinc-900"><?php echo number_format($calif_por_parcial[1], 1); ?></strong></span>
                                     <span>P2: <strong class="text-zinc-900"><?php echo number_format($calif_por_parcial[2], 1); ?></strong></span>
                                     <span>P3: <strong class="text-zinc-900"><?php echo number_format($calif_por_parcial[3], 1); ?></strong></span>
                                 </div>
                             </td>
                         </tr>
                    </table>
                </div>
                <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex justify-between items-center">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold uppercase tracking-widest text-zinc-400">Promedio Final</span>
                        <span class="text-[10px] text-emerald-600 font-medium mt-1 leading-tight max-w-[150px]">Promedio acumulado de los 3 parciales.</span>
                    </div>
                    <span class="text-3xl font-serif font-bold <?php echo $final_calif_class; ?>">
                        <?php echo number_format($calif_final, 1); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODAL CREADO SIN DEPENDENCIA DE BOOTSTRAP PARA LA ADMIN -->
<div id="califModalVanilla" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 opacity-100">
        <div class="px-6 py-4 border-b border-zinc-100 flex justify-between items-center bg-zinc-950 text-white">
            <h5 class="font-serif italic text-lg" id="modalTituloVanilla">Desglose</h5>
            <button type="button" onclick="closeDetalleModal()" class="text-zinc-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-0 overflow-y-auto max-h-96">
            <ul class="list-none m-0 p-0" id="modalContenidoVanilla">
            </ul>
        </div>
        <div class="px-6 py-4 bg-zinc-50 flex justify-end gap-3 border-t border-zinc-100">
            <button type="button" onclick="closeDetalleModal()" class="px-4 py-2 bg-white border border-zinc-300 text-zinc-600 text-xs uppercase tracking-widest hover:bg-zinc-100 rounded transition">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL AJUSTE EXTRAORDINARIO -->
<div id="extraordinarioModal" class="fixed inset-0 z-[60] hidden bg-zinc-900/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in-up">
        <div class="px-6 py-5 border-b border-zinc-100 bg-orange-50 flex justify-between items-center">
            <div>
                <h3 class="font-serif italic text-xl text-orange-800">Calificación Extraordinaria</h3>
                <p class="text-xs text-orange-600 mt-1 font-light block" id="extraordinarioMateriaLabel">Materia XYZ</p>
            </div>
            <button onclick="closeExtraordinarioModal()" class="text-orange-400 hover:text-orange-700"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="extraordinarioForm" onsubmit="submitExtraordinario(event)" class="px-6 py-4">
            <input type="hidden" id="extClaseId" name="clase_id">
            <input type="hidden" id="extInscripcionId" name="inscripcion_id">
            
            <!-- ALERTA DE PESOS GLOBALES -->
            <div class="mb-4 bg-rose-50 border border-rose-200 p-3 rounded flex items-start">
                <i class="fas fa-exclamation-circle text-rose-500 mt-0.5 mr-2"></i>
                <p class="text-[10px] uppercase tracking-wider font-bold text-rose-700">Las ponderaciones a continuación afectarán a <u>TODA LA CLASE</u> globalmente. Las calificaciones serán solo para el alumno seleccionado.</p>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-1">Periodo a Evaluar *</label>
                <select id="extParcial" name="parcial" class="w-full px-3 py-2 border border-zinc-200 rounded text-sm focus:border-zinc-900 outline-none" required>
                    <option value="">Seleccione Parcial</option>
                    <option value="1">Parcial 1</option>
                    <option value="2">Parcial 2</option>
                    <option value="3">Parcial 3</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-2 mb-4 p-3 bg-zinc-50 border border-zinc-100 rounded-lg">
                <div class="col-span-3 pb-2 mb-2 border-b border-zinc-200 text-xs font-bold uppercase tracking-wider text-zinc-600">Ponderaciones (Suma = 100%)</div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold">% Actividades</label>
                    <input type="number" step="0.01" id="extPesoAct" name="pesos[Actividades]" class="w-full px-2 py-1 mt-1 border border-zinc-200 rounded text-sm text-center ext-peso" required>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold">% Asistencia</label>
                    <input type="number" step="0.01" id="extPesoAsi" name="pesos[Asistencia]" class="w-full px-2 py-1 mt-1 border border-zinc-200 rounded text-sm text-center ext-peso" required>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 font-bold">% Exámenes</label>
                    <input type="number" step="0.01" id="extPesoExa" name="pesos[Examenes]" class="w-full px-2 py-1 mt-1 border border-zinc-200 rounded text-sm text-center ext-peso" required>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 mb-6">
                <div class="col-span-3 pb-2 mb-2 text-xs font-bold uppercase tracking-wider text-zinc-600">Calificaciones (0 a 10) *</div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 mb-1">Nota Act./Tareas</label>
                    <input type="number" step="any" min="0" max="100" name="notas[Actividades]" class="w-full px-2 py-2 border-b-2 border-orange-200 focus:border-zinc-900 bg-transparent text-center font-bold outline-none font-mono" required>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 mb-1">Nota Asistencia</label>
                    <input type="number" step="any" min="0" max="100" name="notas[Asistencia]" class="w-full px-2 py-2 border-b-2 border-orange-200 focus:border-zinc-900 bg-transparent text-center font-bold outline-none font-mono" required>
                </div>
                <div>
                    <label class="block text-[10px] uppercase text-zinc-500 mb-1">Nota Exámenes</label>
                    <input type="number" step="any" min="0" max="100" name="notas[Examenes]" class="w-full px-2 py-2 border-b-2 border-orange-200 focus:border-zinc-900 bg-transparent text-center font-bold outline-none font-mono" required>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-100">
                <button type="button" onclick="closeExtraordinarioModal()" class="px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-500 rounded hover:text-zinc-900 transition-colors">Cancelar</button>
                <button type="submit" id="extSubmitBtn" class="px-6 py-2 bg-orange-600 text-white font-bold text-xs uppercase tracking-widest rounded shadow-sm hover:bg-orange-700 transition">Generar Extraordinario</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDetalleModal(titulo, itemsJson) {
        document.getElementById('modalTituloVanilla').textContent = titulo;
        var modalBodyList = document.getElementById('modalContenidoVanilla');
        modalBodyList.innerHTML = '';
        
        var items = JSON.parse(itemsJson);
        if (items.length > 0) {
            items.forEach(function(item) {
                var li = document.createElement('li');
                li.className = 'flex justify-between items-center px-6 py-4 border-b border-zinc-100 last:border-0 hover:bg-zinc-50';

                var nombreSpan = document.createElement('span');
                nombreSpan.className = 'text-zinc-700 font-light text-sm';
                nombreSpan.textContent = item.nombre;

                var califSpan = document.createElement('span');
                califSpan.className = 'font-mono font-bold text-zinc-900 text-sm';
                califSpan.textContent = parseFloat(item.calif).toFixed(1);

                li.appendChild(nombreSpan);
                li.appendChild(califSpan);
                modalBodyList.appendChild(li);
            });
        } else {
            var li = document.createElement('li');
            li.className = 'px-6 py-4 text-center text-zinc-400 italic text-sm';
            li.textContent = 'No hay actividades registradas para este parcial.';
            modalBodyList.appendChild(li);
        }
        
        document.getElementById('califModalVanilla').classList.remove('hidden');
    }
    
    function closeDetalleModal() {
        document.getElementById('califModalVanilla').classList.add('hidden');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var buttons = document.querySelectorAll('button[data-bs-target="#califModal"]');
        buttons.forEach(function(btn) {
            btn.onclick = function() {
                var titulo = this.getAttribute('data-titulo');
                var itemsJson = this.getAttribute('data-items');
                openDetalleModal(titulo, itemsJson);
            };
        });
    });

    // --- MODAL EXTRAORDINARIO LOGIC ---
    function openExtraordinarioModal(claseId, inscripcionId, materiaNombre, wAct, wAsi, wExa) {
        document.getElementById('extClaseId').value = claseId;
        document.getElementById('extInscripcionId').value = inscripcionId;
        document.getElementById('extraordinarioMateriaLabel').innerText = materiaNombre;
        
        // Load calculated or configured weights
        document.getElementById('extPesoAct').value = wAct;
        document.getElementById('extPesoAsi').value = wAsi;
        document.getElementById('extPesoExa').value = wExa;

        // Reset notes form
        let inputs = document.querySelectorAll('input[name^="notas["]');
        inputs.forEach(el => el.value = '');
        document.getElementById('extParcial').value = '';

        document.getElementById('extraordinarioModal').classList.remove('hidden');
    }

    function closeExtraordinarioModal() {
        document.getElementById('extraordinarioModal').classList.add('hidden');
    }

    function submitExtraordinario(e) {
        e.preventDefault();
        
        // Custom sum validate
        let wAct = parseFloat(document.getElementById('extPesoAct').value) || 0;
        let wAsi = parseFloat(document.getElementById('extPesoAsi').value) || 0;
        let wExa = parseFloat(document.getElementById('extPesoExa').value) || 0;
        let totalW = wAct + wAsi + wExa;
        
        if (Math.abs(totalW - 100) > 0.01) {
            Swal.fire({icon: 'error', title: 'Error de Ponderación', text: 'La suma de los % debe ser 100.'});
            return;
        }

        let formData = new FormData(document.getElementById('extraordinarioForm'));
        formData.append('action', 'ajuste_extraordinario');

        document.getElementById('extSubmitBtn').disabled = true;
        document.getElementById('extSubmitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';

        fetch('../../back/admin_actions_calificaciones.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('¡Éxito!', data.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
                document.getElementById('extSubmitBtn').disabled = false;
                document.getElementById('extSubmitBtn').innerHTML = 'Generar Extraordinario';
            }
        })
        .catch(err => {
            Swal.fire('Error Crítico', 'Ha ocurrido un error en la comunicación con el servidor.', 'error');
            document.getElementById('extSubmitBtn').disabled = false;
            document.getElementById('extSubmitBtn').innerHTML = 'Generar Extraordinario';
            console.error(err);
        });
    }
</script>
