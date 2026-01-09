<?php
// Include DB logic
require_once __DIR__ . '/../../back/db_connect.php';

// DEBUG: Enable Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DEBUG: Connection Check
if (!isset($conn)) {
    die("Error: \$conn no está definido. Revisa db_connect.php");
}
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- MAIN LOGIC ---
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// ==========================================
// VIEW 1: STUDENT DETAIL (GESTION DOCUMENTAL)
// ==========================================
if ($user_id):
    // 1. Get Student Info
    $sql_student = "SELECT nombre_completo, curp FROM Usuarios WHERE id = $user_id";
    $res_student = $conn->query($sql_student);
    $student = $res_student->fetch_assoc();

    // 2. Get Documents
    $sql_docs = "SELECT * FROM Documentos_Alumno WHERE alumno_id = $user_id";
    $res_docs = $conn->query($sql_docs);
    $uploaded_docs = [];
    while ($row = $res_docs->fetch_assoc()) {
        $uploaded_docs[$row['tipo_documento']] = $row;
    }

    // 3. Define Requirements (Same as Alumno)
    $required_docs = [
        'Fotografía de Perfil' => 'fa-user-circle',
        'Acta de Nacimiento' => 'fa-scroll',
        'CURP' => 'fa-id-card',
        'Certificado de Estudios' => 'fa-graduation-cap',
        'Identificación Oficial' => 'fa-address-card',
        'Comprobante de Domicilio' => 'fa-home'
    ];
    ?>
    <!-- Header -->
    <div class="mb-8 flex justify-between items-end">
        <div>
            <a href="dashboard.php?page=alumno_setup" class="text-zinc-400 hover:text-zinc-600 text-xs mb-2 block"><i
                    class="fas fa-arrow-left mr-1"></i> Volver a lista</a>
            <h3 class="font-serif text-3xl text-zinc-900 mb-1">Expediente del Alumno</h3>
            <p class="text-zinc-500 font-light text-sm">Gestionando a: <span
                    class="font-bold text-zinc-900"><?php echo htmlspecialchars($student['nombre_completo']); ?></span>
                (<?php echo htmlspecialchars($student['curp']); ?>)</p>
        </div>
        <div class="bg-zinc-100 px-4 py-2 rounded text-xs text-zinc-500 font-mono">
            ID: #<?php echo $user_id; ?>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="mb-6 bg-zinc-900 text-white px-6 py-4 rounded-lg shadow-lg flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i
                    class="fas <?php echo $_SESSION['message']['type'] == 'success' ? 'fa-check-circle text-emerald-400' : 'fa-exclamation-circle text-rose-400'; ?>"></i>
                <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['message']['text']); ?></p>
            </div>
            <button onclick="this.parentElement.remove()" class="text-zinc-400 hover:text-white">&times;</button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Document Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($required_docs as $docName => $docIcon):
            $docData = isset($uploaded_docs[$docName]) ? $uploaded_docs[$docName] : null;
            $estado = $docData ? $docData['estado'] : 'Faltante';
            $archivo = $docData ? $docData['archivo_path'] : '';
            $statusColor = 'zinc';
            $statusLabel = 'Faltante';

            if ($estado === 'Aprobado') {
                $statusColor = 'emerald';
                $statusLabel = 'Validado';
            } elseif ($estado === 'Rechazado') {
                $statusColor = 'rose';
                $statusLabel = 'Rechazado';
            } elseif ($estado === 'Pendiente') {
                $statusColor = 'amber';
                $statusLabel = 'Pendiente';
            }
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-zinc-200 p-5 flex flex-col relative group">

                <!-- Header Card -->
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-<?php echo $statusColor; ?>-50 text-<?php echo $statusColor; ?>-600 flex items-center justify-center text-lg">
                            <i class="fas <?php echo $docIcon; ?>"></i>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-zinc-900 leading-tight"><?php echo $docName; ?></h5>
                            <span
                                class="text-[10px] uppercase font-bold tracking-wider text-<?php echo $statusColor; ?>-500"><?php echo $statusLabel; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div
                    class="flex-1 mb-4 bg-zinc-50 rounded-lg p-3 relative min-h-[80px] flex items-center justify-center border border-dashed border-zinc-200">
                    <?php if ($archivo): ?>
                        <a href="<?php echo $archivo; ?>" target="_blank"
                            class="text-center group-hover:scale-105 transition-transform">
                            <i class="fas fa-file-pdf text-3xl text-zinc-400 mb-1"></i>
                            <p class="text-[10px] text-zinc-500 underline truncate max-w-[150px]">Ver Documento</p>
                        </a>
                    <?php else: ?>
                        <p class="text-[10px] text-zinc-400 italic">Sin archivo</p>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="flex flex-col gap-2">
                    <?php if ($estado === 'Pendiente' || $estado === 'Rechazado' || $estado === 'Aprobado'): ?>
                        <!-- Approval Actions -->
                        <?php if ($estado !== 'Aprobado'): ?>
                            <div class="flex gap-2">
                                <form action="../../back/admin_actions_documentos.php" method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="doc_id" value="<?php echo $docData['id']; ?>">
                                    <input type="hidden" name="alumno_id" value="<?php echo $user_id; ?>">
                                    <button type="submit"
                                        class="w-full bg-emerald-100 text-emerald-700 py-2 rounded text-xs font-bold hover:bg-emerald-200 transition-colors">
                                        <i class="fas fa-check"></i> Aprobar
                                    </button>
                                </form>
                                <button
                                    onclick="document.getElementById('rejectModal-<?php echo $docName; ?>').classList.remove('hidden')"
                                    class="flex-1 bg-rose-100 text-rose-700 py-2 rounded text-xs font-bold hover:bg-rose-200 transition-colors">
                                    <i class="fas fa-times"></i> Rechazar
                                </button>
                            </div>
                        <?php else: ?>
                            <button onclick="document.getElementById('rejectModal-<?php echo $docName; ?>').classList.remove('hidden')"
                                class="w-full border border-rose-200 text-rose-500 py-2 rounded text-xs font-bold hover:bg-rose-50 transition-colors">
                                <i class="fas fa-undo"></i> Revocar / Rechazar
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Admin Upload trigger -->
                    <button onclick="document.getElementById('uploadModal-<?php echo $docName; ?>').classList.remove('hidden')"
                        class="w-full bg-zinc-900 text-white py-2 rounded text-xs font-bold hover:bg-zinc-800 transition-colors">
                        <i class="fas fa-cloud-upload-alt mr-2"></i> <?php echo $archivo ? 'Reemplazar' : 'Subir (Admin)'; ?>
                    </button>
                </div>

                <!-- MODAL REJECT -->
                <div id="rejectModal-<?php echo $docName; ?>"
                    class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm">
                    <div class="bg-white rounded-xl p-6 w-80 shadow-2xl">
                        <h5 class="text-sm font-bold text-zinc-900 mb-4">Motivo de Rechazo</h5>
                        <form action="../../back/admin_actions_documentos.php" method="POST">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="doc_id" value="<?php echo $docData['id']; ?>">
                            <input type="hidden" name="alumno_id" value="<?php echo $user_id; ?>">
                            <textarea name="motivo_rechazo" required
                                class="w-full bg-zinc-50 border border-zinc-200 rounded p-2 text-xs mb-4 focus:ring-2 focus:ring-rose-500 outline-none"
                                rows="3" placeholder="Ej. Documento borroso..."></textarea>
                            <div class="flex gap-2">
                                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')"
                                    class="flex-1 bg-zinc-100 text-zinc-500 py-2 rounded text-xs font-bold">Cancelar</button>
                                <button type="submit"
                                    class="flex-1 bg-rose-600 text-white py-2 rounded text-xs font-bold hover:bg-rose-700">Confirmar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- MODAL UPLOAD -->
                <div id="uploadModal-<?php echo $docName; ?>"
                    class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm">
                    <div class="bg-white rounded-xl p-6 w-80 shadow-2xl">
                        <h5 class="text-sm font-bold text-zinc-900 mb-4">Subir Documento (Admin)</h5>
                        <form action="../../back/admin_actions_documentos.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="admin_upload">
                            <input type="hidden" name="tipo_documento" value="<?php echo $docName; ?>">
                            <input type="hidden" name="alumno_id" value="<?php echo $user_id; ?>">

                            <?php
                            $accept = '.pdf';
                            if ($docName === 'Identificación Oficial') {
                                $accept = '.pdf,.jpg,.jpeg,.png';
                            } elseif ($docName === 'Fotografía de Perfil') {
                                $accept = '.jpg,.jpeg,.png';
                            }
                            ?>
                            <div class="mb-4">
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Archivo</label>
                                <input type="file" name="documento" accept="<?php echo $accept; ?>" required
                                    class="w-full text-xs text-zinc-500 file:mr-2 file:py-1 file:px-2 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200">
                            </div>

                            <div class="bg-amber-50 p-2 rounded mb-4">
                                <p class="text-[10px] text-amber-700"><i class="fas fa-check-circle mr-1"></i> Se marcará como
                                    <strong>Aprobado</strong> automáticamente.
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')"
                                    class="flex-1 bg-zinc-100 text-zinc-500 py-2 rounded text-xs font-bold">Cancelar</button>
                                <button type="submit"
                                    class="flex-1 bg-zinc-900 text-white py-2 rounded text-xs font-bold hover:bg-zinc-800">Subir</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // ==========================================
// VIEW 2: DASHBOARD GLOBAL LIST
// ==========================================
else:
    // 1. KPIs
    $sql_total = "SELECT COUNT(*) as total FROM Usuarios WHERE perfil_id = 3"; // Total Students (All States)
    $total_students = $conn->query($sql_total)->fetch_assoc()['total'];

    // Count Pending
    $sql_pending_docs = "SELECT COUNT(*) as total FROM Documentos_Alumno WHERE estado = 'Pendiente'";
    $pending_docs_count = $conn->query($sql_pending_docs)->fetch_assoc()['total'];

    // 2. Table Data & Filters
    $filter_status = isset($_GET['filter']) ? $_GET['filter'] : '';
    $search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

    // Base SQL
    $sql_table = "
        SELECT 
            u.id, 
            u.nombre_completo, 
            u.curp, 
            u.estado,
            COUNT(d.id) as docs_subidos,
            SUM(CASE WHEN d.estado = 'Aprobado' THEN 1 ELSE 0 END) as docs_aprobados,
            SUM(CASE WHEN d.estado = 'Pendiente' THEN 1 ELSE 0 END) as docs_pendientes
        FROM Usuarios u
        LEFT JOIN Documentos_Alumno d ON u.id = d.alumno_id
        WHERE u.perfil_id = 3
    ";

    // Apply Search
    if (!empty($search_query)) {
        $sql_table .= " AND (u.nombre_completo LIKE '%$search_query%' OR u.curp LIKE '%$search_query%') ";
    }

    $sql_table .= " GROUP BY u.id ";

    // Apply Filter (Pending)
    if ($filter_status === 'pending') {
        $sql_table .= " HAVING docs_pendientes > 0 ";
    }

    $sql_table .= " ORDER BY docs_aprobados ASC, u.nombre_completo ASC ";

    $res_table = $conn->query($sql_table);
    ?>

    <div class="mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h3 class="font-serif text-3xl text-zinc-900 mb-2">Documentación de Alumnos</h3>
            <p class="text-zinc-500 font-light text-sm">Gestiona y valida los expedientes digitales.</p>
        </div>

        <!-- Search Form -->
        <form method="GET" action="dashboard.php" class="flex gap-2 w-full md:w-auto">
            <input type="hidden" name="page" value="alumno_setup">
            <?php if ($filter_status): ?><input type="hidden" name="filter"
                    value="<?php echo $filter_status; ?>"><?php endif; ?>

            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                placeholder="Buscar alumno o CURP..."
                class="border border-zinc-200 rounded-lg px-4 py-2 text-sm outline-none focus:border-zinc-900 w-full md:w-64">
            <button type="submit"
                class="bg-zinc-900 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-zinc-800"><i
                    class="fas fa-search"></i></button>

            <?php if (!empty($search_query) || !empty($filter_status)): ?>
                <a href="dashboard.php?page=alumno_setup"
                    class="bg-zinc-100 text-zinc-500 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-zinc-200 flex items-center justify-center"><i
                        class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <a href="dashboard.php?page=alumno_setup"
            class="bg-white p-6 rounded-xl border border-zinc-100 shadow-sm flex items-center justify-between hover:shadow-md transition-shadow cursor-pointer group">
            <div>
                <p class="text-xs uppercase tracking-widest text-zinc-400 font-bold mb-1">Total Alumnos</p>
                <h4 class="text-3xl font-serif text-zinc-900 group-hover:text-blue-600 transition-colors">
                    <?php echo $total_students; ?>
                </h4>
            </div>
            <div class="w-12 h-12 bg-zinc-50 rounded-full flex items-center justify-center text-zinc-300">
                <i class="fas fa-users text-xl"></i>
            </div>
        </a>

        <!-- Pending KPI -> Click to Filter -->
        <a href="dashboard.php?page=alumno_setup&filter=pending"
            class="bg-white p-6 rounded-xl border <?php echo $filter_status === 'pending' ? 'border-amber-400 ring-2 ring-amber-100' : 'border-zinc-100'; ?> shadow-sm flex items-center justify-between hover:shadow-md transition-all cursor-pointer group">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-500 font-bold mb-1">Documentos Pendientes</p>
                <h4 class="text-3xl font-serif text-amber-600"><?php echo $pending_docs_count; ?></h4>
                <p class="text-[10px] text-zinc-400">
                    <?php echo $filter_status === 'pending' ? 'Filtro Activo' : 'Clic para filtrar'; ?>
                </p>
            </div>
            <div
                class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 <?php echo $pending_docs_count > 0 ? 'animate-pulse' : ''; ?>">
                <i class="fas fa-clock text-xl"></i>
            </div>
        </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-zinc-100 bg-zinc-50/50">
            <h6 class="text-xs font-bold uppercase tracking-widest text-zinc-500">Listado de Alumnos</h6>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-zinc-900 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">CURP</th>
                        <th class="px-6 py-4 font-medium">Alumno</th>
                        <th class="px-6 py-4 font-medium">Estado</th>
                        <th class="px-6 py-4 font-medium whitespace-nowrap">Progreso</th>
                        <th class="px-6 py-4 font-medium whitespace-nowrap">Por Validar</th>
                        <th class="px-6 py-4 font-medium text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 text-sm">
                    <?php while ($row = $res_table->fetch_assoc()):
                        $progreso = $row['docs_aprobados'];
                        $pendientes = $row['docs_pendientes'];
                        $total_req = 6;
                        $percent = ($progreso / $total_req) * 100;
                        $barColor = $percent == 100 ? 'bg-emerald-500' : 'bg-amber-500';
                        ?>
                        <tr class="hover:bg-zinc-50 transition-colors group">
                            <td class="px-6 py-4 font-mono text-zinc-500 text-xs"><?php echo $row['curp']; ?></td>
                            <td class="px-6 py-4 font-bold text-zinc-800"><?php echo $row['nombre_completo']; ?></td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo strtolower($row['estado']) === 'activo' ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'; ?>">
                                    <?php echo $row['estado']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="text-xs font-bold text-zinc-700 w-8"><?php echo $progreso; ?>/<?php echo $total_req; ?></span>
                                    <div class="w-24 h-1.5 bg-zinc-100 rounded-full overflow-hidden">
                                        <div class="h-full <?php echo $barColor; ?>" style="width: <?php echo $percent; ?>%">
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($pendientes > 0): ?>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 animate-pulse">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo $pendientes; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-zinc-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="dashboard.php?page=alumno_setup&user_id=<?php echo $row['id']; ?>"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-zinc-100 text-zinc-600 hover:bg-zinc-900 hover:text-white transition-all shadow-sm group-hover:shadow-md">
                                    <i class="fas fa-folder-open text-xs"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>