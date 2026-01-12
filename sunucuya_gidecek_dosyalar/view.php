<?php
require_once 'db.php';

session_start();

$unique_token = $_GET['token'] ?? ''; // .htaccess ile gelen token

if (empty($unique_token) || strlen($unique_token) !== 32) {
    die("<h1>Geçersiz bağlantı formatı.</h1>");
}

// Galeri kontrolü
$stmt = $pdo->prepare("SELECT id, expire_at, is_expired FROM galleries WHERE unique_token = ?");
$stmt->execute([$unique_token]);
$gallery = $stmt->fetch();

if (!$gallery || $gallery['is_expired'] || strtotime($gallery['expire_at']) < time()) {
    // Eğer süresi dolduysa ve is_expired bayrağı 0 ise, bayrağı güncelle (Cron'un işini kolaylaştır)
    if ($gallery && !$gallery['is_expired']) {
        $pdo->prepare("UPDATE galleries SET is_expired = 1 WHERE id = ?")->execute([$gallery['id']]);
    }
    die("
        <h1 style='color: red; text-align: center; margin-top: 100px;'>
            🔐 Galeri Süresi Dolmuştur veya Geçersizdir.
            <br>
            <span style='font-size: 16px; color: gray;'>İlan Sahibinden yeni bir link isteyiniz.</span>
        </h1>
    ");
}

// --- GATEKEEPER (Telefon Doğrulama) ---
$session_key = 'view_access_' . $unique_token;
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['viewer_phone'])) {
    $phone = preg_replace('/[^0-9]/', '', $_POST['viewer_phone']);
    if (str_starts_with($phone, '0')) {
        $phone = substr($phone, 1);
    }

    // Validasyon
    if (strlen($phone) !== 10 || substr($phone, 0, 1) !== '5') {
        $error_msg = "Lütfen geçerli bir cep telefonu giriniz (Başında 0 olmadan, 5 ile başlayan 10 hane).";
    } elseif (preg_match('/(\d)\1{7,}/', $phone)) {
        // 7'den fazla aynı rakam yan yana ise (örn: 5555555555) geçersiz say
        $error_msg = "Lütfen geçerli bir telefon numarası giriniz.";
    } else {
        // Başarılı
        $_SESSION[$session_key] = $phone;

        // DB'ye Logla
        try {
            $stmt_log = $pdo->prepare("INSERT INTO gallery_views (gallery_id, viewer_phone) VALUES (?, ?)");
            $stmt_log->execute([$gallery['id'], $phone]);
        } catch (Exception $e) {
            // Log hatası kullanıcıyı engellemesin
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

if (!isset($_SESSION[$session_key])) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>İlan Görüntüleme</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>

    <body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-xl shadow-2xl max-w-md w-full text-center">
            <h2 class="text-2xl font-bold mb-2 text-gray-800">Hoş Geldiniz</h2>
            <p class="text-gray-600 mb-6">İlan resimlerini görüntülemek için lütfen cep telefonu numaranızı giriniz.</p>

            <?php if ($error_msg): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-left text-sm font-medium text-gray-700 mb-1">Cep Telefonu</label>
                    <input type="tel" name="viewer_phone" required placeholder="05XXXXXXXXX" maxlength="11"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <p class="text-xs text-gray-400 mt-1 text-left">Başında 0 olmadan giriniz.</p>
                </div>
                <button type="submit"
                    class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition">
                    İlanı Görüntüle
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-6">Bu bilgi ilan sahibi ile paylaşılabilir.</p>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// Resimleri çek
$stmt_images = $pdo->prepare("SELECT image_path FROM images WHERE gallery_id = ? ORDER BY id ASC");
$stmt_images->execute([$gallery['id']]);
$images = $stmt_images->fetchAll();

$remaining_time = strtotime($gallery['expire_at']) - time();
$hours = floor($remaining_time / 3600);
$minutes = floor(($remaining_time % 3600) / 60);
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlan Görselleri - Güvenli Önizleme</title>
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:image" content="<?= SITE_URL ?>/logo.png">
    <meta property="og:description" content="İlan görsellerini incelemek için tıklayın.">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom styles if any */
    </style>
</head>

<body class="bg-gray-900 min-h-screen">
    <!-- Navbar -->
    <nav class="bg-gray-800 p-4 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="https://ilangoster.com" class="flex items-center gap-2">
                <img src="<?= SITE_URL ?>/logo.png" alt="İlanGöster" class="h-8 w-auto">
                <span class="text-xl font-bold tracking-wider text-white">ilan<span
                        class="text-green-400">goster.com</span></span>
            </a>

            <div class="flex items-center gap-4">
                <a href="https://ilangoster.com"
                    class="flex items-center gap-2 bg-gray-700 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Geri Dön
                </a>
                <?php if (isset($hours) && isset($minutes)): ?>
                    <div
                        class="text-[10px] md:text-sm text-gray-300 bg-gray-700 px-3 py-1.5 rounded-lg flex items-center gap-2">
                        <span class="hidden sm:inline">⏱️ Kalan Süre:</span>
                        <span class="font-mono text-white font-bold"><?= $hours ?>sa <?= $minutes ?>dk</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto p-4 md:p-6 pb-20">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($images as $img): ?>
                <div class="relative group">
                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($img['image_path']); ?>" loading="lazy"
                        class="w-full h-auto rounded-lg shadow-lg transform transition duration-500 hover:scale-[1.02]"
                        alt="İlan Resmi">

                    <!-- Watermark Overlay (Optional visual reinforcement) -->
                    <div
                        class="absolute bottom-2 right-2 opacity-50 text-white text-xs font-bold pointer-events-none drop-shadow-md">
                        <?= get_setting($pdo, 'watermark_text', WATERMARK_TEXT) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($images)): ?>
            <div class="text-center text-gray-400 py-20">
                <p>Bu galeride henüz görsel bulunmuyor.</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="text-center text-gray-500 text-xs py-6">
        &copy; <?= date('Y') ?> İlanGöster.com - Güvenli Portföy Paylaşımı
    </footer>

</body>

</html>