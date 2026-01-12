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
    if ($user['package_name'] !== 'premium' && $user['package_name'] !== 'standard') {
        $message = "Bu özellik sadece ücretli paketlerde geçerlidir.";
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
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Profil Ayarları - İlanGöster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <nav class="bg-white shadow mb-8">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between">
            <a href="dashboard.php" class="font-bold text-gray-800">Panelim</a>
            <a href="logout.php" class="text-red-600">Çıkış Yap</a>
        </div>
    </nav>
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-xl shadow">
        <h1 class="text-2xl font-bold mb-6">Profil & Filigran Ayarları</h1>

        <?php if ($message): ?>
            <div class="p-4 mb-4 bg-blue-100 text-blue-700 rounded">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="mb-8">
            <h2 class="text-lg font-semibold mb-2">Paketiniz: <span class="text-indigo-600">
                    <?= strtoupper($user['package_name']) ?>
                </span></h2>
            <p class="text-sm text-gray-500 italic">Özel logo filigranı sadece Standart ve Premium paketlerde
                kullanılabilir.</p>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Mevcut Logonuz</label>
                <div class="mt-2 p-4 border rounded-lg bg-gray-50 text-center">
                    <?php if ($user['custom_logo']): ?>
                        <img src="uploads/<?= htmlspecialchars($user['custom_logo']) ?>"
                            class="mx-auto h-24 object-contain mb-2">
                        <p class="text-xs text-green-600">Aktif olarak kullanılıyor.</p>
                    <?php else: ?>
                        <p class="text-gray-400">Henüz logo yüklenmedi. Varsayılan metin filigranı (ilangoster.com)
                            kullanılacak.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Yeni Logo Yükle (PNG/JPG)</label>
                <input type="file" name="logo"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700">Kaydet</button>
        </form>

        <div class="mt-8 pt-6 border-t">
            <a href="dashboard.php" class="text-indigo-600 font-bold">&larr; Panele Dön</a>
        </div>
    </div>
</body>

</html>