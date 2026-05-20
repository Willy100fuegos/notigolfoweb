<?php
date_default_timezone_set('America/Mexico_City');
require_once 'includes/markdown_parser.php';

$articleId = $_GET['id'] ?? '';
// Soporte para subcarpetas (YYYY/MM) previniendo directory traversal
$articleId = str_replace(['../', '..\\'], '', $articleId);
$filePath = 'content/posts/' . ltrim($articleId, '/') . '.md';

if (empty($articleId) || !file_exists($filePath)) {
    header("Location: /");
    exit;
}

// Estadísticas con Redis (Multi-key temporal analytics via pipeline)
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $year  = date('Y');           // ej. 2026
    $month = date('Y-m');         // ej. 2026-05

    $redis->pipeline();
    $redis->incr("visitas:global:{$articleId}");    // Histórico total
    $redis->incr("visitas:{$year}:{$articleId}");   // Acumulado anual
    $redis->incr("visitas:{$month}:{$articleId}");  // Acumulado mensual
    $redis->exec();
} catch (Exception $e) {
    // Si Redis falla, no bloqueamos la carga de la nota
}

$content = file_get_contents($filePath);
$parsed = parseFrontmatter($content);
$meta = $parsed['meta'];
$htmlContent = parseMarkdown($parsed['content']);

$cleanText = trim(preg_replace('/\s+/', ' ', strip_tags($htmlContent)));
$pageDescription = mb_substr($cleanText, 0, 150) . (mb_strlen($cleanText) > 150 ? '...' : '');

$pageTitle = htmlspecialchars($meta['title']) . ' - NotiGolfo Veracruz';
$ogTitle = $meta['title'];
if (empty($meta['featured_image'])) {
    $ogImage = 'http://imgfz.com/i/sULPnX9.jpeg';
} else {
    $ogImage = strpos(trim($meta['featured_image']), 'http') === 0 
        ? trim($meta['featured_image']) 
        : 'https://notigolfoveracruz.com/content/images/' . trim($meta['featured_image']);
}
$currentCategory = $meta['category'];
$isArticlePage = true;

require 'includes/header.php';

$currentUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>

<div class="max-w-7xl mx-auto px-4 py-6 md:py-8 w-full grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- Article Main -->
    <div class="lg:col-span-8">
        <article class="bg-white p-6 rounded-lg border border-brand-beige shadow-sm">
            <div class="mb-6 flex items-center justify-between">
                <a href="/category.php?id=<?= $meta['category'] ?>" class="text-xs font-bold uppercase tracking-wider bg-brand-menu text-white px-3 py-1 rounded hover:bg-brand-dark transition">
                    <?= htmlspecialchars($categories[$meta['category']] ?? $meta['category']) ?>
                </a>
                
                <!-- Compartir Flotante -->
                <div class="flex space-x-2">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 transition"><i class="fab fa-facebook-f text-sm"></i></a>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($currentUrl) ?>&text=<?= urlencode($meta['title']) ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-black text-white rounded-full hover:bg-gray-800 transition"><svg viewBox="0 0 24 24" class="h-4 w-4 fill-current"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode($meta['title'] . ' ' . $currentUrl) ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-green-500 text-white rounded-full hover:bg-green-600 transition"><i class="fab fa-whatsapp text-sm"></i></a>
                </div>
            </div>
            
            <h1 class="text-3xl md:text-5xl font-serif font-bold text-brand-dark leading-tight mb-6"><?= htmlspecialchars($meta['title']) ?></h1>
            
            <div class="flex items-center text-sm text-brand-olive border-y border-brand-beige py-3 mb-8">
                <span class="font-bold text-brand-dark mr-4">Por: <?= htmlspecialchars($meta['author']) ?></span>
                <span>Publicado: <?= date('d M, Y - H:i', strtotime($meta['date'])) ?></span>
            </div>
            
            <?php if(!empty($meta['featured_image'])): ?>
            <div class="mb-10 w-full -mx-6 px-6 md:mx-0 md:px-0">
                <?php $artImgSrc = strpos($meta['featured_image'], 'http') === 0 ? $meta['featured_image'] : '/content/images/' . $meta['featured_image']; ?>
                <img src="<?= htmlspecialchars($artImgSrc) ?>" alt="<?= htmlspecialchars($meta['title']) ?>" class="w-full object-cover rounded shadow-md">
            </div>
        <?php endif; ?>
        
        <div class="prose prose-lg max-w-none text-gray-800 leading-relaxed font-serif">
            <?= $htmlContent ?>
        </div>
    </article>
    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-4">
        <?php require 'includes/sidebar.php'; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
