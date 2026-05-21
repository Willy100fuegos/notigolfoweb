<?php
date_default_timezone_set('America/Mexico_City');
require_once 'includes/markdown_parser.php';
require_once 'includes/indexer.php';

// Leer posts
$posts = getPostsIndex();

// 1. Lógica PHP (Preparación de Datos)
// Filtrar destacadas
$featuredPosts = array_filter($posts, function($p) {
    return isset($p['featured']) && filter_var($p['featured'], FILTER_VALIDATE_BOOLEAN);
});
// Fallback: Si hay menos de 4 destacadas, rellenar con las más recientes
if (count($featuredPosts) < 4) {
    $featuredUrls = array_column($featuredPosts, 'filename');
    foreach ($posts as $p) {
        if (!in_array($p['filename'], $featuredUrls)) {
            $featuredPosts[] = $p;
            if (count($featuredPosts) >= 4) break;
        }
    }
}
$featuredPosts = array_values($featuredPosts);

$recentPostsSidebar = array_slice($posts, 0, 5); // Notas para sidebar

$groupedPosts = [
    'local' => [], 'estatal' => [], 'nacional' => [], 
    'policiaca' => [], 'internacional' => [], 
    'deportes' => [], 'sociedad' => []
];

foreach ($posts as $post) {
    $cat = strtolower(trim($post['category'] ?? ''));
    
    // Normalize aliases/accents
    $catMap = [
        'policíaca' => 'policiaca',
        'internacional' => 'internacional',
        'sociedad' => 'sociedad'
    ];
    if (isset($catMap[$cat])) {
        $cat = $catMap[$cat];
    }
    
    if (isset($groupedPosts[$cat])) {
        $groupedPosts[$cat][] = $post;
    }
}

// Categories definitions for display
$categoriesList = [
    'local' => 'Local', 'estatal' => 'Estatal', 'nacional' => 'Nacional', 
    'policiaca' => 'Policíaca', 'internacional' => 'Internacional', 
    'deportes' => 'Deportes', 'sociedad' => 'Sociedad'
];

$currentCategory = '';
require 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-6 md:py-8 w-full">
    
    <!-- 1. Sección Carrusel (Destacadas Principales) -->
    <!-- 1. Sección Carrusel (Destacadas Principales) -->
    <?php if(!empty($featuredPosts)): ?>
    
    <div class="relative overflow-hidden w-full mb-8 group h-[300px] md:h-[400px] lg:h-[450px]" id="heroCarousel">
        <!-- Contenedor Track -->
        <div class="flex flex-nowrap w-full h-full transition-transform duration-500 ease-in-out" id="carouselTrack" style="transform: translateX(0%);">
            <?php foreach($featuredPosts as $post): ?>
                <!-- Slide -->
                <div class="w-full flex-shrink-0 relative lg:w-1/4 h-full px-0 lg:px-1">
                    <a href="/article.php?id=<?= $post['filename'] ?>" class="block w-full h-full relative overflow-hidden rounded-lg bg-gray-900">
                        <!-- Imagen de Fondo -->
                        <?php 
                        if (empty($post['featured_image'])) {
                            $imgSrc = 'https://placehold.co/1200x600/1a1a1a/4a4a4a?text=NotiGolfo';
                        } else {
                            $imgSrc = strpos(trim($post['featured_image']), 'http') === 0 ? trim($post['featured_image']) : '/content/images/' . trim($post['featured_image']); 
                        }
                        ?>
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover absolute inset-0 z-0 hover:scale-105 transition-transform duration-700">
                        <!-- Overlay (Sombra) -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/50 to-transparent z-10 pointer-events-none"></div>
                        <!-- Contenido (Textos) -->
                        <div class="absolute bottom-0 left-0 p-4 md:p-6 z-20 w-full flex flex-col gap-2">
                            <div>
                                <span class="text-[10px] md:text-xs font-bold uppercase tracking-wider bg-brand-gold text-white px-2 py-1 rounded inline-block">
                                    <?= htmlspecialchars($categoriesList[$post['category']] ?? $post['category']) ?>
                                </span>
                            </div>
                            <span class="text-gray-300 text-xs md:text-sm"><?= date('d M, Y', strtotime($post['date'])) ?></span>
                            <h3 class="text-white font-serif font-bold text-lg md:text-xl leading-tight hover:text-gray-200 transition line-clamp-3">
                                <?= htmlspecialchars($post['title']) ?>
                            </h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Botones de Navegación -->
        <button id="carouselPrev" class="absolute left-2 top-1/2 -translate-y-1/2 z-30 text-white w-10 h-10 md:w-12 md:h-12 bg-black/60 hover:bg-black/90 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center cursor-pointer border-none outline-none">
            <i class="fas fa-chevron-left text-lg"></i>
        </button>
        <button id="carouselNext" class="absolute right-2 top-1/2 -translate-y-1/2 z-30 text-white w-10 h-10 md:w-12 md:h-12 bg-black/60 hover:bg-black/90 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center cursor-pointer border-none outline-none">
            <i class="fas fa-chevron-right text-lg"></i>
        </button>
    </div>

    <!-- Lógica de Desplazamiento Responsivo (Vanilla JS) -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const track = document.getElementById('carouselTrack');
        const prevBtn = document.getElementById('carouselPrev');
        const nextBtn = document.getElementById('carouselNext');
        const totalSlides = <?= count($featuredPosts) ?>;
        
        let currentIndex = 0;
        let autoPlayInterval;

        const getVisibleSlides = () => {
            return window.innerWidth >= 1024 ? 4 : 1;
        };

        const updateCarousel = () => {
            const visible = getVisibleSlides();
            // Computadora: 25% por slide. Móvil: 100% por slide.
            const slideWidthPercentage = 100 / visible;
            const percentage = -(currentIndex * slideWidthPercentage);
            track.style.transform = 'translateX(' + percentage + '%)';
        };

        const nextSlide = () => {
            const visible = getVisibleSlides();
            const maxIndex = Math.max(0, totalSlides - visible);
            
            currentIndex++;
            if (currentIndex > maxIndex) {
                currentIndex = 0; // Bucle infinito
            }
            updateCarousel();
        };

        const prevSlide = () => {
            const visible = getVisibleSlides();
            const maxIndex = Math.max(0, totalSlides - visible);

            currentIndex--;
            if (currentIndex < 0) {
                currentIndex = maxIndex; // Ir al final
            }
            updateCarousel();
        };

        const startAutoPlay = () => {
            autoPlayInterval = setInterval(nextSlide, 5000);
        };

        const resetAutoPlay = () => {
            clearInterval(autoPlayInterval);
            startAutoPlay();
        };

        nextBtn.addEventListener('click', () => {
            nextSlide();
            resetAutoPlay();
        });

        prevBtn.addEventListener('click', () => {
            prevSlide();
            resetAutoPlay();
        });

        // Ajuste en tiempo real al rotar pantalla o cambiar tamaño
        window.addEventListener('resize', () => {
            const visible = getVisibleSlides();
            const maxIndex = Math.max(0, totalSlides - visible);
            if (currentIndex > maxIndex) {
                currentIndex = maxIndex;
            }
            updateCarousel();
        });

        // Iniciar autoplay si hay suficientes notas
        if(totalSlides > getVisibleSlides()) {
            startAutoPlay();
        } else {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        }
    });
    </script>
    <?php endif; ?>

    <!-- 2. Layout Inferior (Feed y Sidebar) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Feed de Noticias (Izquierda, 8 columnas) -->
        <div class="lg:col-span-8 flex flex-col space-y-12">
            <?php 
            $hasCategoryPosts = false;
            foreach ($categoriesList as $catSlug => $catName):
                $catPosts = $groupedPosts[$catSlug] ?? [];
                if (empty($catPosts)) continue;
                $hasCategoryPosts = true;
                $mainPost = $catPosts[0];
                $secondaryPosts = array_slice($catPosts, 1, 3);
            ?>
            
            <section class="category-block">
                <!-- Encabezado del Bloque -->
                <div class="flex items-center mb-6">
                    <h2 class="text-2xl font-serif font-bold text-brand-dark uppercase tracking-wide"><?= htmlspecialchars($categoriesList[$catSlug]) ?></h2>
                    <div class="flex-grow h-px bg-gray-300 mx-4"></div>
                    <a href="/category.php?id=<?= $catSlug ?>" class="text-sm font-bold text-brand-accent hover:text-brand-dark transition whitespace-nowrap">Ver más &rarr;</a>
                </div>
                
                <!-- Grid del Bloque -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    
                    <!-- Nota Principal (Izquierda) -->
                    <div class="md:col-span-7">
                        <article class="group">
                            <a href="/article.php?id=<?= $mainPost['filename'] ?>" class="block overflow-hidden rounded-xl mb-4 h-64 md:h-80 relative bg-gray-100">
                                <?php 
                                if (empty($mainPost['featured_image'])) {
                                    $mainImgSrc = 'https://placehold.co/600x400/e2e8f0/94a3b8?text=Sin+Imagen';
                                } else {
                                    $mainImgSrc = strpos(trim($mainPost['featured_image']), 'http') === 0 ? trim($mainPost['featured_image']) : '/content/images/' . trim($mainPost['featured_image']); 
                                }
                                ?>
                                <img src="<?= htmlspecialchars($mainImgSrc) ?>" alt="<?= htmlspecialchars($mainPost['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            </a>
                            <div class="flex flex-col">
                                <div class="mb-2 text-xs font-bold text-brand-olive uppercase tracking-wider">
                                    <?= htmlspecialchars($mainPost['author']) ?> &bull; <?= date('d M, Y', strtotime($mainPost['date'])) ?>
                                </div>
                                <h3 class="text-2xl md:text-3xl font-serif font-bold text-brand-dark leading-tight mb-3">
                                    <a href="/article.php?id=<?= $mainPost['filename'] ?>" class="hover:text-brand-accent transition"><?= htmlspecialchars($mainPost['title']) ?></a>
                                </h3>
                                <p class="text-gray-600 line-clamp-3">
                                    <?= htmlspecialchars($mainPost['excerpt'] ?? '') ?>
                                </p>
                            </div>
                        </article>
                    </div>
                    
                    <!-- Lista Secundaria (Derecha) -->
                    <?php if (!empty($secondaryPosts)): ?>
                    <div class="md:col-span-5 flex flex-col gap-4">
                        <?php foreach ($secondaryPosts as $secPost): ?>
                        <article class="flex gap-4 items-center group">
                            <a href="/article.php?id=<?= $secPost['filename'] ?>" class="w-24 h-24 flex-shrink-0 block overflow-hidden rounded-lg bg-gray-100">
                                <?php 
                                if (empty($secPost['featured_image'])) {
                                    $secImgSrc = 'https://placehold.co/150x150/e2e8f0/94a3b8?text=Nota';
                                } else {
                                    $secImgSrc = strpos(trim($secPost['featured_image']), 'http') === 0 ? trim($secPost['featured_image']) : '/content/images/' . trim($secPost['featured_image']); 
                                }
                                ?>
                                <img src="<?= htmlspecialchars($secImgSrc) ?>" alt="<?= htmlspecialchars($secPost['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            </a>
                            <div class="flex flex-col justify-center">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-brand-accent mb-1">
                                    <?= htmlspecialchars($categoriesList[$secPost['category']] ?? $secPost['category']) ?>
                                </span>
                                <h4 class="text-sm font-serif font-bold text-brand-dark leading-snug line-clamp-2 mb-1">
                                    <a href="/article.php?id=<?= $secPost['filename'] ?>" class="hover:text-brand-accent transition"><?= htmlspecialchars($secPost['title']) ?></a>
                                </h4>
                                <span class="text-xs text-gray-500"><?= date('d M, Y', strtotime($secPost['date'])) ?></span>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </section>
            
            <?php endforeach; ?>
            
            <?php if(!$hasCategoryPosts && empty($heroPosts)): ?>
                <p class="text-brand-olive text-center py-10 bg-white rounded-xl border border-brand-beige">No hay noticias publicadas aún.</p>
            <?php elseif(!$hasCategoryPosts && !empty($heroPosts)): ?>
                <p class="text-brand-olive text-center py-10 bg-white rounded-xl border border-brand-beige">No hay más noticias por el momento.</p>
            <?php endif; ?>
            
        </div>
        
        <!-- Sidebar Flotante (Derecha, 4 columnas) -->
        <div class="lg:col-span-4">
            <?php require 'includes/sidebar.php'; ?>
        </div>
        
    </div>
</div>

<?php require 'includes/footer.php'; ?>
