<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 3) {
    die("Acceso denegado. Solo perfiles de Alumno pueden generar su boleta individual.");
}

require '../../back/db_connect.php';

$alumno_id = $_SESSION['user_id'];
$semestre_id = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;

if ($semestre_id <= 0) {
    die("Error: Parámetro de semestre inválido.");
}

// 1. Datos del Alumno
$sql_alumno = "SELECT nombre_completo, matricula FROM Usuarios WHERE id = ?";
$stmt = $conn->prepare($sql_alumno);
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$alumno) die("Alumno no encontrado");

$categorias_principales = ['Actividades', 'Asistencia', 'Examenes'];

// --- FUNCIÓN DE CÁLCULO MIGRADA (Para Precisión Aritmética) ---
function getDetalleCalificacion($conn, $inscripcion_id, $categorias_principales) {
    $clase_id_result = $conn->query("SELECT clase_id FROM Inscripciones WHERE id = $inscripcion_id");
    if ($clase_id_result->num_rows == 0) return null;
    
    $clase_id = $clase_id_result->fetch_assoc()['clase_id'];
    $categorias_db = $conn->query("SELECT * FROM Categorias_Calificacion WHERE clase_id = $clase_id")->fetch_all(MYSQLI_ASSOC);
    $ponderaciones = [];
    foreach ($categorias_db as $cat) {
        $ponderaciones[$cat['nombre_categoria']] = $cat;
    }
    
    $data_return = [
        'final' => 0.0,
        'calif_por_parcial' => [1 => 0.0, 2 => 0.0, 3 => 0.0],
        'items_count_por_parcial' => [1 => 0, 2 => 0, 3 => 0]
    ];
    
    $promedios_parciales = [];
    foreach ($categorias_principales as $cat_nombre) {
        $cat_id = $ponderaciones[$cat_nombre]['id'] ?? 0;
        for ($p = 1; $p <= 3; $p++) { $promedios_parciales[$cat_nombre][$p] = 0; }
        
        if ($cat_id > 0) {
            $sql_items = "SELECT a.parcial, c.calificacion_obtenida 
                          FROM Calificaciones c
                          JOIN Actividades_Evaluables a ON c.actividad_id = a.id
                          WHERE c.inscripcion_id = ? AND a.categoria_id = ?";
            $stmt = $conn->prepare($sql_items);
            $stmt->bind_param("ii", $inscripcion_id, $cat_id);
            $stmt->execute();
            $result_items = $stmt->get_result();
            
            $califs_por_parcial = [1 => [], 2 => [], 3 => []];
            while ($item = $result_items->fetch_assoc()) {
                $p_index = $item['parcial'];
                $califs_por_parcial[$p_index][] = (float)$item['calificacion_obtenida'];
                $data_return['items_count_por_parcial'][$p_index]++;
            }
            $stmt->close();
            
            for ($p = 1; $p <= 3; $p++) {
                if (count($califs_por_parcial[$p]) > 0) {
                    $promedios_parciales[$cat_nombre][$p] = array_sum($califs_por_parcial[$p]) / count($califs_por_parcial[$p]);
                }
            }
        }
    }
    
    // Calcular por peso dictado en Categorias_Calificacion
    for ($p = 1; $p <= 3; $p++) {
        foreach ($categorias_principales as $cat_nombre) {
            $ponderacion = ($ponderaciones[$cat_nombre]['ponderacion'] ?? 0) / 100;
            $prom = $promedios_parciales[$cat_nombre][$p] ?? 0;
            $data_return['calif_por_parcial'][$p] += ($prom * $ponderacion);
        }
    }
    
    $suma = $data_return['calif_por_parcial'][1] + $data_return['calif_por_parcial'][2] + $data_return['calif_por_parcial'][3];
    $data_return['final'] = $suma / 3;
    
    return $data_return;
}

// 2. Extraer Materias de este semestre
$sql_insc = "SELECT i.id as insc_id, m.nombre_materia, i.calificacion_final 
             FROM Inscripciones i
             JOIN Clases c ON i.clase_id = c.id
             JOIN Materias m ON c.materia_id = m.id
             WHERE i.alumno_id = ? AND m.semestre = ?
             ORDER BY m.nombre_materia ASC";

$stmt = $conn->prepare($sql_insc);
$stmt->bind_param("ii", $alumno_id, $semestre_id);
$stmt->execute();
$materias_result = $stmt->get_result();

$materias_data = [];
$avg_final_general = 0;
$materias_counted = 0;

while ($row = $materias_result->fetch_assoc()) {
    $data = getDetalleCalificacion($conn, $row['insc_id'], $categorias_principales);
    
    // Default dashes
    $p1 = '-'; $p2 = '-'; $p3 = '-'; $final = '-';
    
    if ($data) {
        if ($data['items_count_por_parcial'][1] > 0) $p1 = number_format($data['calif_por_parcial'][1], 1);
        if ($data['items_count_por_parcial'][2] > 0) $p2 = number_format($data['calif_por_parcial'][2], 1);
        if ($data['items_count_por_parcial'][3] > 0) $p3 = number_format($data['calif_por_parcial'][3], 1);
        
        // Si hay items en todos, calculamos final dinamico
        if ($p1 !== '-' && $p2 !== '-' && $p3 !== '-') {
            $final = number_format($data['final'], 1);
        }
        
        // Pero si la base de datos ya tiene dictamen final, sobreescribir (ej. Extraordinario)
        if ($row['calificacion_final'] !== null) {
            $final = number_format($row['calificacion_final'], 1);
        }
    }

    if ($final !== '-') {
        $avg_final_general += (float)$final;
        $materias_counted++;
    }

    $materias_data[] = [
        'nombre' => $row['nombre_materia'],
        'p1' => $p1,
        'p2' => $p2,
        'p3' => $p3,
        'final' => $final
    ];
}

$promedio_ciclo = ($materias_counted > 0) ? number_format($avg_final_general / $materias_counted, 2) : '-';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de Calificaciones | <?php echo htmlspecialchars($alumno['matricula']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tipografías Formatorias */
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Roboto:wght@300;400;700&display=swap');
        
        body { font-family: 'Roboto', sans-serif; background: #e4e4e7; display: flex; justify-content: center; padding: 2rem; }
        
        .hoja-carta {
            width: 215.9mm; /* 8.5 in */
            min-height: 279.4mm; /* 11 in */
            background: white;
            padding: 15mm 20mm;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
        }

        .serif { font-family: 'Playfair Display', serif; }
        
        /* Reglas crudas de impresion */
        @media print {
            @page { size: letter; margin: 0; }
            body { background: white; padding: 0; }
            .hoja-carta { box-shadow: none; width: 100%; min-height: auto; padding: 20mm; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

    <!-- Boton flotante Utils -->
    <div class="fixed top-8 right-8 flex flex-col gap-3 no-print">
        <button onclick="window.print()" class="w-12 h-12 bg-sky-600 text-white rounded-full shadow-xl hover:bg-sky-700 hover:scale-105 transition-transform flex items-center justify-center text-xl">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        </button>
        <button onclick="window.close()" class="w-12 h-12 bg-zinc-800 text-white rounded-full shadow-xl hover:bg-black hover:scale-105 transition-transform flex items-center justify-center text-xl">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- DOCUMENTO OFICIAL -->
    <div class="hoja-carta flex flex-col">
        <!-- Encabezado -->
        <header class="flex justify-between items-center border-b-2 border-zinc-900 pb-6 mb-6">
            <div class="w-32">
                <img src="../../front/multimedia/logoA.png" alt="Instituto Logo" class="w-full grayscale brightness-0 opacity-90">
            </div>
            <div class="text-right">
                <h1 class="serif text-2xl font-bold text-zinc-900 tracking-tight uppercase">Boleta de Calificaciones</h1>
                <p class="text-[11px] font-bold tracking-widest text-zinc-500 uppercase mt-1">Reporte Académico Oficial</p>
                <p class="text-[10px] text-zinc-400 mt-0.5">Fecha de expedición: <?php echo date("d/m/Y"); ?></p>
            </div>
        </header>

        <!-- Datos del Alumno -->
        <section class="bg-zinc-50 border border-zinc-200 p-4 mb-8">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[9px] uppercase font-bold text-zinc-400 tracking-wider">Nombre del Alumno</p>
                    <p class="text-sm font-bold text-zinc-900"><?php echo htmlspecialchars($alumno['nombre_completo']); ?></p>
                </div>
                <div>
                    <p class="text-[9px] uppercase font-bold text-zinc-400 tracking-wider">Ciclo Cursado</p>
                    <p class="text-sm font-bold text-zinc-900">Semestre <?php echo $semestre_id; ?></p>
                </div>
                <div>
                    <p class="text-[9px] uppercase font-bold text-zinc-400 tracking-wider">Matrícula</p>
                    <p class="text-sm text-zinc-700 font-mono"><?php echo htmlspecialchars($alumno['matricula']); ?></p>
                </div>
                <div>
                    <p class="text-[9px] uppercase font-bold text-zinc-400 tracking-wider">Validez</p>
                    <p class="text-sm text-zinc-700">Documento Informativo Interno</p>
                </div>
            </div>
        </section>

        <!-- Tabla Lógica -->
        <section class="flex-1">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-zinc-900 text-white">
                        <th class="py-2 px-4 text-left text-[11px] uppercase tracking-widest font-bold border border-zinc-900">Materia</th>
                        <th class="py-2 px-3 text-center text-[10px] uppercase tracking-widest font-bold border border-zinc-900">Parcial 1</th>
                        <th class="py-2 px-3 text-center text-[10px] uppercase tracking-widest font-bold border border-zinc-900">Parcial 2</th>
                        <th class="py-2 px-3 text-center text-[10px] uppercase tracking-widest font-bold border border-zinc-900">Parcial 3</th>
                        <th class="py-2 px-4 text-center text-[11px] uppercase tracking-widest font-bold border border-zinc-900 bg-zinc-800">Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materias_data)): ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-zinc-400 text-sm border border-zinc-300">No hay materias cursadas en este semestre.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($materias_data as $m): ?>
                            <tr class="even:bg-zinc-50">
                                <td class="py-3 px-4 text-xs text-zinc-800 border border-zinc-300"><?php echo htmlspecialchars($m['nombre']); ?></td>
                                <td class="py-3 px-3 text-center font-mono text-sm border border-zinc-300"><?php echo $m['p1']; ?></td>
                                <td class="py-3 px-3 text-center font-mono text-sm border border-zinc-300"><?php echo $m['p2']; ?></td>
                                <td class="py-3 px-3 text-center font-mono text-sm border border-zinc-300"><?php echo $m['p3']; ?></td>
                                <td class="py-3 px-4 text-center font-mono text-sm font-bold <?php echo ($m['final'] !== '-' && floatval($m['final']) < 7.5) ? 'text-rose-600' : 'text-zinc-900'; ?> border border-zinc-300 bg-zinc-50/50">
                                    <?php echo $m['final']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Promedio General del Ciclo -->
                        <tr>
                            <td colspan="4" class="py-3 px-4 text-right text-xs font-bold text-zinc-600 uppercase tracking-widest border border-zinc-300">
                                Promedio del Semestre:
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-base font-bold bg-zinc-100 border border-zinc-300 text-zinc-900">
                                <?php echo $promedio_ciclo; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Footer / Firmas -->
        <footer class="mt-16 pt-8 grid grid-cols-2 gap-12 text-center text-zinc-900">
            <div>
                <div class="border-b border-zinc-400 pb-8 mx-12"></div>
                <p class="text-[10px] uppercase tracking-widest font-bold mt-2">Sello de Control Escolar</p>
                <p class="text-[8px] text-zinc-500 mt-1">AF Instituto Experto</p>
            </div>
            <div>
                <div class="border-b border-zinc-400 pb-8 mx-12"></div>
                <p class="text-[10px] uppercase tracking-widest font-bold mt-2">Firma Dirección</p>
                <p class="text-[8px] text-zinc-500 mt-1">Aval de Calificaciones</p>
            </div>
        </footer>
        
    </div>

</body>
</html>
