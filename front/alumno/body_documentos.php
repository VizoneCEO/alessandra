<?php
// --- 1. Definir Documentos Requeridos ---
$required_docs = [
    'Fotografía de Perfil' => 'fa-user-circle',
    'Acta de Nacimiento' => 'fa-scroll',
    'CURP' => 'fa-id-card',
    'Certificado de Estudios' => 'fa-graduation-cap',
    'Identificación Oficial' => 'fa-address-card',
    'Comprobante de Domicilio' => 'fa-home'
];

// --- 2. Obtener Estado Actual de BD ---
$alumno_id = $_SESSION['user_id'];
$sql_docs = "SELECT * FROM Documentos_Alumno WHERE alumno_id = '$alumno_id'";
$res_docs = $conn->query($sql_docs);
$uploaded_docs = [];
while ($row = $res_docs->fetch_assoc()) {
    $uploaded_docs[$row['tipo_documento']] = $row;
}
?>

<div class="mb-8">
    <h3 class="font-serif text-3xl text-zinc-900 mb-2">Expediente Digital</h3>
    <p class="text-zinc-500 font-light text-sm">Gestiona tu documentación legal obligatoria para la institución.</p>
</div>

<!-- GRID DE DOCUMENTOS -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php foreach ($required_docs as $docName => $docIcon):
        // Determinar estado
        $docData = isset($uploaded_docs[$docName]) ? $uploaded_docs[$docName] : null;
        $estado = $docData ? $docData['estado'] : 'Faltante';
        $mensaje = $docData ? $docData['mensaje_rechazo'] : '';
        $archivo = $docData ? $docData['archivo_path'] : '';

        // Estilos
        $cardBorder = "border-zinc-200 border-dashed";
        $iconColor = "text-zinc-300";
        $statusBadge = '<span class="bg-zinc-100 text-zinc-400 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full"><i class="fas fa-arrow-up mr-1"></i> Subir</span>';

        if ($estado === 'Aprobado') {
            $cardBorder = "border-emerald-200 bg-emerald-50/10 border-solid";
            $iconColor = "text-emerald-200";
            $statusBadge = '<span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full"><i class="fas fa-check mr-1"></i> Aprobado</span>';
        } elseif ($estado === 'Rechazado') {
            $cardBorder = "border-rose-200 bg-rose-50/20 border-solid";
            $iconColor = "text-rose-200";
            $statusBadge = '<span class="bg-rose-100 text-rose-700 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full"><i class="fas fa-times mr-1"></i> Rechazado</span>';
        } elseif ($estado === 'Pendiente') {
            $cardBorder = "border-amber-200 bg-amber-50/10 border-solid";
            $iconColor = "text-amber-200";
            $statusBadge = '<span class="bg-amber-100 text-amber-700 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full"><i class="fas fa-clock mr-1"></i> En Revisión</span>';
        }
        ?>

        <div
            class="relative bg-white rounded-xl shadow-sm border <?php echo $cardBorder; ?> p-6 flex flex-col h-full group transition-all hover:shadow-md">
            <!-- Icono de Fondo -->
            <div
                class="absolute top-4 right-4 text-6xl <?php echo $iconColor; ?> opacity-20 pointer-events-none group-hover:scale-110 transition-transform">
                <i class="fas <?php echo $docIcon; ?>"></i>
            </div>

            <!-- Header Tarjeta -->
            <div class="mb-4 relative z-10">
                <div class="flex justify-between items-start mb-2">
                    <div
                        class="p-3 bg-zinc-50 rounded-lg inline-flex items-center justify-center text-zinc-600 mb-2 shadow-sm border border-zinc-100">
                        <i class="fas <?php echo $docIcon; ?> text-xl"></i>
                    </div>
                    <div><?php echo $statusBadge; ?></div>
                </div>
                <h4 class="font-serif text-lg font-bold text-zinc-900 leading-tight">
                    <?php echo htmlspecialchars($docName); ?>
                </h4>
            </div>

            <!-- Cuerpo de Interacción -->
            <div class="flex-1 flex flex-col justify-end relative z-10 space-y-3">

                <!-- ESTADO: RECHAZADO -->
                <?php if ($estado === 'Rechazado'): ?>
                    <div class="bg-rose-50 border border-rose-100 rounded-lg p-3 text-xs text-rose-700 flex items-start">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-2 flex-shrink-0"></i>
                        <span><strong>Motivo:</strong> <?php echo htmlspecialchars($mensaje); ?></span>
                    </div>
                    <!-- Formulario de Re-subida -->
                    <form action="../../back/alumno_actions_documentos.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="tipo_documento" value="<?php echo htmlspecialchars($docName); ?>">

                        <label class="block w-full cursor-pointer">
                            <span class="sr-only">Seleccionar archivo</span>
                            <input type="file" name="documento" accept="<?php
                            if ($docName === 'Identificación Oficial')
                                echo '.pdf,.jpg,.jpeg,.png';
                            elseif ($docName === 'Fotografía de Perfil')
                                echo '.jpg,.jpeg,.png';
                            else
                                echo '.pdf';
                            ?>" required class="block w-full text-xs text-slate-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-full file:border-0
                                  file:text-xs file:font-semibold
                                  file:bg-rose-50 file:text-rose-700
                                  hover:file:bg-rose-100
                                " />
                        </label>
                        <button type="submit"
                            class="mt-2 w-full bg-rose-600 text-white text-xs font-bold uppercase tracking-wider py-2 rounded hover:bg-rose-700 transition-colors">
                            <i class="fas fa-redo mr-2"></i> Reintentar
                        </button>
                    </form>

                    <!-- Botón Eliminar (Permitido porque fue rechazado) -->
                    <form action="../../back/alumno_actions_documentos.php" method="POST"
                        onsubmit="return confirm('¿Eliminar documento rechazado?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="doc_id" value="<?php echo $docData['id']; ?>">
                        <button type="submit"
                            class="w-full text-rose-400 text-[10px] font-bold uppercase tracking-widest hover:text-rose-600 transition-colors mt-1">
                            Eliminar Registro
                        </button>
                    </form>

                    <!-- ESTADO: APROBADO -->
                <?php elseif ($estado === 'Aprobado'): ?>
                    <div class="bg-emerald-50/50 border border-emerald-100 rounded-lg p-4 text-center">
                        <i class="fas fa-lock text-emerald-300 text-2xl mb-2"></i>
                        <p class="text-xs text-emerald-800 font-medium mb-3">Documento Validado</p>
                        <a href="<?php echo htmlspecialchars($archivo); ?>" target="_blank"
                            class="block w-full bg-white border border-emerald-200 text-emerald-700 text-xs font-bold uppercase tracking-wider py-2 rounded hover:bg-emerald-50 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i> Ver Archivo
                        </a>
                    </div>

                    <!-- ESTADO: PENDIENTE -->
                <?php elseif ($estado === 'Pendiente'): ?>
                    <div class="bg-amber-50 border border-amber-100 rounded-lg p-3 text-center">
                        <p class="text-xs text-amber-800 mb-2">Tu documento está siendo revisado por administración.</p>
                        <!-- Opción de Eliminar para volver a subir si se equivocó -->
                        <form action="../../back/alumno_actions_documentos.php" method="POST"
                            onsubmit="return confirm('¿Eliminar documento pendiente?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="doc_id" value="<?php echo $docData['id']; ?>">
                            <button type="submit"
                                class="text-amber-600 text-[10px] font-bold uppercase tracking-widest hover:text-amber-800 transition-colors border-b border-amber-200 pb-0.5">
                                Cancelar / Eliminar
                            </button>
                        </form>
                    </div>

                    <!-- ESTADO: FALTANTE (Upload) -->
                <?php else: ?>
                    <form action="../../back/alumno_actions_documentos.php" method="POST" enctype="multipart/form-data"
                        class="w-full">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="tipo_documento" value="<?php echo htmlspecialchars($docName); ?>">

                        <?php
                        $acceptTypes = '.pdf';
                        $acceptLabel = 'Subir PDF';

                        if ($docName === 'Identificación Oficial') {
                            $acceptTypes = '.pdf,.jpg,.jpeg,.png';
                            $acceptLabel = 'Subir PDF/IMG';
                        } elseif ($docName === 'Fotografía de Perfil') {
                            $acceptTypes = '.jpg,.jpeg,.png';
                            $acceptLabel = 'Subir Imagen';
                        }
                        ?>
                        <div
                            class="relative border-2 border-dashed border-zinc-200 rounded-lg p-6 text-center hover:border-zinc-800 hover:bg-zinc-50 transition-all cursor-pointer group-upload">
                            <input type="file" name="documento" accept="<?php echo $acceptTypes; ?>" required
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20"
                                onchange="this.nextElementSibling.classList.add('hidden'); this.nextElementSibling.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.nextElementSibling.innerText = this.files[0].name;">

                            <!-- Placeholder -->
                            <div class="relative z-10 pointer-events-none">
                                <i
                                    class="fas fa-cloud-upload-alt text-zinc-300 text-2xl mb-2 group-hover:text-zinc-600 transition-colors"></i>
                                <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-wider">
                                    <?php echo $acceptLabel; ?>
                                </p>
                            </div>
                            <!-- Filename Preview -->
                            <div class="relative z-10 pointer-events-none hidden text-xs font-bold text-zinc-800 break-all">
                            </div>
                        </div>
                        <button type="submit"
                            class="mt-3 w-full bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider py-2 rounded hover:bg-zinc-700 transition-colors">
                            Guardar
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Alerts -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="fixed bottom-4 right-4 bg-zinc-900 text-white px-6 py-4 rounded-lg shadow-2xl z-50 animate-bounce-in">
        <div class="flex items-center gap-3">
            <i
                class="fas <?php echo $_SESSION['message']['type'] == 'success' ? 'fa-check-circle text-emerald-400' : 'fa-exclamation-circle text-rose-400'; ?>"></i>
            <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['message']['text']); ?></p>
        </div>
        <button onclick="this.parentElement.remove()"
            class="absolute -top-2 -right-2 bg-white text-zinc-900 rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold shadow-sm hover:scale-110 transition-transform">&times;</button>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<div class="mt-8 p-4 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
    <i class="fas fa-info-circle text-blue-500 mt-1"></i>
    <div>
        <h6 class="text-sm font-bold text-blue-800">Recuerda</h6>
        <p class="text-xs text-blue-700">
            Una vez que un documento sea <strong>Aprobado</strong>, no podrás eliminarlo. Si necesitas actualizar un
            documento aprobado, deberás contactar a control escolar para que lo liberen.
        </p>
    </div>
</div>