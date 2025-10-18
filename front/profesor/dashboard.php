<?php session_start(); ?>
<h1>Dashboard del Profesor</h1>
<p>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?>.</p>
<a href="../../back/logout.php">Cerrar Sesión</a>