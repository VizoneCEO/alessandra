<?php
// Iniciar la sesión si no está iniciada para poder leer los mensajes de error
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="login-container">
    <div class="card login-card">

        <div class="login-card-header">
            <img src="front/multimedia/logoA.png" alt="Logo de la Escuela" class="school-logo">
            <h1>Sistema de Calificaciones</h1>
        </div>

        <div class="login-card-body">

            <?php
            // Este bloque PHP muestra un mensaje de error si el login falla
            if (isset($_SESSION['login_error'])) {
                echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                // Una vez mostrado, eliminamos el mensaje para que no se repita
                unset($_SESSION['login_error']);
            }
            ?>

            <form action="back/auth.php" method="POST">
                <div class="mb-4">
                    <label for="curp" class="form-label">Usuario (CURP)</label>
                    <input type="text" class="form-control" id="curp" name="curp" placeholder="Ingresa tu CURP" required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">Iniciar Sesión</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="register.php" class="forgot-password-link">¿No tienes contraseña o la olvidaste?</a>
            </div>

            <div class="text-center mt-3 pt-3 border-top">
                <a href="version1/index.php" class="btn btn-outline-secondary btn-sm">Versión 1</a>
            </div>
            </div>

    </div>
</div>