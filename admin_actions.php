<?php
require_once 'db.php';
session_start();

// Güvenlik: Admin Kontrolü
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("Erişim reddedildi.");
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- 1. Veri Dışa Aktarma (Excel/CSV) ---
if ($action === 'export_users') {
    $filename = "kullanicilar_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // UTF-8 BOM ekle (Excel'in Türkçe karakterleri düzgün açması için)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Başlıklar
    fputcsv($output, ['ID', 'Telefon', 'Paket', 'Kayıt Tarihi', 'Bitiş Tarihi']);

    // Verileri çek (Limitsiz)
    $stmt = $pdo->query("SELECT id, phone, package_name, created_at, active_until FROM users ORDER BY id DESC");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// --- 2. Veritabanı Yedekleme (SQL Dump) ---
if ($action === 'backup_db') {
    $filename = "backup_ilangoster_" . date('Y-m-d_H-i') . ".sql";

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename=' . $filename);

    // Tabloları listele
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql_script = "-- İlanGöster Veritabanı Yedeği\n";
    $sql_script .= "-- Tarih: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        $stmt_create = $pdo->query("SHOW CREATE TABLE $table");
        $row_create = $stmt_create->fetch(PDO::FETCH_NUM);

        $sql_script .= "\n\n-- Tablo yapısı: $table --\n";
        $sql_script .= $row_create[1] . ";\n";

        $stmt_data = $pdo->query("SELECT * FROM $table");
        $rowCount = $stmt_data->rowCount();

        if ($rowCount > 0) {
            $sql_script .= "\n-- Veriler: $table --\n";
            while ($row = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map(function ($value) use ($pdo) {
                    return $value === null ? "NULL" : $pdo->quote($value);
                }, array_values($row));
                $sql_script .= "INSERT INTO $table VALUES (" . implode(", ", $values) . ");\n";
            }
        }
    }

    echo $sql_script;
    exit;
}

// --- 3. Temizlik İşlemleri (Disk Cleanup) ---
if ($action === 'cleanup_files') {
    // Süresi dolmuş galerileri bul
    $stmt_expired = $pdo->prepare("SELECT id FROM galleries WHERE expire_at < NOW() OR is_expired = 1");
    $stmt_expired->execute();
    $expired_galleries = $stmt_expired->fetchAll(PDO::FETCH_COLUMN);

    $deleted_files_count = 0;

    if (!empty($expired_galleries)) {
        $gallery_ids = implode(',', $expired_galleries);

        // Bu galerilere ait resimleri bul
        $stmt_images = $pdo->query("SELECT image_path FROM images WHERE gallery_id IN ($gallery_ids)");
        $images = $stmt_images->fetchAll(PDO::FETCH_COLUMN);

        foreach ($images as $path) {
            $full_path = realpath(__DIR__ . '/' . $path);
            if ($full_path && file_exists($full_path)) {
                @unlink($full_path);
                $deleted_files_count++;
            }
        }

        // Galeri kayıtlarını da "is_expired=1" olarak işaretle (Silmiyoruz, log kalsın diye, sadece dosyaları uçurduk)
        // Eğer tamamen silmek isterseniz DELETE de yapabilirsiniz. Şimdilik dosyaları sildik.

        // Ekstra Önlem: DB'den de resim kayıtlarını silebiliriz diskten sildiysek
        $pdo->query("DELETE FROM images WHERE gallery_id IN ($gallery_ids)");

        // Galerileri 'is_expired = 1' olarak işaretle (zaten öyle olabilir ama expire_at'ten yakaladıklarımızı da işaretleyelim)
        $pdo->query("UPDATE galleries SET is_expired = 1 WHERE id IN ($gallery_ids)");
    }

    $_SESSION['admin_message'] = "Temizlik Tamamlandı: $deleted_files_count adet fotoğraf diskten silindi.";
    header("Location: admin.php?tab=maintenance");
    exit;
}

// Bilinmeyen eylem
header("Location: admin.php");
exit;
