<?php
// CONSERVA INTACTO todo el bloque de código PHP al inicio del archivo
// Lógica migrada de front/index/bodyIndex.php para mantener funcionalidad
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alessandra Farelli | Login</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .font-serif-display {
            font-family: 'Playfair Display', serif;
        }
    </style>
</head>

<body class="h-screen w-full flex overflow-hidden bg-white">

    <!-- Sección Izquierda: Imagen Couture B&W (Limpia) -->
    <div class="hidden lg:block w-1/2 h-full relative border-r border-gray-100">
        <img src="https://images.unsplash.com/photo-1539109136881-3be0616acf4b?q=80&w=1887&auto=format&fit=crop"
            alt="Haute Couture Black & White" class="absolute inset-0 w-full h-full object-cover">

        <!-- Overlay Negro Semitransparente Sutil -->
        <div class="absolute inset-0 bg-black/40"></div>
    </div>

    <!-- Sección Derecha: Formulario Minimalista -->
    <div class="w-full lg:w-1/2 h-full flex flex-col items-center justify-center p-10 lg:p-24 bg-white relative">

        <div class="w-full max-w-sm">
            <div class="mb-12 text-center pt-8 lg:pt-0">
                <!-- Logo Principal Centrado -->
                <img src="front/multimedia/logoA.png" alt="Alessandra Farelli" class="h-20 mx-auto mb-8"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <span class="hidden text-3xl font-serif-display tracking-widest uppercase mb-8">ALESSANDRA
                    FARELLI</span>

                <!-- Título -->
                <h1 class="text-5xl font-serif-display font-medium text-zinc-950 mb-4">Bienvenido</h1>
                <p class="text-gray-500 font-light text-sm tracking-wide">INGRESA TUS CREDENCIALES</p>
            </div>

            <?php
            // Lógica de error preservada
            if (isset($_SESSION['login_error'])) {
                echo '<div class="mb-8 border border-zinc-950 p-4 text-center">';
                echo '<p class="text-xs text-red-600 uppercase tracking-widest font-medium">' . htmlspecialchars($_SESSION['login_error']) . '</p>';
                echo '</div>';
                unset($_SESSION['login_error']);
            }
            ?>

            <!-- Formulario principal HIGH-END -->
            <form action="back/auth.php" method="POST" class="space-y-12">

                <div class="group relative">
                    <input type="text" id="curp" name="curp" required
                        class="block w-full px-0 py-3 bg-transparent border-b border-gray-300 text-zinc-800 placeholder-transparent focus:outline-none focus:border-zinc-950 transition-colors duration-300 rounded-none font-light"
                        placeholder="Usuario">
                    <label for="curp"
                        class="absolute left-0 -top-3.5 text-gray-400 text-xs transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-placeholder-shown:top-3 peer-focus:-top-3.5 peer-focus:text-green-600 peer-focus:text-xs tracking-wide">
                        USUARIO (CURP)
                    </label>
                </div>

                <div class="group relative">
                    <input type="password" id="password" name="password" required
                        class="block w-full px-0 py-3 bg-transparent border-b border-gray-300 text-zinc-800 placeholder-transparent focus:outline-none focus:border-zinc-950 transition-colors duration-300 rounded-none font-light"
                        placeholder="Contraseña">
                    <label for="password"
                        class="absolute left-0 -top-3.5 text-gray-400 text-xs transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-placeholder-shown:top-3 peer-focus:-top-3.5 peer-focus:text-green-600 peer-focus:text-xs tracking-wide">
                        CONTRASEÑA
                    </label>
                    <div class="absolute right-0 top-3">
                        <a href="register.php"
                            class="text-[10px] uppercase tracking-widest text-gray-400 hover:text-black transition-colors">¿Olvidaste?</a>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit"
                        class="w-full py-4 bg-zinc-950 text-white text-xs uppercase tracking-[0.2em] hover:bg-zinc-800 transition-colors duration-500 font-medium">
                        Iniciar Sesión
                    </button>
                </div>
            </form>

            <div class="mt-12 text-center">
                <button type="button" onclick="alert('Integración con Google próximamente')"
                    class="text-xs text-gray-400 uppercase tracking-widest hover:text-black transition-colors pb-1 border-b border-transparent hover:border-black">
                    Continuar con Google
                </button>
            </div>

            <div class="mt-20 text-center border-t border-gray-50 pt-8">
                <p class="text-xs text-gray-400 opacity-60 font-light">Powered by Vizone Ultra v3.0</p>
            </div>
        </div>
    </div>

</body>

</html>