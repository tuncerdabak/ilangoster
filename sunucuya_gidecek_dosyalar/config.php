<?php
// PHP Hata Gösterimini Kapat (Prod. Ortamı için)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// --- 1. Veritabanı Ayarları ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'tuncerda_ilan-goster'); // Kendi veritabanı adınızı girin
define('DB_USER', 'tuncerda_ilanci');
define('DB_PASS', 'Td3492549*');

// --- 2. Site ve Güvenlik Ayarları ---
define('SITE_URL', 'https://ilangoster.com'); // Sitenizin ana URL'si
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('WATERMARK_TEXT', 'ilangoster.com'); // Varsayılan filigran metni

// --- 3. Shopier API Ayarları (Test veya Gerçek Anahtarlarınız) ---
define('SHOPIER_API_KEY', 'SHOPIER_API_KEY_BURAYA');
define('SHOPIER_SECRET', 'SHOPIER_SECRET_BURAYA');
define('SHOPIER_ENDPOINT', 'https://www.shopier.com/ShowProduct/api_pay4.php');
define('SHOPIER_CALLBACK_URL', SITE_URL . '/payment/callback.php');

// --- 4. Paket Limitleri ---
$GLOBALS['PACKAGES'] = [
    'free' => [
        'price' => 0.00,
        'duration_days' => 1,          // 24 saat
        'gallery_limit' => 2,          // 3 Adet Aktif Galeri
        'photo_limit' => 10,           // Toplam 10 Fotoğraf
        'name_tr' => 'Ücretsiz Kullanım'
    ],
    'standard' => [
        'price' => 50.00,
        'duration_days' => 30,         // 30 gün
        'gallery_limit' => 5,
        'photo_limit' => 50,
        'name_tr' => 'Standart Paket'
    ],
    'premium' => [
        'price' => 250.00,
        'duration_days' => 90,         // 3 ay (90 gün)
        'gallery_limit' => 10,
        'photo_limit' => 100,
        'name_tr' => 'Premium Paket'
    ]
];