<?php
if (!isset($_SESSION['user_id'])) {
    exit();
}

require_once '../../back/db_connect.php';
$usuario_id = $_SESSION['user_id'];

// Inicializar variables vacías
$c = [
    'curp' => '', 'fecha_nacimiento' => '', 'genero' => '', 'escolaridad' => '', 'ocupacion' => '',
    'calle_numero' => '', 'colonia' => '', 'codigo_postal' => '', 'municipio_alcaldia' => '', 'estado_republica' => '',
    'telefono_celular' => '', 'telefono_fijo' => '', 'facebook' => '', 'instagram' => '', 'tiktok' => '',
    'tipo_sangre' => '', 'padece_enfermedad' => 0, 'enfermedad_descripcion' => '', 'enfermedad_sintomas' => '',
    'requiere_medicamentos' => 0, 'medicamentos_descripcion' => '', 'alergias_medicamentos' => '',
    'situacion_personal_docente' => '', 'protocolos_emergencia' => '',
    'tutor_nombre' => '', 'tutor_telefono' => '', 'tutor_ocupacion' => ''
];

$sql = "SELECT c.*, u.CURP as curp_usuario FROM Usuarios u LEFT JOIN Contacto_Alumnos c ON u.id = c.usuario_id WHERE u.id = $usuario_id LIMIT 1";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    foreach ($c as $key => $val) {
        if (isset($row[$key])) {
            $c[$key] = htmlspecialchars($row[$key]);
        }
    }
    $c['curp'] = isset($row['curp_usuario']) ? htmlspecialchars($row['curp_usuario']) : '';
    // Fix para booleanos
    $c['padece_enfermedad'] = $row['padece_enfermedad'] ? 1 : 0;
    $c['requiere_medicamentos'] = $row['requiere_medicamentos'] ? 1 : 0;
    $c['tiene_alergias'] = !empty($row['alergias_medicamentos']) ? 1 : 0;
} else {
    $c['tiene_alergias'] = 0;
}

// Lógica de Completitud
$tab1_ok = (!empty($c['curp']) && !empty($c['fecha_nacimiento']) && !empty($c['genero']));
$tab2_ok = (!empty($c['calle_numero']) && !empty($c['colonia']) && !empty($c['codigo_postal']) && !empty($c['municipio_alcaldia']) && !empty($c['estado_republica']));
$tab3_ok = (!empty($c['telefono_celular']));
$tab4_ok = (!empty($c['tipo_sangre']));
$tab5_ok = (!empty($c['tutor_nombre']) && !empty($c['tutor_telefono']));

function checkIcon($isOk) {
    if ($isOk) {
        return '<i class="fas fa-check-circle text-emerald-500 ml-2 shadow-sm rounded-full bg-white"></i>';
    } else {
        return '<i class="fas fa-exclamation-circle text-rose-400 ml-2 shadow-sm rounded-full bg-white"></i>';
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-zinc-100 p-8 relative overflow-hidden">
        <h1 class="font-serif text-3xl text-slate-900 mb-2">Perfil de Contacto Completo</h1>
        <p class="text-slate-500 font-light">Completa tu información personal, médica y de contacto para que estemos preparados ante cualquier eventualidad.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden flex flex-col md:flex-row">
    
    <!-- Sidebar Tabs -->
    <div class="w-full md:w-1/4 bg-zinc-50 border-r border-zinc-100 p-4">
        <nav class="flex flex-col space-y-2" id="tabs-nav">
            <button onclick="openTab('tab1')" id="btn-tab1" class="tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-bold rounded-lg transition-colors bg-zinc-900 text-white shadow-md">
                <span><i class="fas fa-id-card w-5 text-fuchsia-400"></i> Identidad</span> <?php echo checkIcon($tab1_ok); ?>
            </button>
            <button onclick="openTab('tab2')" id="btn-tab2" class="tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-600 rounded-lg hover:bg-zinc-200 transition-colors">
                <span><i class="fas fa-map-marker-alt w-5 text-indigo-400"></i> Localización</span> <?php echo checkIcon($tab2_ok); ?>
            </button>
            <button onclick="openTab('tab3')" id="btn-tab3" class="tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-600 rounded-lg hover:bg-zinc-200 transition-colors">
                <span><i class="fas fa-mobile-alt w-5 text-blue-400"></i> Contacto</span> <?php echo checkIcon($tab3_ok); ?>
            </button>
            <button onclick="openTab('tab4')" id="btn-tab4" class="tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-600 rounded-lg hover:bg-zinc-200 transition-colors">
                <span><i class="fas fa-briefcase-medical w-5 text-rose-500"></i> Médico <span class="text-xs text-rose-500 font-bold ml-1">*Crítico</span></span> <?php echo checkIcon($tab4_ok); ?>
            </button>
            <button onclick="openTab('tab5')" id="btn-tab5" class="tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-600 rounded-lg hover:bg-zinc-200 transition-colors">
                <span><i class="fas fa-user-friends w-5 text-amber-500"></i> Tutor</span> <?php echo checkIcon($tab5_ok); ?>
            </button>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="w-full md:w-3/4 p-6 lg:p-10">
        
        <!-- TAB 1: IDENTIDAD -->
        <div id="tab1" class="tab-content relative">
            <h3 class="text-xl font-bold text-slate-800 mb-6 border-b border-zinc-100 pb-2">Identidad y Demografía</h3>
            <form id="form-tab1" onsubmit="saveTab(event, 'tab1')">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">CURP <span class="text-[10px] lowercase font-normal">(Bloqueado por sistema)</span></label>
                        <input type="text" name="curp" value="<?php echo $c['curp']; ?>" readonly class="w-full px-4 py-2 bg-slate-200 text-slate-500 cursor-not-allowed border border-slate-200 rounded-lg focus:outline-none transition-all uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Fecha de Nacimiento *</label>
                        <input type="date" name="fecha_nacimiento" value="<?php echo $c['fecha_nacimiento']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Género *</label>
                        <select name="genero" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                            <option value="">Seleccione...</option>
                            <option value="Masculino" <?php echo $c['genero']=='Masculino'?'selected':''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo $c['genero']=='Femenino'?'selected':''; ?>>Femenino</option>
                            <option value="Otro" <?php echo $c['genero']=='Otro'?'selected':''; ?>>Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Escolaridad</label>
                        <input type="text" name="escolaridad" value="<?php echo $c['escolaridad']; ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Ocupación</label>
                        <input type="text" name="ocupacion" value="<?php echo $c['ocupacion']; ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-zinc-900 text-white font-bold text-sm rounded-lg shadow hover:bg-zinc-800 transition-all">
                        <i class="fas fa-save mr-2"></i> Guardar Identidad
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 2: LOCALIZACIÓN -->
        <div id="tab2" class="tab-content hidden relative">
            <h3 class="text-xl font-bold text-slate-800 mb-6 border-b border-zinc-100 pb-2">Localización Residencial</h3>
            <form id="form-tab2" onsubmit="saveTab(event, 'tab2')">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Calle y Número *</label>
                        <input type="text" name="calle_numero" value="<?php echo $c['calle_numero']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Colonia *</label>
                        <input type="text" name="colonia" value="<?php echo $c['colonia']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Código Postal *</label>
                        <input type="text" name="codigo_postal" value="<?php echo $c['codigo_postal']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Alcaldía / Municipio *</label>
                        <input type="text" name="municipio_alcaldia" value="<?php echo $c['municipio_alcaldia']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Estado *</label>
                        <input type="text" name="estado_republica" value="<?php echo $c['estado_republica']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-zinc-900 text-white font-bold text-sm rounded-lg shadow hover:bg-zinc-800 transition-all">
                        <i class="fas fa-save mr-2"></i> Guardar Localización
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 3: CONTACTO -->
        <div id="tab3" class="tab-content hidden relative">
            <h3 class="text-xl font-bold text-slate-800 mb-6 border-b border-zinc-100 pb-2">Medios de Contacto</h3>
            <form id="form-tab3" onsubmit="saveTab(event, 'tab3')">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-mobile-alt mr-1"></i> Teléfono Celular *</label>
                        <input type="text" name="telefono_celular" value="<?php echo $c['telefono_celular']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-phone mr-1"></i> Teléfono Fijo</label>
                        <input type="text" name="telefono_fijo" value="<?php echo $c['telefono_fijo']; ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-blue-600 uppercase tracking-wider mb-2"><i class="fab fa-facebook mr-1"></i> Facebook (Opcional)</label>
                        <input type="text" name="facebook" value="<?php echo $c['facebook']; ?>" placeholder="URL o Usuario" class="w-full px-4 py-2 bg-blue-50/30 border border-blue-100 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-pink-600 uppercase tracking-wider mb-2"><i class="fab fa-instagram mr-1"></i> Instagram (Opcional)</label>
                        <input type="text" name="instagram" value="<?php echo $c['instagram']; ?>" placeholder="@Usuario" class="w-full px-4 py-2 bg-pink-50/30 border border-pink-100 rounded-lg focus:outline-none focus:border-pink-500 focus:ring-1 focus:ring-pink-500 transition-all">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-2"><i class="fab fa-tiktok mr-1"></i> TikTok (Opcional)</label>
                        <input type="text" name="tiktok" value="<?php echo $c['tiktok']; ?>" placeholder="@Usuario" class="w-full px-4 py-2 bg-zinc-50 border border-zinc-200 rounded-lg focus:outline-none focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500 transition-all">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-zinc-900 text-white font-bold text-sm rounded-lg shadow hover:bg-zinc-800 transition-all">
                        <i class="fas fa-save mr-2"></i> Guardar Contacto
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 4: MEDICO Y PRECAUCIONES -->
        <div id="tab4" class="tab-content hidden relative">
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 mb-6 rounded-r">
                <h3 class="text-rose-800 font-bold mb-1"><i class="fas fa-exclamation-triangle mr-2"></i> Información Crítica</h3>
                <p class="text-rose-600 text-sm font-light">Los profesores tendrán acceso a esta información en caso de emergencia médica o para adecuar su trato según tus condiciones.</p>
            </div>
            
            <form id="form-tab4" onsubmit="saveTab(event, 'tab4')">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tipo de Sangre *</label>
                        <select name="tipo_sangre" required class="w-full md:w-1/2 px-4 py-2 bg-slate-50 border border-rose-200 rounded-lg focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-all font-bold text-rose-600">
                            <option value="">Seleccione...</option>
                            <?php 
                            $sangres = ['A+','A-','B+','B-','AB+','AB-','O+','O-','No Sabe'];
                            foreach($sangres as $s) {
                                $sel = $c['tipo_sangre'] === $s ? 'selected' : '';
                                echo "<option value='$s' $sel>$s</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Enfermedad -->
                    <div class="md:col-span-2 bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-slate-700 text-sm">¿Padece alguna enfermedad o tiene un tratamiento especial?</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="padece_enfermedad" value="1" class="sr-only peer" id="toggle_enf" onchange="toggleSection('enf_details', this)" <?php echo $c['padece_enfermedad'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                            </label>
                        </div>
                        <div id="enf_details" class="<?php echo $c['padece_enfermedad'] ? '' : 'hidden'; ?> mt-4 border-t border-slate-200 pt-4 grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Describa la enfermedad / tratamiento</label>
                                <input type="text" name="enfermedad_descripcion" value="<?php echo $c['enfermedad_descripcion']; ?>" class="w-full px-3 py-2 border border-slate-200 rounded-md focus:outline-none focus:border-rose-300">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Síntomas recurrentes a los que los docentes deban estar alertas</label>
                                <textarea name="enfermedad_sintomas" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-md focus:outline-none focus:border-rose-300"><?php echo $c['enfermedad_sintomas']; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Medicamentos -->
                    <div class="md:col-span-2 bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-slate-700 text-sm">¿Requiere la administración de medicamentos durante su estancia?</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="requiere_medicamentos" value="1" class="sr-only peer" id="toggle_med" onchange="toggleSection('med_details', this)" <?php echo $c['requiere_medicamentos'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                            </label>
                        </div>
                        <div id="med_details" class="<?php echo $c['requiere_medicamentos'] ? '' : 'hidden'; ?> mt-4 border-t border-slate-200 pt-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Especifique qué y cada cuánto tiempo</label>
                            <textarea name="medicamentos_descripcion" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-md focus:outline-none focus:border-rose-300"><?php echo $c['medicamentos_descripcion']; ?></textarea>
                        </div>
                    </div>

                    <!-- Alergias -->
                    <div class="md:col-span-2 bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-slate-700 text-sm">¿Tiene alergias a medicamentos o comida?</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="tiene_alergias" value="1" class="sr-only peer" id="toggle_ale" onchange="toggleSection('ale_details', this)" <?php echo $c['tiene_alergias'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500"></div>
                            </label>
                        </div>
                        <div id="ale_details" class="<?php echo $c['tiene_alergias'] ? '' : 'hidden'; ?> mt-4 border-t border-slate-200 pt-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Especifique sus alergias</label>
                            <textarea name="alergias_medicamentos" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-md focus:outline-none focus:border-rose-300"><?php echo $c['alergias_medicamentos']; ?></textarea>
                        </div>
                    </div>

                    <div class="md:col-span-2 pt-4">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2"><i class="fas fa-chalkboard-teacher mr-1 text-slate-400"></i> Situación que enfrenta el personal docente</label>
                        <p class="text-xs text-slate-400 mb-2">Escriba instrucciones breves sobre qué información deben conocer sus profesores sobre su situación y por qué.</p>
                        <textarea name="situacion_personal_docente" rows="3" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-400 transition-all"><?php echo $c['situacion_personal_docente']; ?></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2"><i class="fas fa-ambulance mr-1 text-rose-500"></i> Protocolo de emergencia a seguir</label>
                        <p class="text-xs text-slate-400 mb-2">Paso a paso para el docente en caso de una crisis manifestada.</p>
                        <textarea name="protocolos_emergencia" rows="4" class="w-full px-4 py-2 bg-slate-50 border border-rose-200 rounded-lg focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-all font-mono text-sm"><?php echo $c['protocolos_emergencia']; ?></textarea>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-rose-600 text-white font-bold text-sm rounded-lg shadow hover:bg-rose-700 transition-all">
                        <i class="fas fa-save mr-2"></i> Guardar Protocolos Críticos
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 5: TUTOR -->
        <div id="tab5" class="tab-content hidden relative">
            <h3 class="text-xl font-bold text-slate-800 mb-6 border-b border-zinc-100 pb-2">Datos del Tutor</h3>
            <form id="form-tab5" onsubmit="saveTab(event, 'tab5')">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nombre Completo del Tutor *</label>
                        <input type="text" name="tutor_nombre" value="<?php echo $c['tutor_nombre']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Teléfono Principal *</label>
                        <input type="text" name="tutor_telefono" value="<?php echo $c['tutor_telefono']; ?>" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Ocupación / Relación</label>
                        <input type="text" name="tutor_ocupacion" value="<?php echo $c['tutor_ocupacion']; ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-zinc-900 text-white font-bold text-sm rounded-lg shadow hover:bg-zinc-800 transition-all">
                        <i class="fas fa-save mr-2"></i> Guardar Tutor
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    function openTab(tabId) {
        // Ocultar contenidos
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');

        // Reiniciar botones
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.className = "tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-600 rounded-lg hover:bg-zinc-200 transition-colors";
        });
        
        // Activar botón pulsado
        let activeBtn = document.getElementById('btn-' + tabId);
        activeBtn.className = "tab-btn w-full flex items-center justify-between px-4 py-3 text-left text-sm font-bold rounded-lg transition-colors bg-zinc-900 text-white shadow-md";
    }

    function toggleSection(id, checkbox) {
        if(checkbox.checked) {
            document.getElementById(id).classList.remove('hidden');
        } else {
            document.getElementById(id).classList.add('hidden');
        }
    }

    function saveTab(e, tabId) {
        e.preventDefault();
        
        let formData = new FormData(document.getElementById('form-' + tabId));
        formData.append('tab', tabId);

        fetch('../../back/alumno_actions_contacto.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: data.message,
                    confirmButtonColor: '#18181b', // zinc-900
                    timer: 2000
                }).then(() => {
                    // Recargar página para actualizar checkmarks sin complicaciones
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#18181b',
                });
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Hubo un error de red.', 'error');
        });
    }
</script>
