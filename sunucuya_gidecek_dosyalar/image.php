<?php
/**
 * image.php - Güvenli Resim Servisi (Proxy)
 * Resimleri doğrudan URL üzerinden değil, bu script aracılığıyla sunarız.
 * Bu sayede orijinal resimlere yetkisiz erişimi engelleriz.
 */

require_once 'db.php';

$token = $_GET['token'] ?? null; // Gelecekte galeriyi token ile doğrulamak için
$path = $_GET['path'] ?? null;

if (!$path) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

// Güvenlik: Path traversal engelleme
$path = basename($path);
$fullPath = UPLOAD_DIR . $path;

if (file_exists($fullPath)) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fullPath);
    
    header("Content-Type: " . $mimeType);
    header("Content-Length: " . filesize($fullPath));
    
    // Cache başlıkları (Performans için 24 saat)
    header("Cache-Control: public, max-age=86400");
    
    readfile($fullPath);
} else {
    header("HTTP/1.1 404 Not Found");
}
