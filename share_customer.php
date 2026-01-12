<?php
require_once 'db.php';
session_start();

$gallery_id = $_SESSION['temp_gallery_id'] ?? $_POST['gallery_id'] ?? null;
if (isset($_SESSION['temp_gallery_id'])) {
    unset($_SESSION['temp_gallery_id']);
}

if (!$gallery_id) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Geçersiz galeri isteği. Lütfen resmi yeniden yükleyin.'];
    header('Location: index.php');
    exit;
}

// Galeri bilgilerini çek
$stmt = $pdo->prepare("SELECT unique_token, expire_at FROM galleries WHERE id = ?");
$stmt->execute([$gallery_id]);
$gallery = $stmt->fetch();

if (!$gallery) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Galeri bulunamadı.'];
    header('Location: index.php');
    exit;
}

$gallery_link = SITE_URL . '/g/' . $gallery['unique_token'];
$whatsapp_msg = "🏠 Güvenli Portföy Resim Paylaşımı\n\nMerhaba, portföy fotoğraflarını ilangoster.com üzerinden güvenli olarak paylaşıyorum.\n\n⏳ 24 Saat Geçerli\nGüvenlik nedeniyle resimler 24 saat sonra otomatik silinecektir.\n\n👇 Görüntülemek için tıklayın:\n" . $gallery_link;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['customer_phone'])) {
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_phone = preg_replace('/[^0-9]/', '', $customer_phone);

    // Müşteri bilgisini kaydet
    $stmt_update = $pdo->prepare("UPDATE galleries SET customer_phone = ?, shared_at = NOW() WHERE id = ?");
    $stmt_update->execute([$customer_phone, $gallery_id]);

    // WhatsApp'a yönlendir
    $whatsapp_url = "https://wa.me/?text=" . urlencode($whatsapp_msg);
    header("Location: " . $whatsapp_url);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whatsapp Paylaşım</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f9;
        }
    </style>
    <meta property="og:image" content="<?= SITE_URL ?>/logo.png">
</head>

<body class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 md:p-10 rounded-xl shadow-2xl text-gray-800 max-w-lg w-full text-center">
        <div class="flex justify-center mb-6">
            <a href="index.php"><img src="logo.png" alt="İlanGöster" class="h-16 w-auto"></a>
        </div>
        <h2 class="text-2xl font-bold mb-4 text-green-600">Başarılı! Paylaşım Öncesi Son Adım</h2>
        <p class="mb-6 text-gray-600">Portföyünüzün güvenliğini sağlamak ve müşterinizi takip edebilmek için, bu linki
            göndereceğiniz müşterinizin telefon numarasını giriniz.</p>

        <form action="share_customer.php" method="POST" class="space-y-6">
            <input type="hidden" name="gallery_id" value="<?php echo $gallery_id; ?>">

            <div>
                <label for="customer_phone" class="block text-left text-sm font-medium text-gray-700 mb-2">
                    Whatsapp'dan Göndermek istediğiniz Telefon Numarası
                </label>
                <input type="tel" id="customer_phone" name="customer_phone" required pattern="[0-9]{10}" maxlength="10"
                    placeholder="5XXXXXXXXX (10 Hane)"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 transition duration-150">
            </div>

            <button type="submit"
                class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg w-full shadow-lg hover:bg-green-700 transition duration-200">
                WhatsApp'tan Linki Gönder
            </button>
        </form>

        <p class="mt-8 text-sm text-gray-500">
            Galeri Linki:
        <div class="flex items-center justify-center mt-2 space-x-2">
            <input type="text" id="gallery-link" value="<?php echo $gallery_link; ?>" readonly
                class="font-mono text-xs p-2 bg-gray-100 rounded border border-gray-300 w-64 text-center text-gray-600 focus:outline-none">
            <button type="button" onclick="copyLink()"
                class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 p-2 rounded transition-colors"
                title="Linki Kopyala">
                <!-- Copy Icon -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                    </path>
                </svg>
            </button>
        </div>
        <p id="copy-msg" class="text-xs text-green-600 mt-1 hidden">Link kopyalandı!</p>

        <script>
            function copyLink() {
                var copyText = document.getElementById("gallery-link");
                copyText.select();
                copyText.setSelectionRange(0, 99999); /* For mobile devices */
                navigator.clipboard.writeText(copyText.value).then(() => {
                    document.getElementById('copy-msg').classList.remove('hidden');
                    setTimeout(() => { document.getElementById('copy-msg').classList.add('hidden'); }, 2000);
                });
            }
        </script>
        <br>
        <span class="text-red-500">Bu link <?php echo date('d.m.Y H:i', strtotime($gallery['expire_at'])); ?>
            tarihine kadar açıktır.</span>
        </p>
    </div>
</body>

</html>