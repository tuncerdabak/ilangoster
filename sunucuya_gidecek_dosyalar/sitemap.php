<?php
header("Content-Type: application/xml; charset=utf-8");
require_once 'db.php';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>
            <?= SITE_URL ?>/
        </loc>
        <lastmod>
            <?= date('Y-m-d') ?>
        </lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>
            <?= SITE_URL ?>/login.php
        </loc>
        <lastmod>
            <?= date('Y-m-d') ?>
        </lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
</urlset>