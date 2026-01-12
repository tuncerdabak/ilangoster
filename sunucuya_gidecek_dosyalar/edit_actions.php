<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum açmanız gerekiyor.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'delete_photo') {
    $photo_id = $_POST['photo_id'] ?? null;

    if (!$photo_id) {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz fotoğraf id.']);
        exit;
    }

    // Fotoğrafın kullanıcıya ait olup olmadığını doğrula
    $stmt = $pdo->prepare("SELECT i.*, g.user_id, g.id as gallery_id FROM images i 
                           JOIN galleries g ON i.gallery_id = g.id 
                           WHERE i.id = ? AND g.user_id = ?");
    $stmt->execute([$photo_id, $user_id]);
    $photo = $stmt->fetch();

    if (!$photo) {
        echo json_encode(['status' => 'error', 'message' => 'Fotoğraf bulunamadı veya yetkiniz yok.']);
        exit;
    }

    // Dosyayı sunucudan sil
    $file_path = $photo['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // DB'den sil
    $pdo->prepare("DELETE FROM images WHERE id = ?")->execute([$photo_id]);

    // Galeri photo_count'u güncelle
    $pdo->prepare("UPDATE galleries SET photo_count = photo_count - 1 WHERE id = ?")->execute([$photo['gallery_id']]);

    echo json_encode(['status' => 'success', 'message' => 'Fotoğraf silindi.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem.']);
