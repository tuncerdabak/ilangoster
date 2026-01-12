<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum açmanız gerekiyor.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek.']);
    exit;
}

$gallery_id = $_POST['gallery_id'] ?? null;
$customer_phone = $_POST['customer_phone'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$gallery_id || !$customer_phone) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik bilgi.']);
    exit;
}

// 1. Galeri Doğrulama (Bu kullanıcıya mı ait?)
$stmt = $pdo->prepare("SELECT id, unique_token, expire_at FROM galleries WHERE id = ? AND user_id = ?");
$stmt->execute([$gallery_id, $user_id]);
$gallery = $stmt->fetch();

if (!$gallery) {
    echo json_encode(['status' => 'error', 'message' => 'Galeri bulunamadı veya yetkiniz yok.']);
    exit;
}

// 2. Müşteri Telefonunu Kaydet/Güncelle
// Sadece temizlenmiş numara
$customer_phone = preg_replace('/[^0-9]/', '', $customer_phone);

if (strlen($customer_phone) != 10) {
    echo json_encode(['status' => 'error', 'message' => 'Telefon numarası 10 haneli olmalıdır.']);
    exit;
}

$stmt_update = $pdo->prepare("UPDATE galleries SET customer_phone = ?, shared_at = NOW() WHERE id = ?");
$stmt_update->execute([$customer_phone, $gallery_id]);

// 3. Link Hazırla
$gallery_link = SITE_URL . '/g/' . $gallery['unique_token'];
$whatsapp_msg = "🏠 Güvenli Portföy Resim Paylaşımı\r\n\r\nMerhaba, portföy fotoğraflarını ilangoster.com üzerinden güvenli olarak paylaşıyorum.\r\n\r\n⏳ 24 Saat Geçerli\r\nGüvenlik nedeniyle resimler 24 saat sonra otomatik silinecektir.\r\n\r\n👇 Görüntülemek için tıklayın:\r\n" . $gallery_link;

$whatsapp_url = "https://wa.me/?text=" . urlencode($whatsapp_msg);

echo json_encode([
    'status' => 'success',
    'whatsapp_url' => $whatsapp_url,
    'message' => 'Paylaşım hazırlanıyor...'
]);
