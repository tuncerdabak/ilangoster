<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$gallery_id = $_GET['id'] ?? null;

if (!$gallery_id) {
    header('Location: dashboard.php');
    exit;
}

// Galeri ve Sahibi Doğrulama
$stmt = $pdo->prepare("SELECT * FROM galleries WHERE id = ? AND user_id = ?");
$stmt->execute([$gallery_id, $user_id]);
$gallery = $stmt->fetch();

if (!$gallery) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Galeri bulunamadı veya yetkiniz yok.'];
    header('Location: dashboard.php');
    exit;
}

// Resimleri Getir
$stmt_images = $pdo->prepare("SELECT * FROM images WHERE gallery_id = ? ORDER BY id ASC");
$stmt_images->execute([$gallery_id]);
$images = $stmt_images->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Portföyü Düzenle - İlanGöster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .photo-card {
            transition: all 0.3s ease;
        }

        .photo-card:hover {
            transform: translateY(-4px);
        }

        .delete-overlay {
            background: rgba(220, 38, 38, 0.85);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .photo-card:hover .delete-overlay {
            opacity: 1;
        }
    </style>
</head>

<body class="min-h-screen pb-20">

    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center gap-2">
                <img src="logo.png" alt="İlanGöster" class="h-10 w-auto">
                <span class="text-xl font-bold text-gray-800">ilan<span class="text-green-600">goster.com</span></span>
            </a>
            <a href="dashboard.php" class="text-gray-500 hover:text-gray-700 font-bold text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Geri Dön
            </a>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto py-8 px-4">
        <header class="mb-8">
            <h1 class="text-2xl font-black text-gray-900 leading-tight">Portföy Fotoğraflarını Yönet</h1>
            <p class="text-gray-500 text-sm mt-1">Galeri #
                <?= $gallery['id'] ?> (
                <?= count($images) ?> Fotoğraf)
            </p>
        </header>

        <?php if (empty($images)): ?>
            <div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
                <div class="text-5xl mb-4">🖼️</div>
                <h3 class="text-xl font-bold text-gray-800">Hiç fotoğraf kalmadı</h3>
                <p class="text-gray-500 mt-2 mb-6">Bu portföy şu anda boş görünüyor.</p>
                <a href="dashboard_new.php" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold">Yeni Fotoğraf
                    Yükle</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($images as $img): ?>
                    <div id="img-container-<?= $img['id'] ?>"
                        class="photo-card relative aspect-square bg-gray-200 rounded-2xl overflow-hidden shadow-sm border border-white">
                        <img src="<?= $img['image_path'] ?>" class="w-full h-full object-cover">

                        <div class="delete-overlay absolute inset-0 flex items-center justify-center p-4">
                            <button onclick="deletePhoto(<?= $img['id'] ?>)"
                                class="bg-white text-red-600 px-4 py-2 rounded-xl text-xs font-black shadow-xl transform active:scale-95 transition">
                                FOTOĞRAFI SİL
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function deletePhoto(photoId) {
            if (!confirm('Bu fotoğrafı kalıcı olarak silmek istediğinize emin misiniz?')) return;

            const btn = event.target;
            btn.disabled = true;
            btn.innerText = 'SİLİNİYOR...';

            try {
                const response = await fetch('edit_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_photo&photo_id=${photoId}`
                });

                const result = await response.json();

                if (result.status === 'success') {
                    const container = document.getElementById(`img-container-${photoId}`);
                    container.style.opacity = '0';
                    container.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        container.remove();
                        // Sayfayı yenilemeye gerek yok ama fotoğraf sayısı değiştiği için bildirim verilebilir
                        if (document.querySelectorAll('.photo-card').length === 0) {
                            location.reload();
                        }
                    }, 300);
                } else {
                    alert(result.message || 'Bir hata oluştu.');
                    btn.disabled = false;
                    btn.innerText = 'FOTOĞRAFI SİL';
                }
            } catch (error) {
                alert('Bağlantı hatası oluştu.');
                btn.disabled = false;
                btn.innerText = 'FOTOĞRAFI SİL';
            }
        }
    </script>
</body>

</html>