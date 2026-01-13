<?php
session_start();

// --- Seguridad ---
// 1. Comprobar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
// 2. Comprobar si el usuario es Alumno (perfil_id = 3)
if ($_SESSION['perfil_id'] != 3) {
    header("Location: ../../index.php");
    exit();
}

// Conexión a Base de Datos (para uso en todas las vistas)
require_once '../../back/db_connect.php';

// Lógica para cargar la vista correcta
$view = isset($_GET['view']) ? $_GET['view'] : 'main';

// 3. Obtener Fotografía de Perfil
$sql_pic = "SELECT archivo_path FROM Documentos_Alumno 
            WHERE alumno_id = {$_SESSION['user_id']} 
            AND tipo_documento = 'Fotografía de Perfil' 
            ORDER BY id DESC LIMIT 1";
$res_pic = $conn->query($sql_pic);
$profile_pic = null;
if ($row_pic = $res_pic->fetch_assoc()) {
    $profile_pic = $row_pic['archivo_path'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Alumno | AF V3</title>
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
            background-color: #f8fafc;
        }

        /* Transición suave para el contenido */
        #page-content-wrapper {
            width: 100%;
            transition: all 0.3s;
        }

        /* Ocultar scrollbar del sidebar si es necesario */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="flex h-[100dvh] overflow-hidden bg-slate-50">

    <!-- Sidebar "Noir" -->
    <div class="hidden lg:flex bg-zinc-950 w-64 flex-shrink-0 flex-col h-full transition-all duration-300 relative border-r border-zinc-900"
        id="sidebar-wrapper">
        <!-- Logo Area -->
        <div class="h-24 flex items-center justify-center border-b border-zinc-900/50">
            <img src="../../front/multimedia/logoA.png" alt="AF Logo"
                class="h-10 opacity-90 invert brightness-0 filter bg-white/10 rounded px-2 py-1">
            <!-- Nota: Se usa un filtro invert o bg para que se vea el logo si es oscuro -->
        </div>

        <div class="p-6">
            <p class="text-[10px] uppercase tracking-[0.2em] text-zinc-500 mb-4 font-semibold">Menú Principal</p>
            <nav class="space-y-1">
                <a href="dashboard.php?view=main"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'main') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-columns w-6 <?php echo ($view == 'main') ? 'text-amber-500' : 'text-zinc-600 group-hover:text-amber-500'; ?> transition-colors"></i>
                    Dashboard
                </a>

                <a href="dashboard.php?view=credencial"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'credencial') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-id-badge w-6 <?php echo ($view == 'credencial') ? 'text-indigo-400' : 'text-zinc-600 group-hover:text-indigo-400'; ?> transition-colors"></i>
                    Credencial
                </a>

                <a href="dashboard.php?view=clases"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'clases') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-book w-6 <?php echo ($view == 'clases') ? 'text-emerald-500' : 'text-zinc-600 group-hover:text-emerald-500'; ?> transition-colors"></i>
                    Mis Clases
                </a>

                <a href="dashboard.php?view=historial"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'historial') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-clock w-6 <?php echo ($view == 'historial') ? 'text-blue-400' : 'text-zinc-600 group-hover:text-blue-400'; ?> transition-colors"></i>
                    Historial
                </a>

                <a href="dashboard.php?view=finanzas"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'finanzas') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-wallet w-6 <?php echo ($view == 'finanzas') ? 'text-emerald-500' : 'text-zinc-600 group-hover:text-emerald-500'; ?> transition-colors"></i>
                    Mis Finanzas
                </a>

                <a href="dashboard.php?view=boletos"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'boletos') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-ticket-alt w-6 <?php echo ($view == 'boletos') ? 'text-rose-500' : 'text-zinc-600 group-hover:text-rose-500'; ?> transition-colors"></i>
                    Mis Boletos
                </a>

                <a href="dashboard.php?view=portafolio"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'portafolio') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                    <i
                        class="fas fa-layer-group w-6 <?php echo ($view == 'portafolio') ? 'text-purple-400' : 'text-zinc-600 group-hover:text-purple-400'; ?> transition-colors"></i>
                    Portafolio
                    <a href="dashboard.php?view=documentos"
                        class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all group <?php echo ($view == 'documentos') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-900'; ?>">
                        <i
                            class="fas fa-file-alt w-6 <?php echo ($view == 'documentos') ? 'text-amber-500' : 'text-zinc-600 group-hover:text-amber-500'; ?> transition-colors"></i>
                        Documentación
                    </a>
            </nav>
        </div>

        <div class="mt-auto p-6 border-t border-zinc-900">
            <a href="../../back/logout.php"
                class="flex items-center px-4 py-2 text-xs font-medium text-rose-500 hover:text-rose-400 transition-colors">
                <i class="fas fa-power-off w-6"></i>
                CERRAR SESIÓN
            </a>
        </div>
    </div>

    <!-- Page Content -->
    <div class="flex-1 flex flex-col h-full overflow-hidden relative w-full" id="page-content-wrapper">

        <!-- Navbar Minimalista -->
        <header
            class="h-20 bg-white/80 backdrop-blur-md flex items-center justify-between px-8 border-b border-gray-100 z-10 sticky top-0">
            <div class="flex items-center">
                <button id="menu-toggle" class="hidden text-zinc-500 hover:text-zinc-900 focus:outline-none mr-4">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl font-serif text-zinc-900 italic">
                    <?php
                    if ($view == 'main')
                        echo 'Resumen General';
                    elseif ($view == 'clases')
                        echo 'Mis Clases';
                    elseif ($view == 'historial')
                        echo 'Historial Académico';
                    elseif ($view == 'finanzas')
                        echo 'Estado de Cuenta';
                    elseif ($view == 'boletos')
                        echo 'Mis Boletos';
                    elseif ($view == 'portafolio')
                        echo 'Mi Portafolio';
                    elseif ($view == 'documentos')
                        echo 'Expediente Digital';
                    elseif ($view == 'credencial')
                        echo 'Mi Credencial';
                    else
                        echo 'Panel Alumno';
                    ?>
                </h2>
            </div>

            <div class="flex items-center space-x-6">
                <!-- User Profile -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden md:block">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Alumno</p>
                        <p class="text-sm font-bold text-zinc-900">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </p>
                    </div>
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile"
                            class="h-10 w-10 rounded-full object-cover border border-zinc-200 shadow-sm">
                    <?php else: ?>
                        <div
                            class="h-10 w-10 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-900 border border-zinc-200">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 lg:p-10 no-scrollbar">
            <!-- Content Wrapper with safe bottom padding for mobile nav -->
            <div class="w-full pb-44 lg:pb-0">
                <?php
                // --- Cargador de contenido ---
                // --- Cargador de contenido ---
                $allowed_views = ['main', 'clases', 'historial', 'finanzas', 'boletos', 'portafolio', 'documentos', 'credencial'];
                if (in_array($view, $allowed_views)) {
                    include 'body_' . $view . '.php';
                } else {
                    include 'body_main.php';
                }
                ?>
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div
        class="lg:hidden fixed bottom-4 left-4 right-4 bg-zinc-900 rounded-2xl shadow-2xl z-50 flex justify-between gap-4 items-center px-6 py-4 overflow-x-auto no-scrollbar">

        <!-- 1. Dashboard -->
        <a href="dashboard.php?view=main"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'main') ? 'text-amber-500' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-columns text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Inicio</span>
        </a>

        <!-- 2. Credencial (ID) -->
        <a href="dashboard.php?view=credencial"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'credencial') ? 'text-indigo-400' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-id-badge text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">ID</span>
        </a>

        <!-- 3. Clases -->
        <a href="dashboard.php?view=clases"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'clases') ? 'text-emerald-500' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-book text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Clases</span>
        </a>

        <!-- 4. Historial -->
        <a href="dashboard.php?view=historial"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'historial') ? 'text-blue-400' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-clock text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Hist.</span>
        </a>

        <!-- 5. Finanzas (Pagos) -->
        <a href="dashboard.php?view=finanzas"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'finanzas') ? 'text-emerald-400' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-wallet text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Pagos</span>
        </a>

        <!-- Boletos -->
        <a href="dashboard.php?view=boletos"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'boletos') ? 'text-rose-500' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-ticket-alt text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Boletos</span>
        </a>

        <!-- 6. Portafolio -->
        <a href="dashboard.php?view=portafolio"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'portafolio') ? 'text-purple-400' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-layer-group text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Port.</span>
        </a>

        <!-- 7. Documentacion (Docs) -->
        <a href="dashboard.php?view=documentos"
            class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 <?php echo ($view == 'documentos') ? 'text-amber-500' : 'text-zinc-500 hover:text-zinc-300'; ?>">
            <i class="fas fa-file-alt text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Docs</span>
        </a>

        <!-- 8. Logout -->
        <a href="../../back/logout.php" class="flex flex-col items-center gap-1 min-w-[3rem] shrink-0 text-rose-500">
            <i class="fas fa-power-off text-lg"></i>
            <span class="text-[0.6rem] uppercase tracking-wider font-bold">Salir</span>
        </a>
    </div>

    <!-- Bootstrap Bundle JS (Solo por si algún modal lo necesita, aunque intentaremos prescindir) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById("sidebar-wrapper");
        const toggleButton = document.getElementById("menu-toggle");

        if (toggleButton) {
            toggleButton.onclick = function () {
                sidebar.classList.toggle("hidden");
            };
        }
    </script>
</body>

</html>