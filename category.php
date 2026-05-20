<?php
require_once 'includes/markdown_parser.php';
require_once 'includes/indexer.php';

$categoryId = $_GET['id'] ?? '';
$categoriesList = [
    'local' => 'Local', 'estatal' => 'Estatal', 'nacional' => 'Nacional', 
    'policiaca' => 'Policíaca', 'internacional' => 'Internacional', 
    'deportes' => 'Deportes', 'sociedad' => 'Sociedad'
];

if (!array_key_exists($categoryId, $categoriesList)) {
    header("Location: /");
    exit;
}

$categoryName = $categoriesList[$categoryId];

// Leer posts
$allPosts = getPostsIndex();
$posts = array_filter($allPosts, function($p) use ($categoryId) {
    return isset($p['category']) && $p['category'] === $categoryId;
});
$posts = array_values($posts);

// Paginación
$postsPerPage = 12;
$totalPosts = count($posts);
$totalPages = max(1, ceil($totalPosts / $postsPerPage));
$currentPage = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset = ($currentPage - 1) * $postsPerPage;

$pagedPosts = array_slice($posts, $offset, $postsPerPage);

$pageTitle = $categoryName . ' - NotiGolfo Veracruz';
$currentCategory = $categoryId;
require 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6 md:py-8 w-full grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- Feed Principal -->
    <div class="lg:col-span-8">
        <h2 class="text-3xl font-bold font-serif border-b-4 border-brand-accent pb-2 mb-8 uppercase tracking-wider text-brand-dark">Noticias de <?= $categoryName ?></h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if(empty($pagedPosts)): ?>
                <p class="text-brand-olive col-span-2">No hay noticias en esta categoría.</p>
            <?php else: ?>
                <?php foreach($pagedPosts as $post): ?>
                    <article class="bg-white shadow-sm border border-brand-beige hover:shadow-md transition duration-200 flex flex-col">
                        <a href="/article.php?id=<?= $post['filename'] ?>" class="block overflow-hidden h-48">
                            <?php $catImgSrc = strpos($post['featured_image'], 'http') === 0 ? $post['featured_image'] : '/content/images/' . $post['featured_image']; ?>
                            <img src="<?= htmlspecialchars($catImgSrc) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover hover:scale-105 transition duration-500">
                        </a>
                        <div class="p-4 flex flex-col flex-grow">
                            <h3 class="text-lg font-serif font-bold text-brand-dark mb-2 leading-tight">
                                <a href="/article.php?id=<?= $post['filename'] ?>" class="hover:text-brand-accent transition"><?= htmlspecialchars($post['title']) ?></a>
                            </h3>
                            <div class="mt-auto pt-4 flex items-center text-xs text-brand-olive border-t border-brand-beige">
                                <span class="font-medium mr-3"><?= htmlspecialchars($post['author']) ?></span>
                                <span><?= date('d M, Y', strtotime($post['date'])) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Controles de Paginación -->
        <?php if($totalPages > 1): ?>
        <div class="mt-12 flex justify-center items-center space-x-2">
            <?php if($currentPage > 1): ?>
                <a href="?id=<?= $categoryId ?>&page=<?= $currentPage - 1 ?>" class="px-4 py-2 border border-brand-beige text-brand-dark rounded hover:bg-brand-beige transition">Anterior</a>
            <?php endif; ?>
            
            <span class="px-4 py-2 text-brand-dark font-medium border border-transparent">Página <?= $currentPage ?> de <?= $totalPages ?></span>
            
            <?php if($currentPage < $totalPages): ?>
                <a href="?id=<?= $categoryId ?>&page=<?= $currentPage + 1 ?>" class="px-4 py-2 border border-brand-beige text-brand-dark rounded hover:bg-brand-beige transition">Siguiente</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-4">
        <?php require 'includes/sidebar.php'; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
