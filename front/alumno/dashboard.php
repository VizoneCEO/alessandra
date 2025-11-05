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

// Lógica para cargar la vista correcta
$view = isset($_GET['view']) ? $_GET['view'] : 'main';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Alumno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../admin/admin_style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-center py-4 primary-text fs-4 fw-bold text-uppercase border-bottom">
                <i class="fas fa-user-graduate me-2"></i>Alumno
            </div>
            <div class="list-group list-group-flush my-3">
                <a href="dashboard.php?view=main" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($view == 'main') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="dashboard.php?view=clases" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($view == 'clases') ? 'active' : ''; ?>">
                    <i class="fas fa-book-reader me-2"></i>Mis Clases
                </a>
                <a href="dashboard.php?view=historial" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($view == 'historial') ? 'active' : ''; ?>">
                    <i class="fas fa-history me-2"></i>Historial Académico
                </a>
                <a href="../../back/logout.php" class="list-group-item list-group-item-action bg-transparent text-danger fw-bold">
                    <i class="fas fa-power-off me-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">

                    <i class="fas fa-bars me-3" id="menu-toggle" style="font-size: 1.5rem; cursor: pointer; color: var(--main-text-color);"></i>
                    <h2 class="fs-2 m-0">Panel Principal</h2>
                </div>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="navbarDropdown" role="button">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <?php
                // --- Cargador de contenido ---
                $allowed_views = ['main', 'clases', 'historial'];
                if (in_array($view, $allowed_views)) {
                    include 'body_' . $view . '.php';
                } else {
                    // Si la vista no es válida, cargamos la principal
                    include 'body_main.php';
                }
                ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");

        toggleButton.onclick = function () {
            el.classList.toggle("toggled");
        };
    </script>
    </body>
</html>