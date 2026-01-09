<?php
// --- DATA REAL PARA PORTAFOLIO ---
$alumno_id = $_SESSION['user_id'];
$sql_port = "SELECT * FROM Portafolio WHERE alumno_id = $alumno_id ORDER BY fecha_creacion DESC";
$res_port = $conn->query($sql_port);
$proyectos = [];
if ($res_port->num_rows > 0) {
    while ($row = $res_port->fetch_assoc()) {
        // Fetch Gallery Images
        $pid = $row['id'];
        $sql_imgs = "SELECT * FROM Portafolio_Imagenes WHERE portafolio_id = $pid";
        $res_imgs = $conn->query($sql_imgs);
        $row['gallery'] = [];
        while ($img = $res_imgs->fetch_assoc()) {
            $row['gallery'][] = $img;
        }
        $proyectos[] = $row;
    }
}
?>

<!-- Header Section -->
<div class="flex flex-col md:flex-row justify-between items-end mb-10 pb-6 border-b border-zinc-200">
    <div>
        <h3 class="font-serif text-4xl text-zinc-900 mb-2">Mi Portafolio Creativo</h3>
        <p class="text-zinc-500 font-light text-sm">Tu carta de presentación al mundo de la moda</p>
    </div>

    <div class="flex items-center space-x-4 mt-6 md:mt-0">
        <button onclick="toggleModal('shareModal')"
            class="px-5 py-3 border border-zinc-300 text-zinc-600 text-xs font-bold uppercase tracking-widest hover:bg-zinc-50 hover:text-zinc-900 transition-colors bg-white rounded-md flex items-center">
            <i class="fas fa-share-alt mr-2"></i> Compartir
        </button>
        <button onclick="toggleModal('projectModal')"
            class="px-5 py-3 bg-zinc-950 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition-all rounded-md flex items-center shadow-lg transform hover:-translate-y-0.5">
            <i class="fas fa-plus mr-2"></i> Nuevo Proyecto
        </button>
    </div>
</div>

<?php if (empty($proyectos)): ?>
    <!-- Empty State -->
    <div
        class="flex flex-col items-center justify-center p-20 border-2 border-dashed border-zinc-200 rounded-xl bg-zinc-50">
        <div class="h-16 w-16 bg-zinc-200 rounded-full flex items-center justify-center text-zinc-400 mb-6">
            <i class="fas fa-camera text-2xl"></i>
        </div>
        <h4 class="text-lg font-serif text-zinc-900 mb-2">Aún no has subido diseños</h4>
        <p class="text-zinc-500 font-light text-sm mb-6">Empieza a construir tu legado visual hoy mismo.</p>
        <button onclick="toggleModal('projectModal')"
            class="px-6 py-3 bg-zinc-900 text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-700 transition-colors rounded">
            Subir Primer Proyecto
        </button>
    </div>
<?php else: ?>
    <!-- Masonry Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($proyectos as $p): ?>
            <div onclick='openEditModal(<?php echo json_encode($p); ?>)'
                class="group relative rounded-lg overflow-hidden cursor-pointer shadow-sm hover:shadow-xl transition-all duration-500 bg-zinc-900 h-80 lg:h-96">
                <!-- Imagen -->
                <img src="<?php echo $p['imagen']; ?>" alt="<?php echo htmlspecialchars($p['titulo']); ?>"
                    class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 opacity-90 group-hover:opacity-60">

                <!-- Overlay Hover -->
                <div
                    class="absolute inset-0 flex flex-col justify-end p-8 opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                    <p
                        class="text-zinc-300 text-[10px] uppercase tracking-[0.2em] font-medium mb-1 translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-75">
                        <?php echo htmlspecialchars($p['categoria']); ?>
                    </p>
                    <h3
                        class="text-white font-serif text-2xl mb-4 translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-100">
                        <?php echo htmlspecialchars($p['titulo']); ?>
                    </h3>

                    <div
                        class="flex space-x-2 translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-150">
                        <button onclick='event.stopPropagation(); openEditModal(<?php echo json_encode($p); ?>)'
                            class="px-4 py-2 border border-white/30 hover:border-white text-white text-[10px] uppercase tracking-widest transition-colors backdrop-blur-sm rounded">
                            Editar / Ver
                        </button>
                        <button onclick="event.stopPropagation(); deleteProject(<?php echo $p['id']; ?>)"
                            class="px-4 py-2 border border-red-500/50 hover:border-red-500 bg-red-500/10 text-white text-[10px] uppercase tracking-widest transition-colors backdrop-blur-sm rounded">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ================= MODALES ================= -->

<!-- 1. Modal Nuevo Proyecto -->
<div id="projectModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title-project" role="dialog"
    aria-modal="true">
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm transition-opacity opacity-0" id="projectOverlay"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg scale-95 opacity-0"
                id="projectPanel">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-8">
                    <div class="text-center sm:text-left">
                        <h3 class="font-serif text-2xl font-bold text-zinc-900 mb-1" id="modal-title-project">Nuevo
                            Proyecto</h3>
                        <p class="text-sm text-zinc-500 mb-6">Añade una nueva pieza a tu colección.</p>

                        <form class="space-y-5" id="projectForm" onsubmit="uploadProject(event)">
                            <!-- Titulo -->
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-2">Título
                                    de la Obra</label>
                                <input type="text" name="titulo"
                                    class="block w-full border-b border-zinc-200 py-2 text-zinc-900 placeholder-zinc-300 focus:border-zinc-900 focus:outline-none transition-colors"
                                    placeholder="Ej. Colección Primavera 2026" required>
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-2">Categoría</label>
                                <select name="categoria"
                                    class="block w-full border-b border-zinc-200 py-2 text-zinc-900 focus:border-zinc-900 focus:outline-none bg-transparent">
                                    <option value="Editorial">Editorial</option>
                                    <option value="Haute Couture">Haute Couture</option>
                                    <option value="Streetwear">Streetwear</option>
                                    <option value="Accesorios">Accesorios</option>
                                    <option value="Prêt-à-porter">Prêt-à-porter</option>
                                </select>
                            </div>

                            <!-- Upload -->
                            <div id="uploadDropzone"
                                class="mt-2 flex justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-10 hover:bg-zinc-50 transition-colors cursor-pointer">
                                <div class="text-center">
                                    <i class="fas fa-image text-3xl text-zinc-300 mb-3"></i>
                                    <div class="mt-0 flex text-sm leading-6 text-zinc-600 justify-center">
                                        <label
                                            class="relative cursor-pointer rounded-md bg-transparent font-semibold text-zinc-900 focus-within:outline-none hover:text-zinc-700">
                                            <span>Sube archivos</span>
                                            <input type="file" name="imagen[]" multiple class="sr-only"
                                                onchange="document.querySelector('#fileName').innerText = this.files.length + ' archivos seleccionados'"
                                                required>
                                        </label>
                                        <p class="pl-1">o arrastra y suelta</p>
                                    </div>
                                    <p class="text-xs leading-5 text-zinc-500">PNG, JPG hasta 10MB</p>
                                    <p id="fileName" class="text-xs font-bold text-zinc-900 mt-2"></p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-3">
                                <button type="button" onclick="toggleModal('projectModal')"
                                    class="px-4 py-2 border border-zinc-200 rounded text-xs font-bold uppercase tracking-widest text-zinc-500 hover:bg-zinc-50 hover:text-zinc-900 transition-colors">Cancelar</button>
                                <button type="submit"
                                    class="px-6 py-2 bg-zinc-900 rounded text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-700 transition-colors shadow-lg">Publicar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Modal Editar Proyecto -->
<div id="editModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title-edit" role="dialog"
    aria-modal="true">
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm transition-opacity opacity-0" id="editOverlay"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl scale-95 opacity-0"
                id="editPanel">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-8">
                    <div class="text-center sm:text-left">
                        <h3 class="font-serif text-2xl font-bold text-zinc-900 mb-1">Editar Proyecto</h3>
                        <p class="text-sm text-zinc-500 mb-6">Gestiona el contenido de tu proyecto.</p>

                        <form class="space-y-5" id="editForm" onsubmit="updateProject(event)">
                            <input type="hidden" name="id" id="editId">

                            <!-- Titulo -->
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-2">Título
                                    de la Obra</label>
                                <input type="text" name="titulo" id="editTitulo"
                                    class="block w-full border-b border-zinc-200 py-2 text-zinc-900 focus:outline-none"
                                    required>
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-2">Categoría</label>
                                <select name="categoria" id="editCategoria"
                                    class="block w-full border-b border-zinc-200 py-2 text-zinc-900 focus:outline-none bg-transparent">
                                    <option value="Editorial">Editorial</option>
                                    <option value="Haute Couture">Haute Couture</option>
                                    <option value="Streetwear">Streetwear</option>
                                    <option value="Accesorios">Accesorios</option>
                                    <option value="Prêt-à-porter">Prêt-à-porter</option>
                                </select>
                            </div>

                            <!-- Gallery Management -->
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-widest text-zinc-500 mb-4">Galería
                                    de Imágenes</label>
                                <div id="editGalleryGrid" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <!-- Dynamic Content -->
                                </div>
                            </div>

                            <!-- Add More Images -->
                            <div
                                class="mt-2 flex justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-4 hover:bg-zinc-50 transition-colors cursor-pointer">
                                <div class="text-center">
                                    <label
                                        class="relative cursor-pointer rounded-md bg-transparent font-semibold text-zinc-900 focus-within:outline-none hover:text-zinc-700">
                                        <span>Agregar más imágenes</span>
                                        <input type="file" name="imagen_new[]" multiple class="sr-only"
                                            onchange="document.querySelector('#newFilesCount').innerText = this.files.length + ' nuevas seleccionadas'">
                                    </label>
                                    <p id="newFilesCount" class="text-xs font-bold text-zinc-900 mt-1"></p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end space-x-3">
                                <button type="button" onclick="toggleModal('editModal')"
                                    class="px-4 py-2 border border-zinc-200 rounded text-xs font-bold uppercase tracking-widest text-zinc-500 hover:bg-zinc-50">Cancelar</button>
                                <button type="submit"
                                    class="px-6 py-2 bg-zinc-900 rounded text-white text-xs font-bold uppercase tracking-widest hover:bg-zinc-700 shadow-lg">Guardar
                                    Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Modal Compartir -->
<div id="shareModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title-share" role="dialog"
    aria-modal="true">
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm transition-opacity opacity-0" id="shareOverlay"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md scale-95 opacity-0"
                id="sharePanel">
                <div class="bg-white p-6 sm:p-8">
                    <div class="text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 mb-4">
                            <i class="fas fa-globe-americas text-2xl text-indigo-600"></i>
                        </div>
                        <h3 class="font-serif text-2xl font-bold text-zinc-900 mb-2">Comparte tu Talento</h3>
                        <p class="text-sm text-zinc-500 mb-6">Tu enlace público está listo para ser visto por el mundo.
                        </p>

                        <!-- Link Input -->
                        <?php
                        $host = $_SERVER['HTTP_HOST'];
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        // Determine relative path based on setup regardless of folder depth
                        // Just hardcode the path structure we know: alessandra/alessandra/front/public_portfolio.php
                        $publicUrl = "$protocol://$host/alessandra/alessandra/front/public_portfolio.php?id=" . $_SESSION['user_id'];
                        ?>
                        <div class="flex rounded-md shadow-sm mb-6">
                            <input type="text" id="shareLink"
                                class="block w-full rounded-l-md border-0 py-2.5 px-3 text-zinc-900 ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-zinc-900 sm:text-sm sm:leading-6 bg-zinc-50 font-mono"
                                value="<?php echo $publicUrl; ?>" readonly>
                            <button type="button" onclick="copyLink()"
                                class="relative -ml-px inline-flex items-center gap-x-1.5 rounded-r-md px-3 py-2 text-sm font-semibold text-zinc-900 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>

                        <!-- Social Icons Removed -->
                        <div class="hidden"></div>

                    </div>
                    <div class="mt-8">
                        <button type="button" onclick="toggleModal('shareModal')"
                            class="inline-flex w-full justify-center rounded-md bg-white px-3 py-3 text-xs font-bold uppercase tracking-widest text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 sm:col-start-1 sm:mt-0">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Manejador Genérico de Modales
    function toggleModal(modalID) {
        const modal = document.getElementById(modalID);
        let overlayID = 'shareOverlay';
        let panelID = 'sharePanel';

        if (modalID === 'projectModal') {
            overlayID = 'projectOverlay';
            panelID = 'projectPanel';
        } else if (modalID === 'editModal') {
            overlayID = 'editOverlay';
            panelID = 'editPanel';
        }

        const overlay = document.getElementById(overlayID);
        const panel = document.getElementById(panelID);

        if (modal.classList.contains('hidden')) {
            // Abrir
            modal.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'scale-95');
            }, 10);
        } else {
            // Cerrar
            overlay.classList.add('opacity-0');
            panel.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    // Abrir Modal de Edición
    function openEditModal(project) {
        // Parse project if it's a string (from data attribute)
        const p = typeof project === 'string' ? JSON.parse(project) : project;

        document.getElementById('editId').value = p.id;
        document.getElementById('editTitulo').value = p.titulo;
        document.getElementById('editCategoria').value = p.categoria;

        // Render Gallery
        const grid = document.getElementById('editGalleryGrid');
        grid.innerHTML = '';

        // Add Main Image (Cover) - Not deletable as individual image here, only changeable via update?
        // Actually, let's treat it as read-only preview or allow update.
        // For simplicity, we just show gallery images + cover if we want.
        // But backend logic separates them. Let's show gallery images from Portafolio_Imagenes.

        // Show Cover
        const coverDiv = document.createElement('div');
        coverDiv.className = 'relative group aspect-square rounded-lg overflow-hidden border border-zinc-200';
        coverDiv.innerHTML = `
            <img src="${p.imagen}" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black/50 flex items-center justify-center text-white text-[10px] ">Portada</div>
        `;
        grid.appendChild(coverDiv);

        // Show Gallery Items
        if (p.gallery && p.gallery.length > 0) {
            p.gallery.forEach(img => {
                const div = document.createElement('div');
                div.className = 'relative group aspect-square rounded-lg overflow-hidden';
                div.innerHTML = `
                    <img src="${img.imagen_path}" class="w-full h-full object-cover">
                    <button type="button" onclick="deleteImage(${img.id}, this)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 transition-colors">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                `;
                grid.appendChild(div);
            });
        }

        toggleModal('editModal');
    }

    // Actualizar Proyecto
    function updateProject(event) {
        event.preventDefault();

        const form = document.getElementById('editForm');
        const formData = new FormData(form);
        formData.append('action', 'update');

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
        btn.disabled = true;

        fetch('../../back/alumno_actions_portafolio.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error comunicación servidor.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    // Eliminar Imagen de Galería
    function deleteImage(imgId, btnElement) {
        if (!confirm('¿Eliminar esta imagen?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_image');
        formData.append('img_id', imgId);

        fetch('../../back/alumno_actions_portafolio.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove element from DOM
                    btnElement.closest('div').remove();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Subir Proyecto
    function uploadProject(event) {
        event.preventDefault();

        const form = document.getElementById('projectForm');
        const formData = new FormData(form);
        formData.append('action', 'create');

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Subiendo...';
        btn.disabled = true;

        fetch('../../back/alumno_actions_portafolio.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error en la comunicación con el servidor.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    // Eliminar Proyecto
    function deleteProject(id) {
        if (!confirm('¿Estás seguro de que deseas eliminar este proyecto? Esta acción no se puede deshacer.')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('../../back/alumno_actions_portafolio.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al intentar eliminar.');
            });
    }

    // Inicializar dropzone al cargar
    document.addEventListener('DOMContentLoaded', () => {
        setupDropzone('uploadDropzone', 'projectForm');
    });

    function setupDropzone(zoneID, formID) {
        const zone = document.getElementById(zoneID);
        // Find input by name to be sure
        const input = zone.querySelector('input[type="file"]');

        if (!zone || !input) return;

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop area
        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, () => zone.classList.add('bg-zinc-100', 'border-zinc-400'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, () => zone.classList.remove('bg-zinc-100', 'border-zinc-400'), false);
        });

        // Handle dropped files
        zone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                input.files = files; // Assign dropped files to input
                // Trigger change event manually to update UI text
                const event = new Event('change');
                input.dispatchEvent(event);
            }
        }, false);

        // Also trigger input click if zone is clicked
        zone.addEventListener('click', (e) => {
            if (e.target !== input) {
                input.click();
            }
        });
    }

    function copyLink() {
        const link = document.getElementById('shareLink');
        link.select();
        navigator.clipboard.writeText(link.value);
        alert('Enlace copiado al portapapeles');
    }
</script>