<?php
session_start();

// --- Seguridad ---
// 1. Comprobar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
// 2. Comprobar si el usuario es administrador (perfil_id = 1)
if ($_SESSION['perfil_id'] != 1) {
    // Si no es admin, lo redirigimos a su dashboard correspondiente o al login
    header("Location: ../../index.php"); // O a una página de "acceso denegado"
    exit();
}

// Lógica para cargar la página correcta en el contenido principal
$page = isset($_GET['page']) ? $_GET['page'] : 'main';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-center py-4 primary-text fs-4 fw-bold text-uppercase border-bottom">
                <i class="fas fa-user-shield me-2"></i>Admin Panel
            </div>
            <div class="list-group list-group-flush my-3">
                <a href="dashboard.php?page=main" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($page == 'main') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="dashboard.php?page=usuarios" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($page == 'usuarios') ? 'active' : ''; ?>">
                    <i class="fas fa-users me-2"></i>Gestor de Usuarios
                </a>
                <a href="dashboard.php?page=ciclos" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($page == 'ciclos') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Gestión de Ciclos
                </a>
                <a href="dashboard.php?page=materias" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($page == 'materias') ? 'active' : ''; ?>">
                    <i class="fas fa-book me-2"></i>Gestión de Materias
                </a>
                <a href="dashboard.php?page=asignacion" class="list-group-item list-group-item-action bg-transparent second-text <?php echo ($page == 'asignacion') ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus me-2"></i>Asignación de Alumnos
                </a>
                <a href="../../back/logout.php" class="list-group-item list-group-item-action bg-transparent text-danger fw-bold">
                    <i class="fas fa-power-off me-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
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
                // Lista blanca de páginas permitidas para seguridad
                $allowed_pages = ['main', 'usuarios', 'ciclos', 'materias', 'asignacion'];
                if (in_array($page, $allowed_pages)) {
                    include 'body_' . $page . '.php';
                } else {
                    // Si la página no está en la lista, mostramos el dashboard principal
                    include 'body_main.php';
                }
                ?>
            </div>
        </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>