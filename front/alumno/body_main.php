<div class="row">
    <h3 class="fs-4 mb-3">Dashboard</h3>
    <div class="col">
        <p>¡Hola de nuevo, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>!</p>
        <p>Bienvenido a tu panel de control.</p>
    </div>
</div>

<hr>

<div class="row mt-4">
    <div class="col">
        <h4><i class="fas fa-bell me-2"></i>Notificaciones</h4>
         <div class="alert alert-secondary">
            <p class="mb-0">Aún no tienes notificaciones nuevas.</p>
            <small>(Próximamente: Aquí verás avisos de nuevas calificaciones, tareas por vencer, etc.)</small>
        </div>
    </div>
</div>