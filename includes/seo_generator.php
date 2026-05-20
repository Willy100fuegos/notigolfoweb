<?php
require_once __DIR__ . '/markdown_parser.php';

function generateSeoFiles() {
    $indexFile = __DIR__ . '/../content/posts_index.json';
    if (!file_exists($indexFile)) return;
    $posts = json_decode(file_get_contents($indexFile), true);
    if (!is_array($posts)) return;

    $domain = 'https://notigolfoveracruz.com';
    $baseUrl = $domain . '/article.php?id=';

    // 1. Generate Sitemap (sitemap.xml)
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($posts as $post) {
        $loc = htmlspecialchars($baseUrl . $post['filename']);
        $lastmod = date('Y-m-d', strtotime($post['date']));
        $sitemap .= "  <url>\n";
        $sitemap .= "    <loc>{$loc}</loc>\n";
        $sitemap .= "    <lastmod>{$lastmod}</lastmod>\n";
        $sitemap .= "  </url>\n";
    }
    $sitemap .= "</urlset>";
    $sitemapPath = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
    file_put_contents($sitemapPath, $sitemap);

    // 2. Generate RSS Feed (feed.rss)
    $rssPosts = array_slice($posts, 0, 50);
    $rss = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    $rss .= '<rss version="2.0">' . "\n";
    $rss .= "  <channel>\n";
    $rss .= "    <title>NotiGolfo Veracruz</title>\n";
    $rss .= "    <link>{$domain}</link>\n";
    $rss .= "    <description>Las mejores noticias de Veracruz.</description>\n";
    
    foreach ($rssPosts as $post) {
        $link = htmlspecialchars($baseUrl . $post['filename']);
        $title = htmlspecialchars($post['title']);
        $pubDate = date('r', strtotime($post['date']));
        $author = htmlspecialchars($post['author'] ?? 'Redacción');
        
        $mdPath = __DIR__ . '/../content/posts/' . $post['filename'] . '.md';
        $excerpt = $title;
        if (file_exists($mdPath)) {
            $raw = file_get_contents($mdPath);
            $parsed = parseFrontmatter($raw);
            $cleanText = strip_tags(parseMarkdown($parsed['content'] ?? ''));
            $excerpt = htmlspecialchars(mb_substr(trim($cleanText), 0, 150) . '...');
        }

        $rss .= "    <item>\n";
        $rss .= "      <title>{$title}</title>\n";
        $rss .= "      <link>{$link}</link>\n";
        $rss .= "      <pubDate>{$pubDate}</pubDate>\n";
        $rss .= "      <author>{$author}</author>\n";
        $rss .= "      <description>{$excerpt}</description>\n";
        $rss .= "    </item>\n";
    }
    $rss .= "  </channel>\n</rss>";
    $rssPath = $_SERVER['DOCUMENT_ROOT'] . '/feed.rss';
    file_put_contents($rssPath, $rss);
}

// Ejecución directa si se llama al archivo por URL
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    generateSeoFiles();
    echo "Archivos SEO generados exitosamente.";
}
