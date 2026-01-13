<div class="flex flex-col items-center justify-center h-full animate-fade-in-up">
    <div class="text-center mb-10">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-2 tracking-tight">Panel de Seguridad</h2>
        <p class="text-zinc-400">Selecciona el modo de operación</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-4xl px-4">
        <!-- Card 1: Eventos -->
        <a href="dashboard.php?view=scanner_evento"
            class="group relative bg-zinc-900 border border-zinc-800 hover:border-emerald-500/50 rounded-2xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:bg-zinc-800/80 hover:scale-[1.02] cursor-pointer overflow-hidden">
            <div
                class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
            </div>

            <div
                class="h-20 w-20 bg-zinc-800 rounded-full flex items-center justify-center mb-6 text-zinc-400 group-hover:text-emerald-400 group-hover:bg-zinc-950 transition-colors z-10 shadow-lg border border-zinc-700 group-hover:border-emerald-500/30">
                <i class="fas fa-ticket-alt text-3xl"></i>
            </div>

            <h3 class="text-xl font-bold text-white mb-2 z-10 group-hover:text-emerald-400 transition-colors">Escanear
                Evento</h3>
            <p class="text-sm text-zinc-500 z-10">Validación de boletos QR para conciertos y eventos especiales.</p>
        </a>

        <!-- Card 2: Acceso Escolar -->
        <a href="dashboard.php?view=scanner"
            class="group relative bg-zinc-900 border border-zinc-800 hover:border-indigo-500/50 rounded-2xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:bg-zinc-800/80 hover:scale-[1.02] cursor-pointer overflow-hidden">
            <div
                class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
            </div>

            <div
                class="h-20 w-20 bg-zinc-800 rounded-full flex items-center justify-center mb-6 text-zinc-400 group-hover:text-indigo-400 group-hover:bg-zinc-950 transition-colors z-10 shadow-lg border border-zinc-700 group-hover:border-indigo-500/30">
                <i class="fas fa-id-card text-3xl"></i>
            </div>

            <h3 class="text-xl font-bold text-white mb-2 z-10 group-hover:text-indigo-400 transition-colors">Acceso
                Escolar</h3>
            <p class="text-sm text-zinc-500 z-10">Control de entrada y salida diaria de alumnos con credencial.</p>
        </a>
    </div>
</div>

<style>
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>