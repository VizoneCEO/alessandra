<?php
session_start();

// --- Seguridad ---
// 1. Comprobar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
// 2. Comprobar si el usuario es Profesor (perfil_id = 2)
if ($_SESSION['perfil_id'] != 2) {
    header("Location: ../../index.php");
    exit();
}

$view = isset($_GET['view']) ? $_GET['view'] : 'main';
// main => body_clases.php (por defecto en el layout original del profesor solía ser así o 'clases')
// Ajustaremos para que 'main' sea la vista de clases.
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Profesor | AF V3</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Playfair Display', 'serif'],
                        mono: ['Courier New', 'monospace'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Custom Scrollbar for tables if needed */
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d4d4d8;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a1a1aa;
        }
    </style>
</head>

<body class="bg-slate-50 h-screen flex overflow-hidden">

    <!-- Sidebar (Noir Style) -->
    <aside class="w-64 bg-zinc-950 text-white flex-shrink-0 flex flex-col border-r border-zinc-900 shadow-2xl z-20">
        <!-- Logo Header -->
        <div class="h-24 flex items-center justify-center border-b border-zinc-800/50 bg-zinc-950">
            <img src="../../front/multimedia/logoA.png" alt="AF Logo"
                class="h-10 opacity-90 invert brightness-0 filter bg-white/10 rounded px-2 py-1">
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-8 space-y-2 overflow-y-auto">

            <p class="text-[10px] uppercase tracking-[0.2em] text-zinc-600 font-bold mb-4 px-4">Gestión Académica</p>

            <!-- Link: Mis Clases (Main) -->
            <a href="dashboard.php?view=main"
                class="group flex items-center px-4 py-3 text-sm font-medium rounded-md transition-all duration-200 relative overflow-hidden
               <?php echo ($view == 'main') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900'; ?>">

                <?php if ($view == 'main'): ?>
                    <span class="absolute left-0 top-0 bottom-0 w-1 bg-amber-600 rounded-r"></span>
                <?php endif; ?>

                <i
                    class="fas fa-chalkboard-teacher w-6 text-center text-lg mr-3 <?php echo ($view == 'main') ? 'text-amber-500' : 'text-zinc-600 group-hover:text-amber-500'; ?> transition-colors"></i>
                <span>Mis Clases</span>
            </a>

            <!-- Link: Reportes -->
            <a href="dashboard.php?view=reporte"
                class="group flex items-center px-4 py-3 text-sm font-medium rounded-md transition-all duration-200 relative overflow-hidden
               <?php echo ($view == 'reporte') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900'; ?>">

                <?php if ($view == 'reporte'): ?>
                    <span class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-600 rounded-r"></span>
                <?php endif; ?>

                <i
                    class="fas fa-chart-line w-6 text-center text-lg mr-3 <?php echo ($view == 'reporte') ? 'text-emerald-500' : 'text-zinc-600 group-hover:text-emerald-500'; ?> transition-colors"></i>
                <span>Reporte Ejecutivo</span>
            </a>

            <!-- Link: No Calificados (Helper) -->
            <a href="dashboard.php?view=no_calificados"
                class="group flex items-center px-4 py-3 text-sm font-medium rounded-md transition-all duration-200 relative overflow-hidden
               <?php echo ($view == 'no_calificados') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900'; ?>">

                <?php if ($view == 'no_calificados'): ?>
                    <span class="absolute left-0 top-0 bottom-0 w-1 bg-rose-600 rounded-r"></span>
                <?php endif; ?>

                <i
                    class="fas fa-clipboard-check w-6 text-center text-lg mr-3 <?php echo ($view == 'no_calificados') ? 'text-rose-500' : 'text-zinc-600 group-hover:text-rose-500'; ?> transition-colors"></i>
                <span>Pendientes</span>
            </a>

        </nav>

        <!-- Footer Profile -->
        <div class="p-6 border-t border-zinc-900 bg-zinc-950">
            <div class="flex items-center mb-4">
                <div
                    class="h-8 w-8 rounded-full bg-zinc-800 flex items-center justify-center text-white mr-3 border border-zinc-700">
                    <i class="fas fa-user-tie text-xs"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate">
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-[10px] text-zinc-500 uppercase tracking-widest">Profesor</p>
                </div>
            </div>
            <a href="../../back/logout.php"
                class="block w-full py-2 text-center border border-zinc-800 rounded text-[10px] uppercase font-bold tracking-wider text-zinc-500 hover:text-rose-500 hover:border-rose-900 transition-colors">
                Cerrar Sesión
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col relative overflow-hidden bg-slate-50">

        <!-- Minimal Navbar (Solo título dinámico) -->
        <header
            class="h-20 flex items-center justify-between px-8 md:px-12 bg-white/80 backdrop-blur border-b border-zinc-100 sticky top-0 z-10">
            <div>
                <h1 class="font-serif text-2xl text-zinc-900 italic">
                    <?php if ($view == 'main')
                        echo 'Gestión de Clases'; ?>
                    <?php if ($view == 'reporte')
                        echo 'Reporte Ejecutivo'; ?>
                    <?php if ($view == 'no_calificados')
                        echo 'Alumnos Pendientes'; ?>
                </h1>
            </div>
            <!-- Botón móvil si fuera necesario, por ahora desktop first según diseño -->
            <div class="text-right">
                <span class="text-[10px] font-mono text-zinc-400"><?php echo date('d M Y'); ?></span>
            </div>
        </header>

        <!-- Dynamic Content -->
        <div class="flex-1 overflow-y-auto p-8 md:p-12">
            <?php
            $allowed = ['main', 'reporte', 'no_calificados'];
            if (in_array($view, $allowed)) {
                // Mapeo 'main' a body_clases.php porque es el comportamiento legacy
                if ($view == 'main')
                    include 'body_clases.php';
                else
                    include 'body_' . $view . '.php';
            } else {
                // Fallback
                include 'body_clases.php';
            }
            ?>
        </div>

    </main>

    <!-- Bootstrap Bundle JS (SOLAMENTE SI ES TOTALMENTE NECESARIO PARA COMPATIBILIDAD DE VIEJOS MODALES) -->
    <!-- En este diseño V3 intentaremos no usarlo, pero si body_clases o reportes usan modales de bootstrap, lo dejamos comentado o lo incluimos condicionalmente -->
    <!-- Por seguridad y compatibilidad con scripts existentes en body_clases, lo dejo, pero minimizado -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>