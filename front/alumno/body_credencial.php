<?php
// --- GET STUDENT INFO ---
$alumno_id = $_SESSION['user_id'];

// 1. Basic Info
$sql_info = "SELECT nombre_completo, curp FROM Usuarios WHERE id = $alumno_id";
$res_info = $conn->query($sql_info);
$student_info = $res_info->fetch_assoc();

// 2. Profile Pic
$sql_pic = "SELECT archivo_path FROM Documentos_Alumno 
            WHERE alumno_id = $alumno_id AND tipo_documento = 'Fotografía de Perfil' 
            ORDER BY id DESC LIMIT 1";
$res_pic = $conn->query($sql_pic);
$photo_path = null;
if ($row_pic = $res_pic->fetch_assoc()) {
    $photo_path = $row_pic['archivo_path'];
}

// 3. Cycle & Branch Validity (Latest Enrollment)
// Assumption: Clases has sucursal_id. If not, we might need to adjust.
$sql_validity = "SELECT ce.nombre_ciclo, ce.fecha_fin, s.nombre_sucursal
                 FROM Inscripciones i
                 JOIN Clases c ON i.clase_id = c.id
                 JOIN Ciclos_Escolares ce ON c.ciclo_id = ce.id
                 LEFT JOIN Sucursales s ON c.sucursal_id = s.id
                 WHERE i.alumno_id = $alumno_id
                 ORDER BY ce.fecha_fin DESC
                 LIMIT 1";
$res_validity = $conn->query($sql_validity);

$vigencia = "No Inscrito";
$sucursal = "Sin asignar";
$ciclo_nombre = "N/A";
$is_active = false;

if ($res_validity && $row_val = $res_validity->fetch_assoc()) {
    $fecha_fin = new DateTime($row_val['fecha_fin']);
    $hoy = new DateTime();

    $vigencia = $fecha_fin->format('d/m/Y');
    $sucursal = $row_val['nombre_sucursal'] ?? 'Campus Central';
    $ciclo_nombre = $row_val['nombre_ciclo'];

    if ($fecha_fin >= $hoy) {
        $is_active = true;
    }
}

// 4. QR Code Data
// For now, we embed a simple JSON or string. Ideally a signed token.
$qr_data = "VALIDAR|{$student_info['curp']}|{$alumno_id}";
// Optimizada para lectura rápida: ECC Baja (L) y Margen blanco
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=L&margin=10&data=" . urlencode($qr_data);

?>

<div class="max-w-4xl mx-auto">

    <div class="mb-8 text-center lg:text-left">
        <h3 class="font-serif text-3xl text-zinc-900 mb-2">Credencial Digital</h3>
        <p class="text-zinc-500 font-light text-sm">Identificación oficial de alumno para acceso al plantel.</p>
    </div>

    <div class="flex flex-col md:flex-row gap-12 items-center justify-center">

        <!-- ID CARD FRONT -->
        <div
            class="relative w-[320px] h-[500px] bg-zinc-900 rounded-3xl overflow-hidden shadow-2xl transition-transform hover:scale-[1.02] duration-300">

            <!-- Background Decoration -->
            <div
                class="absolute top-0 right-0 w-64 h-64 bg-zinc-800 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2">
            </div>
            <div
                class="absolute bottom-0 left-0 w-64 h-64 bg-amber-900 rounded-full mix-blend-overlay filter blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2">
            </div>

            <!-- Content -->
            <div class="relative z-10 h-full flex flex-col items-center pt-8 pb-6 px-6 text-center">

                <!-- Header -->
                <div class="mb-6">
                    <p class="text-[10px] uppercase tracking-[0.3em] text-amber-500 font-bold mb-1">INSTITUTO</p>
                    <h2 class="font-serif text-2xl text-white tracking-wide">ALESSANDRA</h2>
                    <p class="text-[9px] text-zinc-400 mt-1 uppercase tracking-widest">
                        <?php echo htmlspecialchars($sucursal); ?>
                    </p>
                </div>

                <!-- Profile Photo -->
                <div class="w-32 h-32 rounded-full p-1 bg-gradient-to-tr from-amber-500 to-zinc-800 mb-5 shadow-lg">
                    <div class="w-full h-full rounded-full overflow-hidden bg-zinc-800">
                        <?php if ($photo_path): ?>
                            <img src="<?php echo htmlspecialchars($photo_path); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-zinc-600">
                                <i class="fas fa-user text-4xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="mb-auto w-full">
                    <h3 class="text-lg font-bold text-white leading-tight mb-1 uppercase break-words">
                        <?php echo htmlspecialchars($student_info['nombre_completo']); ?>
                    </h3>
                    <p class="text-[10px] text-zinc-400 font-mono tracking-wider mb-4">
                        <?php echo htmlspecialchars($student_info['curp']); ?>
                    </p>

                    <div class="inline-block bg-zinc-800/50 backdrop-blur border border-zinc-700 rounded px-4 py-1.5">
                        <p class="text-[9px] uppercase text-zinc-500 mb-0.5 tracking-widest">Alumno</p>
                        <p class="text-xs text-emerald-400 font-bold tracking-wider">ACTIVO</p>
                    </div>
                </div>

                <!-- Footer / Validity -->
                <div class="w-full border-t border-zinc-800 pt-4 mt-4">
                    <div class="flex justify-between items-end">
                        <div class="text-left">
                            <p class="text-[9px] text-zinc-500 uppercase tracking-widest mb-1">Vigencia</p>
                            <p class="text-sm font-mono text-white">
                                <?php echo $vigencia; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <img src="../../front/multimedia/logoA.png" class="h-6 opacity-30 invert brightness-0">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- QR SECTION (Validation) -->
        <div class="bg-white p-8 rounded-2xl shadow-lg border border-zinc-100 max-w-xs text-center">
            <h4 class="font-serif text-xl text-zinc-900 mb-2">Escáner de Acceso</h4>
            <p class="text-xs text-zinc-500 mb-6">Muestra este código al personal de seguridad para ingresar.</p>

            <div class="bg-white p-2 border-2 border-zinc-900 rounded-xl inline-block mb-4 relative group">
                <img src="<?php echo $qr_url; ?>" alt="QR Validación" class="w-48 h-48 mix-blend-multiply">

                <?php if (!$is_active): ?>
                    <div class="absolute inset-0 bg-white/90 flex items-center justify-center backdrop-blur-[2px]">
                        <div
                            class="text-rose-600 font-bold text-sm uppercase tracking-widest border-2 border-rose-600 px-3 py-1 -rotate-12">
                            Expirado
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-center gap-2 text-[10px] text-zinc-400 uppercase tracking-wider">
                <i class="fas fa-shield-alt text-emerald-500"></i> Sistema Seguro V3
            </div>
        </div>

    </div>
</div>