<?php
require_once '../db.php';
session_start();

// Varsayılan Değerler
$defaults = [
    'API_key' => trim(get_setting($pdo, 'shopier_api_key', SHOPIER_API_KEY)),
    'API_secret' => trim(get_setting($pdo, 'shopier_secret', SHOPIER_SECRET)),
    'website_index' => 1,
    'platform_order_id' => '999_debug_' . time(),
    'product_type' => 1,
    'total_order_value' => '50.00'
];

// Formdan gelen değerler varsa onları kullan (Signature Update için)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = array_merge($defaults, $_POST);
} else {
    $current = $defaults;
}

// Shopier Args Oluştur
$args = [
    'API_key' => $current['API_key'],
    'website_index' => (int) $current['website_index'],
    'platform_order_id' => $current['platform_order_id'],
    'total_order_value' => $current['total_order_value'],
    'currency' => 0,
    'is_iframe' => 0,
    'customer_name' => 'Kullanıcı',
    'customer_surname' => 'Test',
    'customer_email' => 'info@ilangoster.com',
    'product_name' => 'Debug Test Paket',
    'product_type' => (int) $current['product_type'],
    'callback_url' => SHOPIER_CALLBACK_URL,
    'return_url' => SITE_URL . '/payment/return.php',
    'installments_number' => 0,
    'random_nr' => rand(100000, 999999),
];

// SIGNATURE HESAPLA
$data = $args["random_nr"] . $args["platform_order_id"] . $args["total_order_value"] . $args["currency"];
$signature = hash_hmac('SHA256', $data, $current['API_secret'], true); // Secret formdan veya db'den
$signature = base64_encode($signature);
$args['signature'] = $signature;

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <title>Gelişmiş Shopier Debugger</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 p-10">

    <div class="max-w-4xl mx-auto bg-white p-8 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Shopier Entegrasyon Testi</h1>

        <div class="grid grid-cols-2 gap-8">
            <!-- 1. AYARLAR FORMU -->
            <div>
                <h2 class="font-bold text-lg mb-4 border-b pb-2">1. Parametreleri Düzenle</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold">API Key</label>
                        <input type="text" name="API_key" value="<?= htmlspecialchars($current['API_key']) ?>"
                            class="w-full border p-2 rounded text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold">API Secret (imza için)</label>
                        <input type="text" name="API_secret" value="<?= htmlspecialchars($current['API_secret']) ?>"
                            class="w-full border p-2 rounded text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-bold">Website Index (1, 2, 0 dene)</label>
                        <input type="number" name="website_index"
                            value="<?= htmlspecialchars($current['website_index']) ?>"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-bold">Product Type (0=Fiziksel, 1=Dijital)</label>
                        <input type="number" name="product_type"
                            value="<?= htmlspecialchars($current['product_type']) ?>" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-bold">Tutar (Örn: 50.00)</label>
                        <input type="text" name="total_order_value"
                            value="<?= htmlspecialchars($current['total_order_value']) ?>"
                            class="w-full border p-2 rounded">
                    </div>

                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-bold w-full">
                        İmzayı Yenile ve Kaydet
                    </button>
                </form>
            </div>

            <!-- 2. GÖNDERİM FORMU -->
            <div>
                <h2 class="font-bold text-lg mb-4 border-b pb-2">2. Shopier'e Gönder</h2>
                <p class="text-sm text-gray-600 mb-4">Aşağıdaki form otomatik oluşturulan imzalı datadır.</p>

                <form action="<?php echo SHOPIER_ENDPOINT; ?>" method="post" target="_blank" class="space-y-2">
                    <?php foreach ($args as $key => $value): ?>
                        <div class="flex items-center text-xs">
                            <span class="w-32 font-mono font-bold text-gray-700"><?= $key ?>:</span>
                            <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>" readonly
                                class="bg-gray-100 border text-gray-600 flex-1 px-1">
                        </div>
                    <?php endforeach; ?>

                    <button type="submit"
                        class="bg-green-600 text-white px-6 py-4 rounded font-bold w-full mt-4 text-lg shadow-lg hover:bg-green-700">
                        TEST ET ->
                    </button>
                </form>

                <div class="mt-4 p-4 bg-yellow-50 text-xs font-mono break-all border border-yellow-200 rounded">
                    <strong>Hash Data String:</strong><br>
                    <?= htmlspecialchars($data) ?>
                </div>
            </div>
        </div>
    </div>

</body>

</html>