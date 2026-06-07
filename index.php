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
                        class="block w-full px-0 py-3 bg-transparent border-b border-gray-300 text-zinc-800 placeholder-transparent focus:outline-none focus:border-zinc-950 transition-colors duration-300 rounded-none font-light pr-10"
                        placeholder="Contraseña">
                    <label for="password"
                        class="absolute left-0 -top-3.5 text-gray-400 text-xs transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-placeholder-shown:top-3 peer-focus:-top-3.5 peer-focus:text-green-600 peer-focus:text-xs tracking-wide">
                        CONTRASEÑA
                    </label>
                    <button type="button" onclick="togglePassword('password', 'eye-icon-login')" class="absolute right-0 top-3 text-gray-400 hover:text-black transition-colors">
                        <svg id="eye-icon-login" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                    <div class="mt-2 text-right">
                        <a href="register.php"
                            class="text-[10px] uppercase tracking-widest text-gray-400 hover:text-black transition-colors">¿Olvidaste?
                            O Registrate</a>
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

            <div class="mt-4">
                <a href="version1/index.php"
                    class="block w-full py-4 border border-zinc-200 text-gray-400 text-xs uppercase tracking-[0.2em] hover:border-zinc-950 hover:text-zinc-950 transition-colors duration-500 font-medium text-center">
                    Ir a Versión 1
                </a>
            </div>

            <div class="mt-20 text-center border-t border-gray-50 pt-8">
                <p class="text-xs text-gray-400 opacity-60 font-light">Powered by Vizone Ultra v3.0</p>
            </div>
        </div>
    </div>

</body>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />`;
    } else {
        input.type = "password";
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`;
    }
}
</script>
</html>