<?php
// Scanner Body
?>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    // Determine Scan Mode from PHP View
    const SCAN_MODE = '<?php echo ($view === "scanner_evento") ? "event" : "access"; ?>';
</script>

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

            <!-- FALLBACK FOR HTTP (REMOVED) -->
            <!-- 
            <div class="mt-6 border-t border-zinc-700 pt-4">...</div>
            -->
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
            <input type="text" id="manualCurp" placeholder="Ingresar CURP o Nombre Manualmente"
                class="flex-1 bg-zinc-900 border border-zinc-800 text-white text-sm px-4 py-3 rounded-lg focus:border-emerald-500 outline-none uppercase font-mono">
            <button type="submit" class="bg-zinc-800 hover:bg-zinc-700 text-white px-4 rounded-lg transition-colors">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>

</div>

</div>

<!-- CANDIDATES MODAL (Multiple Users Matches) -->
<div id="candidatesModal"
    class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 transition-all duration-300">
    <div
        class="bg-zinc-900 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl border border-zinc-800 flex flex-col max-h-[80vh]">
        <div class="bg-zinc-800 p-4 border-b border-zinc-700 flex justify-between items-center">
            <h3 class="text-white font-serif text-lg">Seleccionar Alumno</h3>
            <button onclick="closeCandidates()" class="text-zinc-400 hover:text-white"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
            <p class="text-xs text-zinc-500 mb-3">Se encontraron múltiples coincidencias. Selecciona al correcto:</p>
            <div id="candidatesList" class="space-y-2">
                <!-- Dynamic List Items -->
            </div>
        </div>
    </div>
</div>

<!-- TICKET SELECTION MODAL (New) -->
<div id="ticketSelectionModal"
    class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 transition-all duration-300">
    <div
        class="bg-zinc-900 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl border border-zinc-800 flex flex-col max-h-[80vh]">
        <div class="bg-emerald-900/30 p-4 border-b border-emerald-500/30 flex justify-between items-center">
            <h3 class="text-white font-serif text-lg">Seleccionar Boleto</h3>
            <button onclick="closeTicketSelection()" class="text-zinc-400 hover:text-white"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
            <!-- Student Info Header -->
            <div class="flex items-center gap-3 mb-6 p-3 bg-zinc-800 rounded-lg">
                <img id="tsPhoto" src="" class="w-10 h-10 rounded-full object-cover">
                <div>
                    <p class="text-[10px] text-zinc-400 uppercase">Alumno</p>
                    <p id="tsName" class="text-sm font-bold text-white leading-tight">Nombre</p>
                </div>
            </div>

            <p class="text-xs text-zinc-500 mb-3">Boletos disponibles encontrados:</p>
            <div id="ticketList" class="space-y-3">
                <!-- Dynamic Tickets -->
            </div>
        </div>
    </div>
</div>

<!-- RESULTS MODAL -->
<div id="resultModal"
    class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 transition-all duration-300">
    <div class="bg-zinc-900 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl border border-zinc-800 transform scale-95 opacity-0 transition-all duration-300"
        id="resultCard">
        <!-- Content injected via JS -->
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
        let bodyPayload = 'mode=' + SCAN_MODE; // Send mode

        let isJsonTicket = false;

        // Handle "TICKET:ID" format (From Downloaded Images)
        if (code.startsWith('TICKET:')) {
            const ticketId = code.split(':')[1];
            if (ticketId) {
                // Emulate JSON structure expected by backend
                const fakeJson = JSON.stringify({ id: ticketId });
                bodyPayload += '&ticket_data=' + encodeURIComponent(fakeJson);
                isJsonTicket = true;
            }
        }

        if (SCAN_MODE === 'event' && !isJsonTicket) {
            // Try to parse as Ticket JSON (From Screen QR)
            try {
                let jsonObj = JSON.parse(code);
                if (jsonObj && jsonObj.id) {
                    bodyPayload += '&ticket_data=' + encodeURIComponent(code);
                    isJsonTicket = true;
                }
            } catch (e) {
                // Not JSON, assume manual search (Name/CURP)
                isJsonTicket = false;
            }
        }

        if (!isJsonTicket) {
            // Treated as manual search (CURP or Name) for both modes
            let term = code;
            if (code.includes('|')) {
                const parts = code.split('|');
                term = parts[1]; // Index 1 is CURP
            }
            bodyPayload += '&curp=' + encodeURIComponent(term); // Reusing 'curp' param for search term
        }

        try {
            const response = await fetch('../../back/validate_access.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyPayload
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
        // Handle Multiple Candidates (Access Mode)
        if (data.match_type === 'multiple') {
            showCandidates(data.candidates);
            return;
        }

        // Handle Ticket Selection (Event Mode Search)
        if (data.match_type === 'ticket_selection') {
            showTicketSelection(data);
            return;
        }

        const modal = document.getElementById('resultModal');
        const card = document.getElementById('resultCard');

        // Remove hidden class
        modal.classList.remove('hidden');

        let contentHTML = '';

        if (!data.success) {
            // --- ERROR STATE ---
            contentHTML = `
                <div class="bg-rose-600 p-8 text-center relative overflow-hidden">
                    <!-- Background Pattern -->
                    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-black via-transparent to-transparent"></div>
                    
                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm animate-pulse relative z-10">
                        <i class="fas fa-times text-4xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-serif text-white font-bold tracking-wide relative z-10">ACCESO DENEGADO</h3>
                    
                    ${data.student && data.student.evento ? `
                    <div class="mt-4 pt-4 border-t border-white/20 relative z-10">
                        <p class="text-rose-200 text-xs font-bold uppercase tracking-widest mb-1">${data.student.evento}</p>
                        <p class="text-3xl font-black text-white font-mono tracking-tighter">
                            <span class="text-rose-300 text-lg align-top mr-1">#</span>${data.student.folio_display}
                        </p>
                    </div>
                    ` : ''}
                </div>
                <div class="p-8 text-center bg-zinc-900">
                     <p class="text-rose-400 text-lg font-medium mb-6">${data.message}</p>
                     
                     ${data.student ? `
                     <div class="bg-rose-500/10 border border-rose-500/20 rounded-xl p-4 flex items-center gap-4 text-left max-w-sm mx-auto">
                        <img src="${data.student.foto || 'https://ui-avatars.com/api/?name=User'}" class="w-12 h-12 rounded-full object-cover bg-zinc-800">
                        <div>
                            <p class="text-[10px] uppercase text-rose-300 font-bold">Propietario del Boleto/Acceso</p>
                            <p class="text-white font-bold text-sm leading-tight">${data.student.nombre}</p>
                        </div>
                     </div>
                     ` : ''}

                     <button onclick="closeModal()" class="mt-8 w-full py-4 bg-white text-zinc-950 font-bold uppercase tracking-widest hover:bg-zinc-200 transition-colors rounded-xl shadow-lg">
                        Intentar de Nuevo
                    </button>
                </div>
            `;
        } else if (data.is_ticket && data.data) {
            // --- PREMIUM TICKET SUCCESS STATE ---
            const t = data.data; // Event, Folio, Seat, etc.
            const typeKey = (t.tipo_boleto || 'GENERICO').toUpperCase();
            
            // Define Color Themes per Type
            const theme = {
                'ALUMNO': { 
                    bgHeader: 'bg-blue-950', 
                    accent: 'text-blue-500', 
                    glow: 'shadow-[0_0_40px_rgba(59,130,246,0.8)]',
                    ring: 'ring-blue-500/10',
                    iconBg: 'bg-blue-500/20',
                    icon: 'text-blue-500',
                    btn: 'bg-blue-600 hover:bg-blue-500',
                    btnShadow: 'shadow-blue-900/20'
                },
                'INVITADO': { 
                    bgHeader: 'bg-purple-950', 
                    accent: 'text-purple-500', 
                    glow: 'shadow-[0_0_40px_rgba(168,85,247,0.8)]',
                    ring: 'ring-purple-500/10',
                    iconBg: 'bg-purple-500/20',
                    icon: 'text-purple-500',
                    btn: 'bg-purple-600 hover:bg-purple-500',
                    btnShadow: 'shadow-purple-900/20'
                },
                'MODELO': { 
                    bgHeader: 'bg-pink-950', 
                    accent: 'text-pink-500', 
                    glow: 'shadow-[0_0_40px_rgba(236,72,153,0.8)]',
                    ring: 'ring-pink-500/10',
                    iconBg: 'bg-pink-500/20',
                    icon: 'text-pink-500',
                    btn: 'bg-pink-600 hover:bg-pink-500',
                    btnShadow: 'shadow-pink-900/20'
                },
                'STAFF': { 
                    bgHeader: 'bg-zinc-900', 
                    accent: 'text-zinc-500', 
                    glow: 'shadow-[0_0_40px_rgba(113,113,122,0.8)]',
                    ring: 'ring-zinc-500/10',
                    iconBg: 'bg-zinc-500/20',
                    icon: 'text-zinc-400',
                    btn: 'bg-zinc-700 hover:bg-zinc-600',
                    btnShadow: 'shadow-zinc-900/20'
                }
            }[typeKey] || { 
                // Default (Emerald)
                bgHeader: 'bg-zinc-950', 
                accent: 'text-emerald-500', 
                glow: 'shadow-[0_0_40px_rgba(16,185,129,0.8)]',
                ring: 'ring-emerald-500/10',
                iconBg: 'bg-emerald-500/20',
                icon: 'text-emerald-500',
                btn: 'bg-emerald-600 hover:bg-emerald-500',
                btnShadow: 'shadow-emerald-900/20'
            };

            contentHTML = `
                <!-- Ticket Header -->
                <div class="relative ${theme.bgHeader} p-6 text-center border-b border-zinc-800 overflow-hidden">
                    <!-- Glow Effect -->
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-1 bg-current ${theme.accent} ${theme.glow}"></div>
                    
                    <div class="relative z-10">
                        <p class="text-[10px] uppercase tracking-[0.3em] ${theme.accent} font-bold mb-2">ACCESS GRANTED</p>
                        <h2 class="text-xl md:text-2xl font-serif text-white font-bold leading-tight uppercase mb-4">${t.evento}</h2>
                        
                        <!-- BIG TICKET TYPE LABEL -->
                        <div class="inline-block px-4 py-1.5 rounded border border-white/10 bg-black/20 backdrop-blur-sm">
                             <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/90">${typeKey}</p>
                        </div>
                    </div>
                </div>

                <!-- Ticket Body -->
                <div class="bg-zinc-900 p-8 relative">
                    <!-- Ticket Notch Left/Right -->
                    <div class="absolute top-0 left-0 w-6 h-6 bg-black rounded-full -mt-3 -ml-3"></div>
                    <div class="absolute top-0 right-0 w-6 h-6 bg-black rounded-full -mt-3 -mr-3"></div>

                    <div class="flex flex-col items-center">
                        <div class="w-24 h-24 ${theme.iconBg} rounded-full flex items-center justify-center mb-6 ring-4 ${theme.ring}">
                            <i class="fas fa-check text-4xl ${theme.icon}"></i>
                        </div>

                        <div class="text-center mb-8">
                            <p class="text-xs text-zinc-500 uppercase tracking-widest mb-2">Asiento / Folio</p>
                            <p class="text-6xl font-black text-white tracking-tighter font-mono">
                                <span class="text-zinc-600 text-3xl align-top mr-1">#</span>${t.folio}
                            </p>
                        </div>

                        <!-- Attendee Card -->
                        <div class="w-full bg-zinc-950/50 border border-zinc-800 rounded-xl p-4 flex items-center gap-4">
                            <img src="${t.foto || 'https://ui-avatars.com/api/?name=User'}" class="w-14 h-14 rounded-full object-cover border-2 border-zinc-800">
                            <div>
                                <p class="text-[9px] uppercase ${theme.accent} font-bold tracking-wider mb-0.5">Asistente Verificado</p>
                                <p class="text-white font-bold text-sm leading-snug">${t.alumno}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <button onclick="closeModal()" class="mt-8 w-full py-4 ${theme.btn} text-white font-bold uppercase tracking-widest transition-colors rounded-xl shadow-lg ${theme.btnShadow} flex items-center justify-center gap-2">
                        <span>Escanear Siguiente</span> <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <div class="mt-4 text-center">
                        <p class="text-[9px] text-zinc-600 font-mono uppercase">Ticket ID: Verified • Secure Access</p>
                    </div>
                </div>
            `;
        } else {
            // --- STANDARD ACCESS SUCCESS STATE ---
            const s = data.student;
            contentHTML = `
                <div class="bg-emerald-600 p-8 text-center">
                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-sm">
                        <i class="fas fa-check text-4xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-serif text-white font-bold">ACCESO CONCEDIDO</h3>
                </div>
                <div class="p-8 bg-zinc-900">
                    <div class="flex items-start gap-6">
                        <img src="${s.foto || 'https://ui-avatars.com/api/?name=User'}" class="w-24 h-24 rounded-full object-cover border-2 border-zinc-700 bg-zinc-800">
                        <div class="flex-1">
                            <p class="text-[10px] uppercase text-zinc-500 tracking-widest mb-1">Alumno</p>
                            <h4 class="text-xl font-bold text-white leading-tight mb-2">${s.nombre}</h4>

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <p class="text-[9px] uppercase text-zinc-600 tracking-wider mb-1">Status</p>
                                    <p class="text-sm font-mono text-emerald-400">${s.status_text}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] uppercase text-zinc-600 tracking-wider mb-1">Vigencia</p>
                                    <p class="text-sm font-mono text-zinc-300">${s.vigencia}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="closeModal()" class="mt-8 w-full py-4 bg-white text-zinc-950 font-bold uppercase tracking-widest hover:bg-zinc-200 transition-colors rounded-xl">
                        Escanear Siguiente
                    </button>
                </div>
            `;
        }

        // Inject HTML
        card.innerHTML = contentHTML;

        // Animate Entry
        setTimeout(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
            // Play Sound
            if (data.success) {
                soundSuccess.currentTime = 0;
                soundSuccess.play().catch(e => console.log('Autoplay blocked'));
            } else {
                soundError.currentTime = 0;
                soundError.play().catch(e => console.log('Autoplay blocked'));
            }
        }, 10);
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

    // Ticket Selection Logic
    function showTicketSelection(data) {
        const modal = document.getElementById('ticketSelectionModal');
        const container = document.getElementById('ticketList');
        const photo = document.getElementById('tsPhoto');
        const name = document.getElementById('tsName');

        container.innerHTML = '';

        // Populate Student Header
        name.innerText = data.student.nombre;
        photo.src = data.student.foto || 'https://ui-avatars.com/api/?name=User';

        data.tickets.forEach(t => {
            const item = document.createElement('div');
            item.className = 'bg-zinc-800 p-4 rounded-xl border border-zinc-700 hover:border-emerald-500 cursor-pointer transition-colors group';
            item.onclick = () => { selectTicket(t.id); };
            item.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <h4 class="text-white font-bold text-sm group-hover:text-emerald-400 transition-colors">${t.evento}</h4>
                    <span class="bg-emerald-900/50 text-emerald-400 text-[10px] px-2 py-0.5 rounded uppercase font-bold tracking-wider">Disponible</span>
                </div>
                <div class="flex justify-between items-end">
                    <p class="text-zinc-500 text-xs">${t.fecha}</p>
                    <p class="text-emerald-500 font-mono font-bold text-xl">#${t.folio}</p>
                </div>
            `;
            container.appendChild(item);
        });

        modal.classList.remove('hidden');
    }

    function selectTicket(ticketId) {
        document.getElementById('ticketSelectionModal').classList.add('hidden');
        // Create Virtual QR Code JSON
        const virtualCode = JSON.stringify({ id: ticketId });
        processCode(virtualCode);
    }

    function closeTicketSelection() {
        document.getElementById('ticketSelectionModal').classList.add('hidden');
    }

    window.closeModal = function () {
        const modal = document.getElementById('resultModal');
        const card = document.getElementById('resultCard');

        card.classList.remove('scale-100', 'opacity-100');
        card.classList.add('scale-95', 'opacity-0');

        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('manualCurp').value = '';

            // Resume Scanner
            if (html5QrCode) {
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