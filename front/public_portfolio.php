<?php
// No session check required for public view
require_once '../back/db_connect.php';

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id <= 0) {
    die("Perfil no encontrado o enlace inválido.");
}

// 1. Fetch Student Info
$sql_user = "SELECT nombre_completo FROM Usuarios WHERE id = $student_id AND perfil_id = 3";
$res_user = $conn->query($sql_user);

if ($res_user->num_rows === 0) {
    die("Perfil de alumno no encontrado.");
}
$student = $res_user->fetch_assoc();

// 2. Fetch Profile Pic
$sql_pic = "SELECT archivo_path FROM Documentos_Alumno 
            WHERE alumno_id = $student_id AND tipo_documento = 'Fotografía de Perfil' 
            ORDER BY id DESC LIMIT 1";
$res_pic = $conn->query($sql_pic);
$profile_pic = null;
if ($row_pic = $res_pic->fetch_assoc()) {
    // Path might be relative to back/ or root. stored as "../../uploads..." usually.
    // We are in front/public_portfolio.php.
    // If stored as "../../uploads/...", relative to front/alumno/...
    // Relative to front/ it is "../uploads/...".
    // Stored: ../../uploads/doc.jpg (relative to front/alumno)
    // We need: ../uploads/doc.jpg (relative to front)

    // Let's clean it up based on common storage pattern
    $rawPath = $row_pic['archivo_path'];
    $profile_pic = str_replace('../../', '../', $rawPath);
}

// 3. Fetch Projects
$sql_port = "SELECT * FROM Portafolio WHERE alumno_id = $student_id ORDER BY fecha_creacion DESC";
$res_port = $conn->query($sql_port);
$projects = [];
if ($res_port->num_rows > 0) {
    while ($row = $res_port->fetch_assoc()) {
        // Fix Image Path
        // Stored: ../../uploads/portafolio/file.jpg (relative to front/alumno)
        // We need: ../uploads/portafolio/file.jpg
        $row['imagen'] = str_replace('../../', '../', $row['imagen']); // Fix cover path

        // Fetch Gallery
        $pid = $row['id'];
        $sql_imgs = "SELECT * FROM Portafolio_Imagenes WHERE portafolio_id = $pid";
        $res_imgs = $conn->query($sql_imgs);
        $row['gallery'] = [];
        while ($img = $res_imgs->fetch_assoc()) {
            $img['imagen_path'] = str_replace('../../', '../', $img['imagen_path']); // Fix gallery path
            $row['gallery'][] = $img;
        }
        $projects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portafolio de
        <?php echo htmlspecialchars($student['nombre_completo']); ?> | Alessandra Fashion
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .font-serif {
            font-family: 'Playfair Display', serif;
        }
    </style>
</head>

<body class="bg-zinc-50 text-zinc-900 min-h-screen">

    <!-- Hero Header -->
    <header class="bg-zinc-900 text-white pt-20 pb-24 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10"
            style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
        <div class="max-w-6xl mx-auto px-6 relative z-10 flex flex-col md:flex-row items-center md:items-end gap-8">
            <!-- Profile Pic -->
            <div class="relative">
                <?php if ($profile_pic && file_exists(__DIR__ . '/' . $profile_pic)): ?>
                    <img src="<?php echo $profile_pic; ?>" alt="Profile"
                        class="w-32 h-32 md:w-40 md:h-40 rounded-full object-cover border-4 border-zinc-800 shadow-2xl">
                <?php else: ?>
                    <div
                        class="w-32 h-32 md:w-40 md:h-40 rounded-full bg-zinc-800 flex items-center justify-center text-4xl text-zinc-600 border-4 border-zinc-700">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute bottom-2 right-2 bg-emerald-500 w-4 h-4 rounded-full border-2 border-zinc-900">
                </div>
            </div>

            <div class="text-center md:text-left">
                <p class="text-zinc-500 uppercase tracking-widest text-xs font-bold mb-2">Diseñador(a) de Modas</p>
                <h1 class="font-serif text-4xl md:text-5xl font-bold mb-4">
                    <?php echo htmlspecialchars($student['nombre_completo']); ?>
                </h1>
                <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                    <span class="px-3 py-1 bg-white/10 rounded-full text-xs backdrop-blur-sm">Alessandra Fashion
                        Institute</span>
                    <span class="px-3 py-1 bg-white/10 rounded-full text-xs backdrop-blur-sm">Portafolio Oficial</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-6 -mt-12 relative z-20 pb-20">

        <?php if (empty($projects)): ?>
            <div class="bg-white rounded-xl shadow-xl p-12 text-center">
                <i class="fas fa-folder-open text-4xl text-zinc-300 mb-4"></i>
                <h3 class="font-serif text-xl text-zinc-900">Este portafolio aún no tiene proyectos publicados.</h3>
                <p class="text-zinc-500 mt-2">Vuelve pronto para ver las creaciones.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($projects as $p): ?>
                    <div onclick='openProject(<?php echo json_encode($p); ?>)'
                        class="group bg-white rounded-xl shadow-sm hover:shadow-2xl transition-all duration-500 overflow-hidden cursor-pointer transform hover:-translate-y-1">
                        <div class="h-80 overflow-hidden relative">
                            <img src="<?php echo $p['imagen']; ?>"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            <div class="absolute inset-0 bg-black/20 group-hover:bg-black/40 transition-colors"></div>

                            <div
                                class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black/80 to-transparent translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                                <p class="text-zinc-300 text-[10px] uppercase tracking-[0.2em] mb-1">
                                    <?php echo htmlspecialchars($p['categoria']); ?>
                                </p>
                                <h3 class="text-white font-serif text-2xl group-hover:text-white transition-colors">
                                    <?php echo htmlspecialchars($p['titulo']); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Project Modal -->
    <div id="viewModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title-view" role="dialog"
        aria-modal="true">
        <div class="fixed inset-0 bg-zinc-900/90 backdrop-blur-sm transition-opacity opacity-0" id="viewOverlay"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all w-full max-w-4xl opacity-0 scale-95"
                    id="viewPanel">

                    <button onclick="closeModal()"
                        class="absolute top-4 right-4 z-20 bg-white/20 hover:bg-white/40 text-white rounded-full p-2 backdrop-blur transition-colors">
                        <i class="fas fa-times text-xl w-6 h-6 flex items-center justify-center"></i>
                    </button>

                    <div class="flex flex-col md:flex-row h-[80vh] md:h-[600px]">
                        <!-- Main Image Column -->
                        <div class="w-full md:w-1/2 bg-zinc-100 h-64 md:h-full relative group">
                            <img id="viewMainImage" src="" class="w-full h-full object-cover">
                        </div>

                        <!-- Details & Gallery Column -->
                        <div class="w-full md:w-1/2 p-8 overflow-y-auto bg-white flex flex-col">
                            <div>
                                <span id="viewCategory"
                                    class="text-xs font-bold uppercase tracking-widest text-indigo-600 mb-2 block"></span>
                                <h2 id="viewTitle" class="font-serif text-3xl md:text-4xl font-bold text-zinc-900 mb-6">
                                </h2>

                                <div class="w-12 h-1 bg-zinc-100 mb-8"></div>

                                <h4 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-4">Galería</h4>
                                <div id="viewGallery" class="grid grid-cols-3 gap-3">
                                    <!-- Gallery Items -->
                                </div>
                            </div>

                            <div class="mt-auto pt-8 border-t border-zinc-100 text-center">
                                <p class="text-xs text-zinc-400">© 2026 Alessandra Fashion Institute</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        function openProject(p) {
            const project = typeof p === 'string' ? JSON.parse(p) : p;

            document.getElementById('viewMainImage').src = project.imagen;
            document.getElementById('viewTitle').innerText = project.titulo;
            document.getElementById('viewCategory').innerText = project.categoria;

            const gallery = document.getElementById('viewGallery');
            gallery.innerHTML = '';

            // Add Cover to Grid first? Or just gallery?
            // Let's add Cover as selectable active image
            addToGalleryGrid(project.imagen, true);

            if (project.gallery && project.gallery.length > 0) {
                project.gallery.forEach(img => {
                    addToGalleryGrid(img.imagen_path, false);
                });
            }

            const modal = document.getElementById('viewModal');
            const overlay = document.getElementById('viewOverlay');
            const panel = document.getElementById('viewPanel');

            modal.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'scale-95');
            }, 10);
        }

        function addToGalleryGrid(src, isActive) {
            const container = document.getElementById('viewGallery');
            const btn = document.createElement('button');
            btn.className = `relative aspect-square rounded-lg overflow-hidden border-2 transition-all ${isActive ? 'border-zinc-900 opacity-100' : 'border-transparent opacity-60 hover:opacity-100'}`;
            btn.innerHTML = `<img src="${src}" class="w-full h-full object-cover">`;
            btn.onclick = () => {
                document.getElementById('viewMainImage').src = src;
                // Reset active states
                Array.from(container.children).forEach(c => {
                    c.classList.remove('border-zinc-900', 'opacity-100');
                    c.classList.add('border-transparent', 'opacity-60');
                });
                btn.classList.remove('border-transparent', 'opacity-60');
                btn.classList.add('border-zinc-900', 'opacity-100');
            };
            container.appendChild(btn);
        }

        function closeModal() {
            const modal = document.getElementById('viewModal');
            const overlay = document.getElementById('viewOverlay');
            const panel = document.getElementById('viewPanel');

            overlay.classList.add('opacity-0');
            panel.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    </script>
</body>

</html>