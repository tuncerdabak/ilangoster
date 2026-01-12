<?php
require_once '../db.php';
session_start();

// --- DEBUG: Loglama ---
file_put_contents('callback_log.txt', print_r($_POST, true), FILE_APPEND);

$platform_order_id = $_POST['platform_order_id'] ?? null;
$status = $_POST['status'] ?? null;
$random_nr = $_POST['random_nr'] ?? null;
$signature_from_shopier = $_POST['signature'] ?? null;

if (!$platform_order_id || !$status || !$random_nr || !$signature_from_shopier) {
    die("HATA: Eksik parametre.");
}

// --- 1. Geri Gelen Hash'i Doğrulama (Güvenlik Katmanı) ---
// Shopier Secret'ı trimlemeyi unutma
$api_secret = trim(get_setting($pdo, 'shopier_secret', SHOPIER_SECRET));

$data = $random_nr . $platform_order_id; // Standard Shopier callback imzası
$expected_signature = hash_hmac('SHA256', $data, $api_secret, true);
$expected_signature = base64_encode($expected_signature);

if ($expected_signature !== $signature_from_shopier) {
    // Loglama: İmza hatası detayını da yazalım
    file_put_contents('callback_log.txt', "SIG ERROR: Expected $expected_signature != Got $signature_from_shopier\n", FILE_APPEND);
    die("GÜVENLİK HATASI: Geçersiz imza.");
}

// --- 2. Sipariş ID'sinden kullanıcı ve paketi çekme ---
list($user_id, $package_key) = explode('_', $platform_order_id);

// --- 3. Loglama Başlat ---
$price = 0.00;
$package = $GLOBALS['PACKAGES'][$package_key] ?? null;
if ($package)
    $price = $package['price'];

if ($status === 'success') {
    if ($package) {
        $duration = $package['duration_days'];
        $gallery_limit = $package['gallery_limit'];
        $photo_limit = $package['photo_limit'];

        // Yeni bitiş tarihini hesapla
        $new_active_until = date('Y-m-d H:i:s', strtotime("+" . $duration . " days"));

        // Kullanıcıyı aktif et ve limitlerini güncelle
        $stmt = $pdo->prepare("UPDATE users SET 
            package_name = ?, 
            gallery_limit = ?, 
            photo_limit = ?, 
            active_until = ? 
            WHERE id = ?");
        $stmt->execute([$package_key, $gallery_limit, $photo_limit, $new_active_until, $user_id]);

        // DB Log (Başarılı)
        $stmt_log = $pdo->prepare("INSERT INTO payments (user_id, order_id, package_name, amount, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$user_id, $platform_order_id, $package_key, $price, 'success']);

        // Eğer oturum varsa (Kullanıcı tarayıcısı yönlendirilmişse)
        if (isset($_SESSION['user_id'])) {
            header('Location: success.php');
            exit;
        }

        echo "OK";
    } else {
        // Geçersiz paket ama ödeme gelmiş
        $stmt_log = $pdo->prepare("INSERT INTO payments (user_id, order_id, package_name, amount, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$user_id, $platform_order_id, $package_key, 0.00, 'invalid_package']);
        echo "OK";
    }
} else {
    // Ödeme başarısız
    $stmt_log = $pdo->prepare("INSERT INTO payments (user_id, order_id, package_name, amount, status) VALUES (?, ?, ?, ?, ?)");
    $stmt_log->execute([$user_id, $platform_order_id, $package_key ?? 'unknown', $price, 'failed']);
    echo "OK";
}
// Bu dosya sadece Shopier sunucusuna 'OK' yanıtı vermelidir. Kullanıcı dönüşü return.php'den yönetilir.