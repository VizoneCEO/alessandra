<?php
// Scanner Body
?>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="h-full flex flex-col items-center justify-center p-4">

    <!-- Header Instructions -->
    <div class="text-center mb-8">
        <h2 class="text-3xl font-serif text-white mb-2">Escáner de Acceso</h2>
        <p class="text-zinc-500 text-sm">Coloca el código QR del alumno frente a la cámara.</p>
    </div>

    <!-- Scanner Container -->
    <div
        class="relative w-full max-w-md aspect-square bg-zinc-900 rounded-2xl overflow-hidden border-2 border-zinc-800 shadow-2xl flex flex-col items-center justify-center">

        <!-- Camera area (hidden initially) -->
        <div id="reader" class="w-full h-full object-cover hidden"></div>

        <!-- Start Button (Visible initially) -->
        <div id="startContainer" class="text-center p-6 z-30">
            <div class="w-20 h-20 bg-zinc-800 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                <i class="fas fa-camera text-3xl text-zinc-500"></i>
            </div>
            <button onclick="startCamera()"
                class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 px-8 rounded-full uppercase tracking-widest text-xs transition-transform transform active:scale-95 shadow-lg shadow-emerald-900/50">
                Activar Cámara
            </button>
            <p id="permError" class="text-rose-500 text-xs mt-4 hidden max-w-xs mx-auto text-center"></p>

            <!-- FALLBACK FOR HTTP -->
            <div class="mt-6 border-t border-zinc-700 pt-4">
                <p class="text-zinc-500 text-[10px] mb-2 uppercase tracking-wider">¿Problemas con la cámara?</p>
                <label
                    class="cursor-pointer inline-flex items-center gap-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors border border-zinc-700">
                    <i class="fas fa-camera-retro"></i> Tomar Foto
                    <input type="file" id="scanFile" accept="image/*" capture="environment" class="hidden"
                        onchange="scanFromFile(this)">
                </label>
            </div>
        </div>

        <!-- Overlay Guide (Only visible when active) -->
        <div id="overlayGuide"
            class="absolute inset-0 border-[40px] border-zinc-950/80 z-10 pointer-events-none hidden items-center justify-center">
            <div class="w-64 h-64 border-2 border-white/20 rounded-lg relative">
                <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-emerald-500 -mt-1 -ml-1"></div>
                <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-emerald-500 -mt-1 -mr-1"></div>
                <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-emerald-500 -mb-1 -ml-1">
                </div>
                <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-emerald-500 -mb-1 -mr-1">
                </div>
            </div>
        </div>

        <!-- Scanning Indicator -->
        <div id="scanIndicator" class="absolute top-4 left-0 right-0 text-center z-20 hidden">
            <span
                class="bg-red-500/90 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest animate-pulse">
                <i class="fas fa-circle text-[8px] mr-1"></i> En Vivo
            </span>
        </div>
    </div>

    <!-- Manual Input Option (Fallback) -->
    <div class="mt-8 w-full max-w-sm">
        <form id="manualForm" class="flex gap-2">
            <input type="text" id="manualCurp" placeholder="Ingresar CURP Manualmente"
                class="flex-1 bg-zinc-900 border border-zinc-800 text-white text-sm px-4 py-3 rounded-lg focus:border-emerald-500 outline-none uppercase font-mono">
            <button type="submit" class="bg-zinc-800 hover:bg-zinc-700 text-white px-4 rounded-lg transition-colors">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>

</div>

</div>

<!-- CANDIDATES MODAL (Multiple Matches) -->
<div id="candidatesModal" class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 transition-all duration-300">
    <div class="bg-zinc-900 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl border border-zinc-800 flex flex-col max-h-[80vh]">
        <div class="bg-zinc-800 p-4 border-b border-zinc-700 flex justify-between items-center">
            <h3 class="text-white font-serif text-lg">Seleccionar Alumno</h3>
            <button onclick="closeCandidates()" class="text-zinc-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
            <p class="text-xs text-zinc-500 mb-3">Se encontraron múltiples coincidencias. Selecciona al correcto:</p>
            <div id="candidatesList" class="space-y-2">
                <!-- Dynamic List Items -->
            </div>
        </div>
    </div>
</div>

<!-- RESULTS MODAL -->
<div id="resultModal" class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 transition-all duration-300">
    <div class="bg-zinc-900 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl border border-zinc-800 transform scale-95 opacity-0 transition-all duration-300" id="resultCard">
        
        <!-- Status Header -->
        <div id="resultHeader" class="bg-emerald-600 p-6 text-center">
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-sm">
                <i id="resultIcon" class="fas fa-check text-4xl text-white"></i>
            </div>
            <h3 id="resultTitle" class="text-2xl font-serif text-white font-bold">ACCESO CONCEDIDO</h3>
        </div>

        <!-- Student Details -->
        <div class="p-8">
            <div class="flex items-start gap-6">
                <img id="resultPhoto" src=""
                    class="w-24 h-24 rounded-full object-cover border-2 border-zinc-700 bg-zinc-800">
                <div class="flex-1">
                    <p class="text-[10px] uppercase text-zinc-500 tracking-widest mb-1">Alumno</p>
                    <h4 id="resultName" class="text-xl font-bold text-white leading-tight mb-2">Nombre del Alumno</h4>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <p class="text-[9px] uppercase text-zinc-600 tracking-wider mb-1">Status</p>
                            <p id="resultStatus" class="text-sm font-mono text-emerald-400">ACTIVO</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-zinc-600 tracking-wider mb-1">Vigencia</p>
                            <p id="resultVigencia" class="text-sm font-mono text-zinc-300">30/06/2026</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="resultMessage"
                class="mt-6 p-3 bg-red-500/10 border border-red-500/30 rounded text-red-400 text-xs text-center hidden">
                <!-- Error messages go here -->
            </div>

            <button onclick="closeModal()"
                class="mt-8 w-full py-4 bg-white text-zinc-950 font-bold uppercase tracking-widest hover:bg-zinc-200 transition-colors rounded-xl">
                Escanear Siguiente
            </button>
        </div>
    </div>
</div>

<!-- JS Logic -->
<script>
    const soundSuccess = new Audio('../../front/multimedia/success.mp3');
    const soundError = new Audio('../../front/multimedia/error.mp3');

    let html5QrCode = null;

    function startCamera() {
        const errorMsg = document.getElementById('permError');
        errorMsg.classList.add('hidden');

        // Check for Secure Context (HTTPS)
        if (location.hostname !== "localhost" && location.hostname !== "127.0.0.1" && location.protocol !== 'https:') {
            errorMsg.innerHTML = "<b>¡Error de Seguridad!</b><br>El navegador bloquea la cámara en conexiones no seguras (HTTP).<br>Por favor usa <b>localhost</b> o configura <b>HTTPS</b>.";
            errorMsg.classList.remove('hidden');
            return;
        }

        // Use Html5Qrcode (API) instead of Scanner (UI Widget)
        html5QrCode = new Html5Qrcode("reader");

        const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };

        // Request Camera
        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            (errorMessage) => {
                // Ignore frame parse errors
            }
        ).then(() => {
            // Success: Switch UI
            document.getElementById('startContainer').classList.add('hidden');
            document.getElementById('reader').classList.remove('hidden');
            document.getElementById('overlayGuide').classList.remove('hidden');
            document.getElementById('overlayGuide').classList.add('flex');
            document.getElementById('scanIndicator').classList.remove('hidden');
        }).catch((err) => {
            // Permission Error or other
            console.error(err);
            console.log(JSON.stringify(err));

            let userMsg = "Error: No se pudo acceder a la cámara.";

            if (err.name === "NotAllowedError" || err.name === "PermissionDeniedError") {
                userMsg = "<b>Permiso Denegado.</b><br>Por favor permite el acceso a la cámara en tu navegador.";
            } else if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
                userMsg = "<b>Cámara no encontrada.</b><br>No se detectó ninguna cámara.";
            } else if (err.name === "NotReadableError" || err.name === "TrackStartError") {
                userMsg = "<b>Cámara en uso.</b><br>Cierra otras apps que usen la cámara.";
            } else {
                userMsg += "<br><span class='text-[10px] opacity-70'>" + err + "</span>";
            }

            errorMsg.innerHTML = userMsg;
            errorMsg.classList.remove('hidden');
        });
    }

    // Initialize Scanner Success Callback
    function onScanSuccess(decodedText, decodedResult) {
        // Pause to avoid multiple triggers
        if (html5QrCode) {
            html5QrCode.pause(true);
        }
        processCode(decodedText);
    }

    // Manual Submit
    document.getElementById('manualForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const curp = document.getElementById('manualCurp').value;
        if (curp) processCode(curp);
    });

    async function processCode(code) {
        let curp = code;
        if (code.includes('|')) {
            const parts = code.split('|');
            curp = parts[1]; // Index 1 is CURP
        }

        try {
            const response = await fetch('../../back/validate_access.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'curp=' + encodeURIComponent(curp)
            });
            const data = await response.json();
            showResult(data);
        } catch (error) {
            console.error(error);
            alert("Error de conexión");
            // Resume if error
            if (html5QrCode) html5QrCode.resume();
        }
    }

    function showResult(data) {
        // Handle Multiple Candidates
        if (data.match_type === 'multiple') {
            showCandidates(data.candidates);
            return;
        }

        const modal = document.getElementById('resultModal');
        const card = document.getElementById('resultCard');
        const header = document.getElementById('resultHeader');
        const icon = document.getElementById('resultIcon');
        const title = document.getElementById('resultTitle');
        const photo = document.getElementById('resultPhoto');
        const name = document.getElementById('resultName');
        const status = document.getElementById('resultStatus');
        const vigencia = document.getElementById('resultVigencia');
        const msg = document.getElementById('resultMessage');

        modal.classList.remove('hidden');
        msg.classList.add('hidden');
        
        // Populate Data
        name.innerText = data.student ? data.student.nombre : 'Desconocido';
        photo.src = (data.student && data.student.foto) ? data.student.foto : 'https://ui-avatars.com/api/?name=User&background=3f3f46&color=fff';
        vigencia.innerText = data.student ? data.student.vigencia : '-';
        status.innerText = data.student ? data.student.status_text : '-';

        // Animate Entry
        setTimeout(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        }, 10);

        if (data.success) {
            header.className = 'bg-emerald-600 p-6 text-center';
            icon.className = 'fas fa-check text-4xl text-white';
            title.innerText = 'ACCESO CONCEDIDO';
        } else {
            header.className = 'bg-rose-600 p-6 text-center';
            icon.className = 'fas fa-times-circle text-4xl text-white';
            title.innerText = 'ACCESO DENEGADO';
            
            msg.innerText = data.message;
            msg.classList.remove('hidden');
        }
    }

    // Candidate List Logic
    function showCandidates(list) {
        const modal = document.getElementById('candidatesModal');
        const container = document.getElementById('candidatesList');
        container.innerHTML = '';

        list.forEach(u => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-3 bg-zinc-800/50 hover:bg-zinc-700 rounded-lg cursor-pointer border border-zinc-800 transition-colors';
            item.onclick = () => { selectCandidate(u.curp); };
            item.innerHTML = `
                <div>
                    <p class="text-sm font-bold text-white">${u.nombre}</p>
                    <p class="text-[10px] text-zinc-400 font-mono">${u.curp}</p>
                </div>
                <div class="text-xs text-zinc-500">
                    <i class="fas fa-chevron-right"></i>
                </div>
            `;
            container.appendChild(item);
        });

        modal.classList.remove('hidden');
    }

    function selectCandidate(curp) {
        document.getElementById('candidatesModal').classList.add('hidden');
        document.getElementById('manualCurp').value = curp;
        processCode(curp);
    }

    function closeCandidates() {
        document.getElementById('candidatesModal').classList.add('hidden');
    }

    window.closeModal = function() {
        const modal = document.getElementById('resultModal');
        const card = document.getElementById('resultCard');
        
        card.classList.remove('scale-100', 'opacity-100');
        card.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('manualCurp').value = '';
            
            // Resume Scanner
            if(html5QrCode) {
                 html5QrCode.resume();
            }
        }, 300);
    }
</script>
<style>
    /* Force Video to fill container */
    #reader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }
</style>