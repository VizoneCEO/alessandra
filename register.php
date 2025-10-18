<?php
// Iniciar sesión para poder mostrar mensajes de error/éxito
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="front/index/login.css">
    <title>Crear Contraseña</title>
</head>
<body>

<div class="login-container">
    <div class="card login-card">
        <div class="login-card-header">
            <h1>Crear Contraseña</h1>
        </div>

        <div class="login-card-body">

            <?php
            // Bloque para mostrar mensajes
            if (isset($_SESSION['register_error'])) {
                echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['register_error']) . '</div>';
                unset($_SESSION['register_error']);
            }
            if (isset($_SESSION['register_success'])) {
                echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['register_success']) . '</div>';
                unset($_SESSION['register_success']);
            }
            ?>

            <p class="text-muted mb-4">Si eres un usuario nuevo o necesitas registrar tu contraseña por primera vez, completa este formulario.</p>

            <form action="back/handle_register.php" method="POST">
                <div class="mb-3">
                    <label for="curp" class="form-label">Usuario (CURP)</label>
                    <input type="text" class="form-control" id="curp" name="curp" placeholder="Ingresa tu CURP" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Crea tu contraseña" required>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Escribe la contraseña de nuevo" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">Guardar Contraseña</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="index.php" class="forgot-password-link">Volver a Iniciar Sesión</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>