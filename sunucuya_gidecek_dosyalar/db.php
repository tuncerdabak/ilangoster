<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- Tablo Kontrolleri ve Ayarlar ---
    // 1. Settings tablosu yoksa oluştur
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (PDOException $e) {
    // Üretim ortamında hata mesajını kullanıcıya gösterme
    die("Sistem şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.");
}

/**
 * Veritabanından ayar çeker. Yoksa varsayılan değeri (veya sabiti) döner.
 */
function get_setting($pdo, $key, $default = null)
{
    // 1. DB'den dene
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if ($row) {
        return $row['setting_value'];
    }

    // 2. Default parametresi verilmişse onu dön
    if ($default !== null) {
        return $default;
    }

    // 3. Hiçbiri yoksa ve aynı isimde bir CONSTANT varsa onu dön (Geriye uyumluluk)
    $const_name = strtoupper($key);
    if (defined($const_name)) {
        return constant($const_name);
    }

    return null;
}

// --- 5. Paket Limitlerini DB'den Çek ve Global Değişkeni Güncelle ---
// Bu kod db.php'nin en sonunda çalışır, böylece $pdo hazırdır.
$GLOBALS['PACKAGES']['free']['gallery_limit'] = (int) get_setting($pdo, 'pkg_free_limit_gallery', 2);
$GLOBALS['PACKAGES']['free']['photo_limit'] = (int) get_setting($pdo, 'pkg_free_limit_photo', 10);
$GLOBALS['PACKAGES']['free']['duration_days'] = (int) get_setting($pdo, 'pkg_free_days', 1); // 24 saat (1 gün olarak tutulabilir veya config'de saat bazlıdır ama burada gün)

$GLOBALS['PACKAGES']['standard']['price'] = (float) get_setting($pdo, 'pkg_standard_price', 50.00);
$GLOBALS['PACKAGES']['standard']['gallery_limit'] = (int) get_setting($pdo, 'pkg_standard_limit_gallery', 5);
$GLOBALS['PACKAGES']['standard']['photo_limit'] = (int) get_setting($pdo, 'pkg_standard_limit_photo', 50);
$GLOBALS['PACKAGES']['standard']['duration_days'] = (int) get_setting($pdo, 'pkg_standard_days', 30);

$GLOBALS['PACKAGES']['premium']['price'] = (float) get_setting($pdo, 'pkg_premium_price', 250.00);
$GLOBALS['PACKAGES']['premium']['gallery_limit'] = (int) get_setting($pdo, 'pkg_premium_limit_gallery', 10);
$GLOBALS['PACKAGES']['premium']['photo_limit'] = (int) get_setting($pdo, 'pkg_premium_limit_photo', 100);
$GLOBALS['PACKAGES']['premium']['duration_days'] = (int) get_setting($pdo, 'pkg_premium_days', 90);
