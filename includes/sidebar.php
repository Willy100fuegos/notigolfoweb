<?php
require_once __DIR__ . '/indexer.php';
$config = getSiteConfig();

if (!isset($recentPostsSidebar)) {
    $allPosts = getPostsIndex();
    $recentPostsSidebar = array_slice($allPosts, 0, 5);
}

$catsList = [
    'local' => 'Local', 'estatal' => 'Estatal', 'nacional' => 'Nacional', 
    'policiaca' => 'Policíaca', 'internacional' => 'Internacional', 
    'deportes' => 'Deportes', 'sociedad' => 'Sociedad'
];
?>
<!-- Sidebar -->
<aside class="w-full flex-shrink-0 sticky top-20 self-start">
    <div class="space-y-6">
        
        <!-- Widget Notas Recientes -->
        <div class="bg-white p-5 rounded-2xl border border-brand-beige shadow-sm">
            <h3 class="text-lg font-bold font-sans mb-4 border-b pb-2 text-brand-dark">Notas Recientes</h3>
            <ul class="flex flex-col gap-4">
                <?php foreach($recentPostsSidebar as $post): ?>
                <li class="flex gap-4 items-center group">
                    <a href="/article.php?id=<?= $post['filename'] ?>" class="w-24 h-24 flex-shrink-0 block overflow-hidden rounded-lg bg-gray-100">
                        <?php 
                        if (empty($post['featured_image'])) {
                            $sideImgSrc = 'https://placehold.co/150x150/e2e8f0/94a3b8?text=Nota';
                        } else {
                            $sideImgSrc = strpos(trim($post['featured_image']), 'http') === 0 ? trim($post['featured_image']) : '/content/images/' . trim($post['featured_image']); 
                        }
                        ?>
                        <img src="<?= htmlspecialchars($sideImgSrc) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                    </a>
                    <div class="flex flex-col justify-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-brand-accent mb-1 block">
                            <?= htmlspecialchars($catsList[$post['category']] ?? $post['category'] ?? 'General') ?>
                        </span>
                        <a href="/article.php?id=<?= $post['filename'] ?>" class="text-sm font-sans font-bold text-brand-dark hover:text-brand-accent leading-snug line-clamp-2 mb-1 transition">
                            <?= htmlspecialchars($post['title']) ?>
                        </a>
                        <span class="text-xs text-gray-500"><?= date('d M, Y', strtotime($post['date'])) ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if(empty($recentPostsSidebar)): ?>
                    <p class="text-sm text-gray-500">No hay notas recientes.</p>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Banner Sidebar 1 -->
        <div class="w-full bg-gray-100 rounded-2xl flex items-center justify-center border border-gray-200 overflow-hidden min-h-[250px]">
            <?php if (!empty($config['banners']['sidebar1'])): ?>
                <img src="<?= htmlspecialchars($config['banners']['sidebar1']) ?>" alt="Ad" class="w-full object-contain">
            <?php else: ?>
                <span class="text-gray-400 text-sm">Banner Sidebar 1 (300x250)</span>
            <?php endif; ?>
        </div>

        <!-- Banner Sidebar 2 -->
        <div class="w-full bg-gray-100 rounded-2xl flex items-center justify-center border border-gray-200 overflow-hidden min-h-[250px]">
            <?php if (!empty($config['banners']['sidebar2'])): ?>
                <img src="<?= htmlspecialchars($config['banners']['sidebar2']) ?>" alt="Ad" class="w-full object-contain">
            <?php else: ?>
                <span class="text-gray-400 text-sm">Banner Sidebar 2 (300x250)</span>
            <?php endif; ?>
        </div>
        
    </div>
</aside>
