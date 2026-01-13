<?php
session_start();

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['perfil_id'] != 4) {
    header("Location: ../../index.php");
    exit();
}

$view = isset($_GET['view']) ? $_GET['view'] : 'menu';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Seguridad | AF Luxury</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-zinc-950 h-screen flex flex-col text-white overflow-hidden">

    <!-- Header -->
    <header class="h-16 border-b border-zinc-800 flex items-center justify-between px-6 bg-zinc-950 z-20">
        <div class="flex items-center gap-3">
            <img src="../../front/multimedia/logoA.png" class="h-6 opacity-80 invert brightness-0 filter">
            <div class="h-4 w-px bg-zinc-800"></div>
            <p class="text-xs uppercase tracking-[0.2em] text-zinc-400 font-bold">Seguridad</p>
        </div>

        <div class="flex items-center gap-4">
            <span class="text-xs text-zinc-500 hidden md:block">
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </span>
            <a href="../../back/logout.php"
                class="text-rose-500 hover:text-rose-400 text-xs font-bold uppercase tracking-wider border border-rose-500/30 px-3 py-1.5 rounded hover:bg-rose-500/10 transition-colors">
                <i class="fas fa-power-off mr-1"></i> Salir
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto p-4 md:p-8 relative">
        <!-- Background Effects -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-emerald-500/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-indigo-500/5 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 max-w-5xl mx-auto h-full flex flex-col">
            <?php
            if ($view === 'menu') {
                include 'body_menu.php';
            } elseif ($view === 'scanner' || $view === 'scanner_evento') {
                // If scanner or scanner_evento, load scanner.
                // We might need to pass a variable to scanner to know mode.
                // For now, let's assume body_scanner handles general scanning.
                echo '<div class="mb-4"><a href="dashboard.php?view=menu" class="text-zinc-500 hover:text-white flex items-center gap-2 transition-colors"><i class="fas fa-arrow-left"></i> Regresar al Menú</a></div>';
                include 'body_scanner.php';
            } else {
                echo "<p>Vista no encontrada</p>";
            }
            ?>
        </div>
    </main>
</body>

</html>