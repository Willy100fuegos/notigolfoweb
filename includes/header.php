<?php
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    // Redirección HTTPS omitida localmente o forzada en prod
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
}
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: http://imgfz.com https://imgfz.com http: https:; object-src 'none';");

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config_manager.php';

$config = getSiteConfig();
$isLoggedIn = isLoggedIn();

$categories = [
    'local' => 'Local', 'estatal' => 'Estatal', 'nacional' => 'Nacional', 
    'policiaca' => 'Policíaca', 'internacional' => 'Internacional', 
    'deportes' => 'Deportes', 'sociedad' => 'Sociedad'
];

$currentCategory = $currentCategory ?? '';
$pageTitle = $pageTitle ?? 'NotiGolfo Veracruz';
$ogTitle = $ogTitle ?? $pageTitle;
$ogImage = $ogImage ?? 'http://imgfz.com/i/sULPnX9.jpeg';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Las mejores noticias de Veracruz y el mundo.') ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? 'Las mejores noticias de Veracruz y el mundo.') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:url" content="<?= htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:type" content="article">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription ?? 'Las mejores noticias de Veracruz y el mundo.') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cabin:wght@400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            dark: '#1a3a72', menu: '#1a4a8a', olive: '#5C5748',
                            gold: '#3a9a2a', red: '#991b1b', accent: '#1e6fb8',
                            light: '#F5F3EF', beige: '#E8E4DB', navy: '#1a3a72',
                        }
                    },
                    fontFamily: {
                        sans: ['"Segoe UI"', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'],
                        serif: ['"Cabin"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-light font-sans text-brand-dark min-h-screen flex flex-col overflow-x-hidden w-full">

<?php if ($isLoggedIn): ?>
<!-- Admin Bar -->
<div class="bg-gray-900 text-gray-200 text-sm py-2 px-4 flex justify-between items-center z-50 relative">
    <div class="flex space-x-4">
        <a href="/admin/editor.php" class="hover:text-white transition"><i class="fas fa-tachometer-alt mr-1"></i> Panel de Administración</a>
        <?php if (isset($isArticlePage) && $isArticlePage): ?>
            <span class="text-gray-500">|</span>
            <a href="/admin/editor.php?edit=<?= urlencode($articleId) ?>" class="hover:text-white transition"><i class="fas fa-edit mr-1"></i> Editar Nota</a>
        <?php endif; ?>
    </div>
    <div>
        <a href="/admin/logout.php" class="hover:text-white transition">Cerrar Sesión</a>
    </div>
</div>
<?php endif; ?>

<!-- Header Principal -->
<header class="bg-white border-b border-brand-beige">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col md:flex-row items-center gap-4">
        <!-- Logo -->
        <a href="/" class="flex-shrink-0">
            <img src="http://imgfz.com/i/eFQ0WDT.png" alt="NotiGolfo" style="width: 450px; height: auto;" class="object-contain">
        </a>
        
        <!-- Banner Superior (728x90) -->
        <div class="flex-grow flex justify-end">
            <div class="w-full max-w-[728px] h-[90px] bg-gray-100 flex items-center justify-center border border-gray-200 overflow-hidden">
                <?php if (!empty($config['banners']['top'])): ?>
                    <img src="<?= htmlspecialchars($config['banners']['top']) ?>" alt="Ad" class="w-full h-full object-contain">
                <?php else: ?>
                    <span class="text-gray-400 text-sm">Banner Superior (728x90)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- Menú de Navegación -->
<nav class="bg-brand-dark text-white sticky top-0 z-40 shadow-lg" style="background: linear-gradient(90deg, #1a3a72 0%, #1e4f8c 100%);">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
        <!-- Mobile Hamburger Icon -->
        <div class="md:hidden flex items-center">
            <button id="mobileMenuBtn" class="text-white hover:text-brand-gold focus:outline-none p-2 -ml-2" aria-label="Menú">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>

        <!-- Links Categorías (Desktop) -->
        <div class="hidden md:flex flex-grow">
            <ul class="flex text-sm font-medium w-full">
                <li><a href="/" class="block px-4 py-3 hover:bg-brand-menu transition <?= $currentCategory === '' ? 'bg-brand-menu text-brand-gold' : '' ?>">Inicio</a></li>
                <?php foreach($categories as $slug => $name): ?>
                    <li><a href="/category.php?id=<?= $slug ?>" class="block px-4 py-3 hover:bg-brand-menu transition <?= $currentCategory === $slug ? 'bg-brand-menu text-brand-gold' : '' ?>"><?= $name ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Redes Sociales -->
        <div class="flex space-x-3 pl-4 border-l border-brand-menu py-3 ml-auto md:ml-0">
            <?php if (!empty($config['social']['facebook']) && $config['social']['facebook'] !== '#'): ?>
                <a href="<?= htmlspecialchars($config['social']['facebook']) ?>" target="_blank" class="hover:text-brand-gold transition"><i class="fab fa-facebook-f"></i></a>
            <?php endif; ?>
            <?php if (!empty($config['social']['twitter']) && $config['social']['twitter'] !== '#'): ?>
                <a href="<?= htmlspecialchars($config['social']['twitter']) ?>" target="_blank" class="hover:text-brand-gold transition"><i class="fab fa-twitter"></i></a>
            <?php endif; ?>
            <?php if (!empty($config['social']['instagram']) && $config['social']['instagram'] !== '#'): ?>
                <a href="<?= htmlspecialchars($config['social']['instagram']) ?>" target="_blank" class="hover:text-brand-gold transition"><i class="fab fa-instagram"></i></a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mobile Dropdown Menu -->
    <div id="mobileDropdown" class="hidden md:hidden bg-brand-dark w-full border-t border-brand-menu pb-2">
        <ul class="flex flex-col text-sm font-medium">
            <li><a href="/" class="block px-4 py-3 hover:bg-brand-menu transition border-b border-brand-menu/50 <?= $currentCategory === '' ? 'bg-brand-menu text-brand-gold' : '' ?>">Inicio</a></li>
            <?php foreach($categories as $slug => $name): ?>
                <li><a href="/category.php?id=<?= $slug ?>" class="block px-4 py-3 hover:bg-brand-menu transition border-b border-brand-menu/50 <?= $currentCategory === $slug ? 'bg-brand-menu text-brand-gold' : '' ?>"><?= $name ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileDropdown = document.getElementById('mobileDropdown');
    
    if (mobileMenuBtn && mobileDropdown) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileDropdown.classList.toggle('hidden');
        });
    }
});
</script>

<!-- Main Container -->
<main class="flex-grow w-full bg-brand-light pb-8">
