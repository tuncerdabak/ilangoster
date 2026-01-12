<?php
// Bu dosya haftalık olarak CRON ile çalıştırılmalıdır (Örn: Pazar 03:00)
// Komut: /usr/bin/php /home/kullanici_adiniz/public_html/cron/clean_expired.php

require_once '../db.php';

// --- 1. Temizlik Kriteri ---
// is_expired = 1 olan (yani view.php'de süresi dolduğu anlaşılan) 
// VE expire_at üzerinden 7 günden fazla geçmiş olan galerileri bul.
$stmt_galleries = $pdo->query("
    SELECT g.id, i.image_path 
    FROM galleries g
    JOIN images i ON g.id = i.gallery_id
    WHERE g.is_expired = 1 
    AND g.expire_at < NOW() - INTERVAL 7 DAY
");

$galleries_to_delete = [];
while ($row = $stmt_galleries->fetch()) {
    $galleries_to_delete[$row['id']][] = $row['image_path'];
}

$deleted_files_count = 0;
$deleted_galleries_count = 0;

if (!empty($galleries_to_delete)) {
    foreach ($galleries_to_delete as $gallery_id => $paths) {
        $files_deleted = true;

        // --- 2. Fiziksel Dosyaları Sil ---
        foreach ($paths as $path) {
            $full_path = realpath(__DIR__ . '/../' . $path);
            if (file_exists($full_path)) {
                if (unlink($full_path)) {
                    $deleted_files_count++;
                } else {
                    // Silinemeyen dosya varsa DB kaydını tutmaya devam et
                    $files_deleted = false;
                    error_log("CRON HATA: Dosya silinemedi: " . $full_path);
                }
            }
        }

        // --- 3. DB Kaydını Sil (Dosyalar başarılı silinmişse) ---
        if ($files_deleted) {
            // images tablosu CASCADE ile silinecektir
            $pdo->prepare("DELETE FROM galleries WHERE id = ?")->execute([$gallery_id]);
            $deleted_galleries_count++;
        }
    }
}

echo "CRON JOB BAŞARILI: " . $deleted_galleries_count . " galeri ve " . $deleted_files_count . " dosya silindi. \n";