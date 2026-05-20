<?php
session_start();
date_default_timezone_set('America/Mexico_City');
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/config_manager.php';
require_once '../includes/markdown_parser.php';

requireLogin();

$config = getSiteConfig();

// Procesar actualización de configuración si viene por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_config') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Error de validación de seguridad (CSRF).");
    }

    $config['social']['facebook'] = $_POST['social_facebook'] ?? '';
    $config['social']['twitter'] = $_POST['social_twitter'] ?? '';
    $config['social']['instagram'] = $_POST['social_instagram'] ?? '';
    
    $config['banners']['top'] = $_POST['banner_top'] ?? '';
    $config['banners']['sidebar1'] = $_POST['banner_sidebar1'] ?? '';
    $config['banners']['sidebar2'] = $_POST['banner_sidebar2'] ?? '';
    $config['banners']['bottom'] = $_POST['banner_bottom'] ?? '';
    
    saveSiteConfig($config);
    $configMessage = "Configuración actualizada correctamente.";
}

// Estadísticas Reales y Lista de Notas usando Redis (Multi-key temporal)
$globalStats = [];
$yearStats = [];
$monthStats = [];
$currentYear = date('Y');
$currentMonth = date('Y-m');

try {
    $redis = new Redis();
    if ($redis->connect('127.0.0.1', 6379)) {
        // Claves globales: visitas:global:{id}
        $gKeys = $redis->keys('visitas:global:*');
        if (!empty($gKeys)) {
            $gVals = $redis->mGet($gKeys);
            foreach ($gKeys as $i => $k) {
                $path = str_replace('visitas:global:', '', $k);
                $globalStats[$path] = (int)$gVals[$i];
            }
        }
        // Claves del año actual: visitas:{YYYY}:{id}
        $yKeys = $redis->keys("visitas:{$currentYear}:*");
        if (!empty($yKeys)) {
            $yVals = $redis->mGet($yKeys);
            foreach ($yKeys as $i => $k) {
                $path = str_replace("visitas:{$currentYear}:", '', $k);
                $yearStats[$path] = (int)$yVals[$i];
            }
        }
        // Claves del mes actual: visitas:{YYYY-MM}:{id}
        $mKeys = $redis->keys("visitas:{$currentMonth}:*");
        if (!empty($mKeys)) {
            $mVals = $redis->mGet($mKeys);
            foreach ($mKeys as $i => $k) {
                $path = str_replace("visitas:{$currentMonth}:", '', $k);
                $monthStats[$path] = (int)$mVals[$i];
            }
        }

        // Retrocompatibilidad: leer claves legacy visitas:{id} (sin prefijo temporal)
        $legacyKeys = $redis->keys('visitas:*');
        if (!empty($legacyKeys)) {
            $legacyVals = $redis->mGet($legacyKeys);
            foreach ($legacyKeys as $i => $k) {
                // Ignorar claves que ya tienen formato temporal
                if (preg_match('/^visitas:(global|' . $currentYear . '|\d{4}-\d{2}|\d{4}):/', $k)) continue;
                $path = preg_replace('/^visitas:/', '', $k);
                if (!isset($globalStats[$path])) {
                    $globalStats[$path] = (int)$legacyVals[$i];
                }
            }
        }
    }
} catch (Exception $e) {
    // Si Redis falla, arrays vacíos por defecto
}

$totalVisitasGlobal = array_sum($globalStats);
$totalVisitasYear   = array_sum($yearStats);
$totalVisitasMonth  = array_sum($monthStats);

$allPosts = [];
$dir = '../content/posts/';
if (is_dir($dir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $content = file_get_contents($file->getPathname());
            $parsed = parseFrontmatter($content);
            $relPath = str_replace([realpath($dir), '.md', '\\'], ['', '', '/'], $file->getRealPath());
            $relPath = trim($relPath, '/');
            $parsed['meta']['filename'] = $relPath;
            $parsed['meta']['views_global'] = $globalStats[$relPath] ?? 0;
            $parsed['meta']['views_year']   = $yearStats[$relPath] ?? 0;
            $parsed['meta']['views_month']  = $monthStats[$relPath] ?? 0;
            $parsed['meta']['author_id'] = $parsed['meta']['author_id'] ?? null;
            $allPosts[] = $parsed['meta'];
        }
    }
}
usort($allPosts, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$totalNotas = count($allPosts);

// Si se recibe ?edit=file
$editFile = $_GET['edit'] ?? '';
$editTitle = ''; $editCategory = 'local'; $editAuthor = 'Redacción NotiGolfo'; $editContent = ''; $editImage = ''; $editFeatured = false;
if ($editFile && file_exists('../content/posts/' . $editFile . '.md')) {
    $raw = file_get_contents('../content/posts/' . $editFile . '.md');
    $parsed = parseFrontmatter($raw);
    $editTitle = $parsed['meta']['title'] ?? '';
    $editCategory = $parsed['meta']['category'] ?? 'local';
    $editAuthor = $parsed['meta']['author'] ?? 'Redacción NotiGolfo';
    $editImage = $parsed['meta']['featured_image'] ?? '';
    $editFeatured = isset($parsed['meta']['featured']) ? filter_var($parsed['meta']['featured'], FILTER_VALIDATE_BOOLEAN) : false;
    $editContent = $parsed['content'] ?? '';
} else {
    // Default author rules
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $editAuthor = 'Redacción NotiGolfo';
    } else {
        $editAuthor = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
    }
}
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$session_user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            dark: '#2E2B22', menu: '#4A4538', olive: '#5C5748',
                            gold: '#B8860B', red: '#991b1b', accent: '#C0392B',
                            light: '#F5F3EF', beige: '#E8E4DB',
                        }
                    }
                }
            }
        }
        
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('bg-brand-dark', 'text-white');
                el.classList.add('text-brand-dark', 'hover:bg-brand-beige');
            });
            
            document.getElementById(tabId).classList.remove('hidden');
            document.getElementById('btn-' + tabId).classList.remove('text-brand-dark', 'hover:bg-brand-beige');
            document.getElementById('btn-' + tabId).classList.add('bg-brand-dark', 'text-white');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans text-gray-800 min-h-screen">

<!-- Admin Bar (Top) -->
<div class="bg-gray-900 text-gray-200 text-sm py-2 px-4 flex justify-between items-center z-50">
    <div class="flex space-x-4">
        <a href="/" class="hover:text-white transition"><i class="fas fa-home mr-1"></i> Ver Sitio</a>
    </div>
    <div class="flex items-center space-x-4">
        <span>Hola, <?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
        <?php if($is_admin): ?>
            <a href="users.php" class="hover:text-white transition bg-brand-gold px-2 py-1 rounded"><i class="fas fa-users"></i> Usuarios</a>
        <?php endif; ?>
        <a href="logout.php" class="hover:text-white transition bg-gray-700 px-2 py-1 rounded">Cerrar Sesión</a>
    </div>
</div>

<div class="max-w-6xl mx-auto p-4 md:p-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Panel de Administración</h1>
            <p class="text-gray-600 mt-1">Gestiona el contenido y la configuración de NotiGolfo.</p>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-300 pb-2">
        <button id="btn-tab-list" onclick="switchTab('tab-list')" class="tab-btn bg-brand-dark text-white px-4 py-2 rounded-t font-medium transition"><i class="fas fa-list mr-2"></i>Notas</button>
        <button id="btn-tab-editor" onclick="switchTab('tab-editor')" class="tab-btn text-brand-dark hover:bg-brand-beige px-4 py-2 rounded-t font-medium transition"><i class="fas fa-pen mr-2"></i><?= $editFile ? 'Editar Nota' : 'Redactar Nota' ?></button>
        <button id="btn-tab-config" onclick="switchTab('tab-config')" class="tab-btn text-brand-dark hover:bg-brand-beige px-4 py-2 rounded-t font-medium transition"><i class="fas fa-cog mr-2"></i>Configuración</button>
        <button id="btn-tab-stats" onclick="switchTab('tab-stats')" class="tab-btn text-brand-dark hover:bg-brand-beige px-4 py-2 rounded-t font-medium transition"><i class="fas fa-chart-bar mr-2"></i>Estadísticas</button>
    </div>

    <!-- TAB LIST: Lista y Filtros -->
    <div id="tab-list" class="tab-content bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <input type="text" id="searchTitle" placeholder="Buscar por título..." class="px-4 py-2 border rounded focus:ring-brand-gold w-full md:w-1/3" onkeyup="filterNotes()">
            <select id="filterCat" class="px-4 py-2 border rounded focus:ring-brand-gold w-full md:w-1/4" onchange="filterNotes()">
                <option value="">Todas las Categorías</option>
                <option value="local">Local</option>
                <option value="estatal">Estatal</option>
                <option value="nacional">Nacional</option>
                <option value="policiaca">Policíaca</option>
                <option value="internacional">Internacional</option>
                <option value="deportes">Deportes</option>
                <option value="sociedad">Sociedad</option>
            </select>
            <input type="month" id="filterMonth" class="px-4 py-2 border rounded focus:ring-brand-gold w-full md:w-1/4" onchange="filterNotes()">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Fecha</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Categoría</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Título</th>
                        <th class="py-2 px-4 border-b text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody id="notesTableBody">
                    <?php foreach($allPosts as $p): 
                        // Filtro de Privacidad: Editor solo ve sus notas
                        if (!$is_admin && $p['author_id'] != $session_user_id) continue;
                    ?>
                    <tr class="hover:bg-gray-50 note-row" data-title="<?= strtolower(htmlspecialchars($p['title'])) ?>" data-cat="<?= strtolower(htmlspecialchars($p['category'])) ?>" data-date="<?= date('Y-m', strtotime($p['date'])) ?>">
                        <td class="py-2 px-4 border-b text-sm text-gray-600"><?= date('Y-m-d', strtotime($p['date'])) ?></td>
                        <td class="py-2 px-4 border-b text-sm font-bold uppercase text-brand-accent"><?= htmlspecialchars($p['category']) ?></td>
                        <td class="py-2 px-4 border-b text-sm text-gray-800"><?= htmlspecialchars($p['title']) ?></td>
                        <td class="py-2 px-4 border-b text-center">
                            <?php if($is_admin || $p['author_id'] == $session_user_id): ?>
                                <a href="editor.php?edit=<?= urlencode($p['filename']) ?>" class="text-blue-600 hover:underline mx-1 text-sm" title="Editar"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <a href="/article.php?id=<?= urlencode($p['filename']) ?>" target="_blank" class="text-green-600 hover:underline mx-1 text-sm" title="Ver"><i class="fas fa-eye"></i></a>
                            <?php if($is_admin || $p['author_id'] == $session_user_id): ?>
                                <a href="process_delete.php?file=<?= urlencode($p['filename']) ?>" onclick="return confirm('¿Seguro que deseas eliminar esta nota permanentemente?');" class="text-red-600 hover:underline mx-1 text-sm" title="Eliminar"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination: Notes -->
        <div id="notesPagination" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200"></div>
    </div>
    
    <script>
    function insertAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const val = textarea.value;
        textarea.value = val.substring(0, start) + text + val.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.focus();
    }

    function addMarkdown(type) {
        const area = document.getElementById('content');
        switch(type) {
            case 'bold': insertAtCursor(area, '**Texto en negrita**'); break;
            case 'italic': insertAtCursor(area, '_Texto en cursiva_'); break;
            case 'h2': insertAtCursor(area, '\n## Subtítulo\n'); break;
            case 'list': insertAtCursor(area, '\n- Elemento de lista\n'); break;
            case 'link': 
                const url = prompt('Ingrese URL:');
                if(url) insertAtCursor(area, '[Texto del enlace](' + url + ')');
                break;
            case 'video':
                const vurl = prompt('Ingrese URL de Video (YouTube, Facebook, X):');
                if(vurl) insertAtCursor(area, '\n' + vurl + '\n');
                break;
        }
    }

    function uploadImage() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = e => {
            const file = e.target.files[0];
            const formData = new FormData();
            formData.append('file', file);
            
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
            btn.disabled = true;

            fetch('upload_inline.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                if(data.success) {
                    insertAtCursor(document.getElementById('content'), '\n![' + file.name + '](' + data.url + ')\n');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                alert('Error de conexión');
            });
        };
        input.click();
    }

    // filterNotes and filterStats are defined in the pagination script at the bottom
    function filterNotes() {}
    function filterStats() {}

    // Auto-save logic
    const isEditMode = <?= $editFile ? 'true' : 'false' ?>;
    document.addEventListener('DOMContentLoaded', () => {
        const titleInput = document.getElementById('title');
        const catInput = document.getElementById('category');
        const contentInput = document.getElementById('content');
        const statusText = document.getElementById('autosave-status');

        if (!isEditMode && titleInput && catInput && contentInput) {
            // Restore draft
            const savedDraft = localStorage.getItem('notigolfo_draft');
            if (savedDraft) {
                try {
                    const draft = JSON.parse(savedDraft);
                    if (draft.title || draft.content) {
                        titleInput.value = draft.title || '';
                        catInput.value = draft.category || 'local';
                        contentInput.value = draft.content || '';
                        
                        statusText.innerText = "Borrador recuperado.";
                        statusText.classList.remove('hidden');
                        setTimeout(() => statusText.classList.add('hidden'), 4000);
                    }
                } catch (e) {}
            }
            
            // Auto-save loop
            setInterval(() => {
                if (titleInput.value || contentInput.value) {
                    statusText.innerText = "Guardando...";
                    statusText.classList.remove('hidden');
                    
                    localStorage.setItem('notigolfo_draft', JSON.stringify({
                        title: titleInput.value,
                        category: catInput.value,
                        content: contentInput.value
                    }));
                    
                    setTimeout(() => {
                        statusText.innerText = "Borrador guardado";
                        setTimeout(() => statusText.classList.add('hidden'), 2000);
                    }, 1000);
                }
            }, 15000);
        }

        // Switch to editor if edit mode
        <?php if($editFile): ?>
            switchTab('tab-editor');
        <?php endif; ?>
    });
    </script>

    <!-- TAB 1: Editor -->
    <div id="tab-editor" class="tab-content hidden bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <form action="process_post.php" method="POST" enctype="multipart/form-data" onsubmit="if(!isEditMode) localStorage.removeItem('notigolfo_draft');">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <?php if($editFile): ?>
                <input type="hidden" name="edit_file" value="<?= htmlspecialchars($editFile) ?>">
                <input type="hidden" name="current_image" value="<?= htmlspecialchars($editImage) ?>">
                <div class="bg-blue-50 text-blue-800 p-3 rounded mb-6 text-sm flex justify-between items-center">
                    <span>Editando archivo: <strong><?= htmlspecialchars($editFile) ?>.md</strong></span>
                    <a href="editor.php" class="text-blue-600 hover:underline">Crear nota nueva</a>
                </div>
            <?php endif; ?>
            <div class="mb-6">
                <label for="title" class="block text-sm font-bold text-gray-700 mb-2">Título de la Noticia</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($editTitle) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-lg">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="category" class="block text-sm font-bold text-gray-700 mb-2">Categoría</label>
                    <select id="category" name="category" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-gold bg-white">
                        <?php 
                        $cats = ['local'=>'Local','estatal'=>'Estatal','nacional'=>'Nacional','policiaca'=>'Policiaca','internacional'=>'Internacional','deportes'=>'Deportes','sociedad'=>'Sociedad'];
                        foreach($cats as $k=>$v): 
                        ?>
                        <option value="<?= $k ?>" <?= $editCategory===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="author" class="block text-sm font-bold text-gray-700 mb-2">Autor</label>
                    <input type="text" id="author" name="author" value="<?= htmlspecialchars($editAuthor) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-gold" <?= !$is_admin ? 'readonly' : '' ?>>
                </div>
            </div>
            
            <div class="mb-6 flex items-center bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= $editFeatured ? 'checked' : '' ?> class="w-5 h-5 text-brand-gold bg-white border-gray-300 rounded focus:ring-brand-gold focus:ring-2">
                <label for="is_featured" class="ml-3 text-sm font-bold text-gray-800">DESTACAR NOTA (Aparecerá en el Carrusel Principal)</label>
            </div>

            <div class="mb-6">
                <label for="custom_date" class="block text-sm font-bold text-gray-700 mb-2">Fecha/Hora de Publicación</label>
                <input type="datetime-local" id="custom_date" name="custom_date" value="<?= date('Y-m-d\TH:i') ?>" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-gold">
            </div>

            <div class="mb-6">
                <label for="image" class="block text-sm font-bold text-gray-700 mb-2">Imagen Destacada</label>
                <?php if($editImage): ?>
                    <p class="text-xs text-gray-500 mb-2">Imagen actual: <?= htmlspecialchars($editImage) ?>. Deja en blanco para conservarla.</p>
                <?php endif; ?>
                <div class="space-y-3">
                    <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif, image/webp" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-bold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 transition">
                    <input type="url" name="image_url" placeholder="O ingresa la URL directa de la imagen" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-gold text-sm">
                </div>
            </div>

            <div class="mb-8">
                <label for="content" class="block text-sm font-bold text-gray-700 mb-2">Contenido (Markdown)</label>
                
                <!-- Toolbar -->
                <div class="flex flex-wrap items-center bg-gray-50 border border-b-0 border-gray-300 rounded-t-lg p-2 gap-1">
                    <button type="button" onclick="addMarkdown('bold')" class="px-3 py-1 hover:bg-gray-200 rounded text-gray-700 transition text-sm" title="Negrita"><i class="fas fa-bold"></i></button>
                    <button type="button" onclick="addMarkdown('italic')" class="px-3 py-1 hover:bg-gray-200 rounded text-gray-700 transition text-sm" title="Cursiva"><i class="fas fa-italic"></i></button>
                    <button type="button" onclick="addMarkdown('h2')" class="px-3 py-1 hover:bg-gray-200 rounded text-gray-700 transition text-sm" title="Título H2"><i class="fas fa-heading"></i></button>
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>
                    <button type="button" onclick="addMarkdown('list')" class="px-3 py-1 hover:bg-gray-200 rounded text-gray-700 transition text-sm" title="Lista"><i class="fas fa-list"></i></button>
                    <button type="button" onclick="addMarkdown('link')" class="px-3 py-1 hover:bg-gray-200 rounded text-gray-700 transition text-sm" title="Enlace"><i class="fas fa-link"></i></button>
                    <div class="w-px h-6 bg-gray-300 mx-1"></div>
                    <button type="button" onclick="uploadImage()" class="px-3 py-1 hover:bg-gray-200 rounded text-brand-accent transition text-sm" title="Subir Imagen"><i class="fas fa-image mr-1"></i> Imagen</button>
                    <button type="button" onclick="addMarkdown('video')" class="px-3 py-1 hover:bg-gray-200 rounded text-blue-600 transition text-sm" title="Insertar Video"><i class="fas fa-video mr-1"></i> Video</button>
                </div>

                <textarea id="content" name="content" rows="15" required class="w-full px-4 py-3 border border-gray-300 rounded-b-lg focus:outline-none focus:ring-2 focus:ring-brand-gold font-mono text-sm border-t-0"><?= htmlspecialchars($editContent) ?></textarea>
                <p class="text-xs text-gray-500 mt-2">Puedes usar sintaxis Markdown o usar la barra de herramientas superior.</p>
            </div>

            <div class="flex justify-end items-center space-x-4">
                <span id="autosave-status" class="text-sm font-bold text-gray-500 hidden"></span>
                <a href="editor.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">Cancelar</a>
                <button type="submit" class="px-6 py-2 bg-brand-accent text-white font-bold rounded hover:bg-brand-red transition shadow">
                    <?= $editFile ? 'Actualizar Noticia' : 'Publicar Noticia' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 2: Configuración -->
    <div id="tab-config" class="tab-content hidden bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <?php if(!empty($configMessage)): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-6 text-sm">
                <?= $configMessage ?>
            </div>
        <?php endif; ?>
        <form action="editor.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="update_config">
            
            <h2 class="text-xl font-bold border-b pb-2 mb-4">Redes Sociales</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fab fa-facebook text-blue-600"></i> Facebook URL</label>
                    <input type="text" name="social_facebook" value="<?= htmlspecialchars($config['social']['facebook']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fa-brands fa-x-twitter"></i> X (Twitter) URL</label>
                    <input type="text" name="social_twitter" value="<?= htmlspecialchars($config['social']['twitter']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fab fa-instagram text-pink-600"></i> Instagram URL</label>
                    <input type="text" name="social_instagram" value="<?= htmlspecialchars($config['social']['instagram']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                </div>
            </div>

            <h2 class="text-xl font-bold border-b pb-2 mb-4">Banners Publicitarios (URLs de Imagen)</h2>
            <div class="space-y-4 mb-8">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Banner Superior (Header - 728x90)</label>
                    <input type="text" name="banner_top" value="<?= htmlspecialchars($config['banners']['top']) ?>" placeholder="https://ejemplo.com/banner.jpg" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Sidebar 1 (300x250)</label>
                        <input type="text" name="banner_sidebar1" value="<?= htmlspecialchars($config['banners']['sidebar1']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Sidebar 2 (300x250/600)</label>
                        <input type="text" name="banner_sidebar2" value="<?= htmlspecialchars($config['banners']['sidebar2']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Banner Inferior (Footer Leaderboard)</label>
                    <input type="text" name="banner_bottom" value="<?= htmlspecialchars($config['banners']['bottom']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-brand-gold">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-brand-dark text-white font-bold rounded hover:bg-gray-800 transition shadow">
                    Guardar Configuración
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 3: Estadísticas -->
    <div id="tab-stats" class="tab-content hidden bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold border-b pb-2 mb-6">Estadísticas de Tráfico</h2>

        <!-- Cards de Tráfico Segmentado -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Tráfico del Mes -->
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-50 to-emerald-100 p-5 rounded-xl border border-emerald-200 text-center">
                <div class="absolute top-3 right-3 text-emerald-300"><i class="fas fa-calendar-day text-2xl"></i></div>
                <p class="text-emerald-700 text-xs font-bold uppercase tracking-wider mb-1">Tráfico del Mes</p>
                <p class="text-sm text-emerald-600 mb-2"><?= $currentMonth ?></p>
                <p class="text-3xl font-extrabold text-emerald-900"><?= number_format($totalVisitasMonth) ?></p>
            </div>
            <!-- Tráfico del Año -->
            <div class="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100 p-5 rounded-xl border border-blue-200 text-center">
                <div class="absolute top-3 right-3 text-blue-300"><i class="fas fa-calendar-alt text-2xl"></i></div>
                <p class="text-blue-700 text-xs font-bold uppercase tracking-wider mb-1">Tráfico del Año</p>
                <p class="text-sm text-blue-600 mb-2"><?= $currentYear ?></p>
                <p class="text-3xl font-extrabold text-blue-900"><?= number_format($totalVisitasYear) ?></p>
            </div>
            <!-- Tráfico Histórico -->
            <div class="relative overflow-hidden bg-gradient-to-br from-amber-50 to-amber-100 p-5 rounded-xl border border-amber-200 text-center">
                <div class="absolute top-3 right-3 text-amber-300"><i class="fas fa-chart-line text-2xl"></i></div>
                <p class="text-amber-700 text-xs font-bold uppercase tracking-wider mb-1">Tráfico Histórico</p>
                <p class="text-sm text-amber-600 mb-2">Todo el tiempo</p>
                <p class="text-3xl font-extrabold text-amber-900"><?= number_format($totalVisitasGlobal) ?></p>
            </div>
            <!-- Notas Publicadas -->
            <div class="relative overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-xl border border-gray-200 text-center">
                <div class="absolute top-3 right-3 text-gray-300"><i class="fas fa-newspaper text-2xl"></i></div>
                <p class="text-gray-600 text-xs font-bold uppercase tracking-wider mb-1">Notas Publicadas</p>
                <p class="text-sm text-gray-500 mb-2">Total</p>
                <p class="text-3xl font-extrabold text-gray-800"><?= $totalNotas ?></p>
            </div>
        </div>
        
        <h3 class="font-bold text-lg mb-4">Notas más leídas</h3>
        
        <!-- Filtros para Estadísticas -->
        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <input type="text" id="searchStatsTitle" placeholder="Buscar por título..." class="px-4 py-2 border rounded focus:ring-brand-gold w-full md:w-1/3" onkeyup="filterStats()">
            <select id="filterStatsCat" class="px-4 py-2 border rounded focus:ring-brand-gold w-full md:w-1/4" onchange="filterStats()">
                <option value="">Todas las Categorías</option>
                <option value="local">Local</option>
                <option value="estatal">Estatal</option>
                <option value="nacional">Nacional</option>
                <option value="policiaca">Policíaca</option>
                <option value="internacional">Internacional</option>
                <option value="deportes">Deportes</option>
                <option value="sociedad">Sociedad</option>
            </select>
            <input type="month" id="filterStatsMonth" class="px-4 py-2 border rounded focus:ring-brand-gold w-full md:w-1/4" onchange="filterStats()">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">Categoría</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">Título</th>
                        <th class="py-2 px-4 border-b text-right text-xs font-semibold text-gray-600 uppercase"><i class="fas fa-calendar-day mr-1 text-emerald-500"></i>Mes</th>
                        <th class="py-2 px-4 border-b text-right text-xs font-semibold text-gray-600 uppercase"><i class="fas fa-calendar-alt mr-1 text-blue-500"></i>Año</th>
                        <th class="py-2 px-4 border-b text-right text-xs font-semibold text-gray-600 uppercase"><i class="fas fa-chart-line mr-1 text-amber-500"></i>Global</th>
                    </tr>
                </thead>
                <tbody id="statsTableBody">
                    <?php 
                    $sortedPosts = $allPosts;
                    usort($sortedPosts, function($a, $b) { return $b['views_global'] - $a['views_global']; });
                    foreach($sortedPosts as $p): 
                    ?>
                    <tr class="hover:bg-gray-50 stat-row" data-title="<?= strtolower(htmlspecialchars($p['title'])) ?>" data-cat="<?= strtolower(htmlspecialchars($p['category'])) ?>" data-date="<?= date('Y-m', strtotime($p['date'])) ?>">
                        <td class="py-2 px-4 border-b text-sm text-gray-500 whitespace-nowrap"><?= date('Y-m-d', strtotime($p['date'])) ?></td>
                        <td class="py-2 px-4 border-b text-sm font-bold uppercase text-brand-accent"><?= htmlspecialchars($p['category']) ?></td>
                        <td class="py-2 px-4 border-b text-sm"><a href="/article.php?id=<?= urlencode($p['filename']) ?>" target="_blank" class="hover:underline"><?= htmlspecialchars($p['title']) ?></a></td>
                        <td class="py-2 px-4 border-b text-sm font-bold text-right text-emerald-700"><?= number_format($p['views_month']) ?></td>
                        <td class="py-2 px-4 border-b text-sm font-bold text-right text-blue-700"><?= number_format($p['views_year']) ?></td>
                        <td class="py-2 px-4 border-b text-sm font-bold text-right text-amber-700"><?= number_format($p['views_global']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination: Stats -->
        <div id="statsPagination" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200"></div>
    </div>

</div>

<?php if(isset($_POST['action']) && $_POST['action'] === 'update_config'): ?>
<script>
    switchTab('tab-config');
</script>
<?php endif; ?>

<script>
/**
 * Reusable Pagination Engine — 25 rows/page
 * Supports filtered views (hidden rows are skipped)
 */
function initPagination(tableBodyId, paginationContainerId, rowClass, perPage) {
    const PER_PAGE = perPage || 25;
    let currentPage = 1;

    function getVisibleRows() {
        return Array.from(document.querySelectorAll('.' + rowClass)).filter(r => {
            // If the row is inside the correct tbody
            return r.closest('tbody')?.id === tableBodyId || (!r.closest('tbody')?.id && r.classList.contains(rowClass));
        });
    }

    function getFilteredRows() {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return [];
        return Array.from(tbody.querySelectorAll('.' + rowClass)).filter(r => r.dataset.filtered !== 'out');
    }

    function render() {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;
        const allRows = Array.from(tbody.querySelectorAll('.' + rowClass));
        const filtered = allRows.filter(r => r.dataset.filtered !== 'out');
        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

        if (currentPage > totalPages) currentPage = totalPages;

        // Hide all, then show only current page slice
        allRows.forEach(r => r.style.display = 'none');
        const start = (currentPage - 1) * PER_PAGE;
        const end = start + PER_PAGE;
        filtered.slice(start, end).forEach(r => r.style.display = '');

        // Build controls
        const container = document.getElementById(paginationContainerId);
        if (!container) return;

        const info = `<span class="text-sm text-gray-500">Mostrando ${Math.min(start+1, total)}–${Math.min(end, total)} de ${total}</span>`;

        let buttons = '';
        // Prev
        buttons += `<button onclick="window._pg_${tableBodyId}.goto(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''} class="px-3 py-1.5 text-sm rounded border transition ${currentPage <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'}"><i class="fas fa-chevron-left text-xs"></i> Anterior</button>`;

        // Page numbers (show max 7 centered around current)
        const maxVisible = 7;
        let startP = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endP = Math.min(totalPages, startP + maxVisible - 1);
        if (endP - startP < maxVisible - 1) startP = Math.max(1, endP - maxVisible + 1);

        if (startP > 1) {
            buttons += `<button onclick="window._pg_${tableBodyId}.goto(1)" class="px-3 py-1.5 text-sm rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition">1</button>`;
            if (startP > 2) buttons += `<span class="px-1 text-gray-400">…</span>`;
        }
        for (let i = startP; i <= endP; i++) {
            const active = i === currentPage;
            buttons += `<button onclick="window._pg_${tableBodyId}.goto(${i})" class="px-3 py-1.5 text-sm rounded border transition ${active ? 'bg-brand-dark text-white border-brand-dark font-bold' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'}">${i}</button>`;
        }
        if (endP < totalPages) {
            if (endP < totalPages - 1) buttons += `<span class="px-1 text-gray-400">…</span>`;
            buttons += `<button onclick="window._pg_${tableBodyId}.goto(${totalPages})" class="px-3 py-1.5 text-sm rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition">${totalPages}</button>`;
        }

        // Next
        buttons += `<button onclick="window._pg_${tableBodyId}.goto(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''} class="px-3 py-1.5 text-sm rounded border transition ${currentPage >= totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'}">Siguiente <i class="fas fa-chevron-right text-xs"></i></button>`;

        container.innerHTML = info + `<div class="flex items-center gap-1 flex-wrap">${buttons}</div>`;
    }

    // Expose goto
    window['_pg_' + tableBodyId] = {
        goto(page) {
            currentPage = page;
            render();
        },
        refresh() {
            currentPage = 1;
            render();
        },
        render: render
    };

    render();
    return window['_pg_' + tableBodyId];
}

// Patch filter functions to work with pagination
(function() {
    const origFilterNotes = window.filterNotes;
    window.filterNotes = function() {
        let q = document.getElementById('searchTitle').value.toLowerCase();
        let c = document.getElementById('filterCat').value.toLowerCase();
        let m = document.getElementById('filterMonth').value;
        let rows = document.querySelectorAll('#notesTableBody .note-row');
        rows.forEach(r => {
            let matchesTitle = r.dataset.title.includes(q);
            let matchesCat = c === '' || r.dataset.cat === c;
            let matchesMonth = m === '' || r.dataset.date === m;
            r.dataset.filtered = (matchesTitle && matchesCat && matchesMonth) ? '' : 'out';
        });
        if (window._pg_notesTableBody) window._pg_notesTableBody.refresh();
    };

    const origFilterStats = window.filterStats;
    window.filterStats = function() {
        let q = document.getElementById('searchStatsTitle').value.toLowerCase();
        let c = document.getElementById('filterStatsCat').value.toLowerCase();
        let m = document.getElementById('filterStatsMonth').value;
        let rows = document.querySelectorAll('#statsTableBody .stat-row');
        rows.forEach(r => {
            let matchesTitle = r.dataset.title.includes(q);
            let matchesCat = c === '' || r.dataset.cat === c;
            let matchesMonth = m === '' || r.dataset.date === m;
            r.dataset.filtered = (matchesTitle && matchesCat && matchesMonth) ? '' : 'out';
        });
        if (window._pg_statsTableBody) window._pg_statsTableBody.refresh();
    };
})();

// Initialize both paginators after DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initPagination('notesTableBody', 'notesPagination', 'note-row', 25);
    initPagination('statsTableBody', 'statsPagination', 'stat-row', 25);
});
</script>

</body>
</html>
