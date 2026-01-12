<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- SELF-HEALING MIGRATION ---
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS custom_logo VARCHAR(255) DEFAULT NULL;");
} catch (Exception $e) {
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    if ($user['package_name'] !== 'premium') {
        $message = "Bu özellik sadece Premium paket sahiplerine özeldir.";
    } else {
        $file = $_FILES['logo'];
        if ($file['error'] === 0) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $newName = 'logo_' . $user_id . '_' . time() . '.' . $ext;
                $target = UPLOAD_DIR . $newName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    // Eskisini sil (isteğe bağlı)
                    if ($user['custom_logo'] && file_exists(UPLOAD_DIR . $user['custom_logo'])) {
                        unlink(UPLOAD_DIR . $user['custom_logo']);
                    }

                    $stmt = $pdo->prepare("UPDATE users SET custom_logo = ? WHERE id = ?");
                    $stmt->execute([$newName, $user_id]);
                    $user['custom_logo'] = $newName;
                    $message = "Logonuz başarıyla güncellendi.";
                }
            } else {
                $message = "Lütfen PNG veya JPG formatında dosya yükleyin.";
            }
        }
    }
}
if ($user['package_name'] !== 'premium') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Profil & Logo Ayarları - İlanGöster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .premium-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .btn-premium {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transition: all 0.3s ease;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        }

        .logo-preview-container {
            border: 2px dashed #e5e7eb;
            transition: all 0.3s ease;
        }

        .logo-preview-container:hover {
            border-color: #4f46e5;
            background-color: #f9fafb;
        }

        .safe-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="flex items-center gap-2">
                        <img class="h-8 w-auto" src="logo.png" alt="İlanGöster">
                        <span class="font-bold text-gray-900 hidden sm:inline">Panelim</span>
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard.php"
                        class="text-sm font-semibold text-gray-600 hover:text-indigo-600 transition">Geri Dön</a>
                    <a href="logout.php"
                        class="text-sm font-semibold text-red-600 hover:text-red-700 transition">Çıkış</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Profil & Logo</h1>
                <p class="mt-2 text-sm text-gray-600">Portföylerinizde görünecek özel logonuzu buradan yönetebilirsiniz.
                </p>
            </div>

            <div class="premium-card p-6 sm:p-8">
                <?php if ($message): ?>
                    <div
                        class="mb-6 p-4 rounded-xl text-sm font-medium <?php echo str_contains($message, 'başarıyla') ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-blue-50 text-blue-700 border border-blue-100'; ?>">
                        <div class="flex items-center gap-2">
                            <?php if (str_contains($message, 'başarıyla')): ?>
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                    </path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php endif; ?>
                            <?= $message ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-8">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Üyelik Paketi</span>
                        <span
                            class="px-2 py-1 text-[10px] font-black bg-indigo-100 text-indigo-700 rounded-full uppercase"><?= htmlspecialchars($user['package_name']) ?></span>
                    </div>
                    <?php if ($user['package_name'] === 'free'): ?>
                        <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-3">
                            <p class="text-[11px] text-yellow-800 leading-snug">
                                <span class="font-bold">⚠️ Ücretsiz Mod:</span> Logonuz filigran olarak eklenemez. Sadece
                                Premium üyeler özel logo kullanabilir.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-3">Mevcut Logonuz</label>
                        <div class="logo-preview-container rounded-2xl p-6 flex flex-col items-center justify-center">
                            <?php if ($user['custom_logo']): ?>
                                <img src="uploads/<?= htmlspecialchars($user['custom_logo']) ?>"
                                    class="max-h-32 w-auto object-contain rounded-lg shadow-sm">
                                <p class="mt-4 text-xs font-semibold text-green-600 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Aktif Kullanılıyor
                                </p>
                            <?php else: ?>
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-2">
                                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </div>
                                <p class="text-xs text-gray-400 text-center px-4">Henüz logo yüklenmedi. Varsayılan filigran
                                    kullanılacak.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-gray-700">Logo Değiştir</label>
                        <div class="relative">
                            <input type="file" name="logo" id="logo-input" class="hidden"
                                accept="image/png, image/jpeg, image/jpg">
                            <label for="logo-input"
                                class="flex items-center justify-center w-full px-4 py-3 border-2 border-gray-200 border-dashed rounded-xl cursor-pointer hover:bg-gray-50 hover:border-indigo-300 transition-all group">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                        </path>
                                    </svg>
                                    <span class="text-sm font-semibold text-gray-600 group-hover:text-indigo-600"
                                        id="file-name">Dosya Seç (PNG veya JPG)</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full btn-premium text-white font-bold py-4 rounded-xl shadow-lg ring-offset-2 focus:ring-2 focus:ring-indigo-500 active:scale-95 transition-all">
                        DEĞİŞİKLİKLERİ KAYDET
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-gray-100">
                    <a href="dashboard.php"
                        class="flex items-center justify-center gap-2 text-indigo-600 font-bold text-sm hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Panele Geri Dön
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-6 text-center text-xs text-gray-400 safe-bottom">
        &copy; <?= date('Y') ?> ilangoster.com &bull; Profesyonel Portföy Yönetimi
    </footer>

    <script>
        document.getElementById('logo-input').addEventListener('change', function (e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Dosya Seç (PNG veya JPG)';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>
</body>

</html>