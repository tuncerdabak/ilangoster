<?php
require_once '../db.php';
session_start();

$package_key = $_GET['package'] ?? '';
$agent_phone = $_SESSION['user_phone'] ?? $_SESSION['admin_phone'] ?? null;

if (empty($package_key) || !isset($GLOBALS['PACKAGES'][$package_key])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Geçersiz paket seçimi.'];
    header('Location: ../index.php#packages');
    exit;
}

if (!$agent_phone) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Ödeme başlatmak için lütfen giriş yapın veya kayıt olun.'];
    header('Location: ../login.php?package=' . $package_key);
    exit;
}

$package = $GLOBALS['PACKAGES'][$package_key];

// Fiyatı Ayarlardan Çek
if ($package_key == 'standard') {
    $price = get_setting($pdo, 'price_standard', $package['price']);
} elseif ($package_key == 'premium') {
    $price = get_setting($pdo, 'price_premium', $package['price']);
} else {
    $price = $package['price'];
}

if ($price <= 0 && $package_key != 'free') {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Bu paket fiyatı geçersiz.'];
    header('Location: ../index.php#packages');
    exit;
}

// Kullanıcı bilgilerini çek veya oluştur
$stmt_user = $pdo->prepare("SELECT id, created_at FROM users WHERE phone = ?");
$stmt_user->execute([$agent_phone]);
$user = $stmt_user->fetch();

if (!$user) {
    $created_at = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO users (phone, package_name, created_at) VALUES (?, 'free', ?)")->execute([$agent_phone, $created_at]);
    $user_id = $pdo->lastInsertId();
    $user_registered = $created_at;
} else {
    $user_id = $user['id'];
    $user_registered = $user['created_at'];
}

// Kullanıcı hesap yaşı (gün cinsinden)
$buyer_account_age = 0;
if ($user_registered) {
    $time_elapsed = time() - strtotime($user_registered);
    $buyer_account_age = (int) ($time_elapsed / 86400); // Gün cinsinden
}

// --- Shopier için Parametreler ---
$random_nr = rand(100000, 999999);
$platform_order_id = $user_id . '_' . $package_key . '_' . time();

$api_key = trim(get_setting($pdo, 'shopier_api_key', SHOPIER_API_KEY));
$api_secret = trim(get_setting($pdo, 'shopier_secret', SHOPIER_SECRET));

// API Anahtarı Kontrolü
if (empty($api_key) || empty($api_secret) || strpos($api_key, 'BURAYA') !== false) {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'Ödeme sistemi henüz yapılandırılmadı (API Key eksik). Lütfen yönetici ile iletişime geçin.'
    ];
    header('Location: ../index.php');
    exit;
}

$price_formatted = number_format($price, 2, '.', '');

// **CRITICAL: Shopier için ZORUNLU parametreler**
// WooCommerce eklentisindekiyle TAMAMEN AYNI olmalı
$args = [
    'API_key' => $api_key,
    'website_index' => 1,
    'use_adress' => 0, // 0: Fatura adresi, 1: Teslimat adresi

    // Sipariş bilgileri
    'platform_order_id' => $platform_order_id,
    'total_order_value' => $price_formatted,
    'currency' => 0, // 0 = TL, 1 = USD, 2 = EUR

    // Ürün bilgileri
    'product_name' => substr($package['name_tr'], 0, 99),
    'product_type' => 1, // 1: Digital ürün

    // Müşteri bilgileri
    'buyer_name' => 'Kullanıcı',
    'buyer_surname' => substr($agent_phone, 0, 49),
    'buyer_email' => 'info@ilangoster.com',
    'buyer_account_age' => $buyer_account_age,
    'buyer_id_nr' => $user_id,
    'buyer_phone' => $agent_phone,

    // Fatura adresi (use_adress = 0 olduğu için bu kullanılacak)
    'billing_address' => 'Online Satış',
    'billing_city' => 'İstanbul',
    'billing_country' => 'TR',
    'billing_postcode' => '34000',

    // Teslimat adresi (use_adress = 0 olduğu için aynı)
    'shipping_address' => 'Online Satış',
    'shipping_city' => 'İstanbul',
    'shipping_country' => 'TR',
    'shipping_postcode' => '34000',

    // Diğer parametreler
    'platform' => 0, // Platform tipi
    'is_in_frame' => 0, // DİKKAT: "is_iframe" DEĞİL, "is_in_frame"
    'current_language' => 0, // 0 = Türkçe
    'modul_version' => '2.0.0',
    'random_nr' => $random_nr,

    // Ürün bilgisi JSON formatında (WooCommerce eklentisinde olduğu gibi)
    'product_info' => json_encode([
        [
            'name' => $package['name_tr'],
            'product_id' => $package_key,
            'product_type' => 1,
            'quantity' => 1,
            'price' => $price_formatted,
            'total_price' => $price_formatted,
        ]
    ], JSON_UNESCAPED_UNICODE),

    // Genel bilgiler
    'general_info' => json_encode([
        'total' => $price_formatted,
        'order_key' => $platform_order_id,
    ], JSON_UNESCAPED_UNICODE),
];

// **CRITICAL: Signature oluşturma - WOOCOMMERCE EKLENTİSİYLE AYNI ŞEKİLDE**
$data = $args["random_nr"] . $args["platform_order_id"] . $args["total_order_value"] . $args["currency"];
$signature = hash_hmac('SHA256', $data, $api_secret, true);
$signature = base64_encode($signature);
$args['signature'] = $signature;

// **Shopier Endpoint URL'si**
$shopier_endpoint = "https://www.shopier.com/ShowProduct/api_pay4.php";

// **DEBUG: Parametreleri kontrol etmek için**
echo "<!-- DEBUG INFO:";
echo "\nAPI Key: " . $api_key;
echo "\nPlatform Order ID: " . $platform_order_id;
echo "\nTotal: " . $price_formatted;
echo "\nCurrency: " . $args['currency'];
echo "\nData for Hash: " . $data;
echo "\nSignature: " . $args['signature'];
echo "\n-->";

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <title>Ödeme Başlatılıyor...</title>
    <script type="text/javascript">
        // $shop değişkenini tanımla (Shopier'ın beklediği değişken)
        var $shop = {
            currency: 'TRY',
            domain: '<?php echo SITE_URL; ?>',
            name: 'İlan Göster'
        };
    </script>
</head>

<body onload="document.getElementById('shopier_form').submit();">
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h2>Ödeme İşlemi Başlatılıyor</h2>
        <p>Lütfen bekleyin, Shopier ödeme sayfasına yönlendiriliyorsunuz...</p>
        <p>Eğer 5 saniye içinde yönlendirilmezseniz, aşağıdaki butona tıklayın.</p>
    </div>

    <form method="post" action="<?php echo $shopier_endpoint; ?>" id="shopier_form">
        <?php
        foreach ($args as $key => $value) {
            // HTML karakterlerini encode et
            $encoded_value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . $encoded_value . '" />' . "\n";
        }
        ?>
        <div style="text-align: center; margin-top: 20px;">
            <input type="submit" value="Ödemeye Devam Et" style="padding: 10px 20px; font-size: 16px;">
        </div>
    </form>

    <script>
        // Otomatik submit
        setTimeout(function () {
            document.getElementById('shopier_form').submit();
        }, 2000);

        // updateDiscountStorage fonksiyonunu tanımla (eğer shopier.js bu fonksiyonu çağırıyorsa)
        function updateDiscountStorage() {
            // Basit bir implementasyon
            if (typeof $shop !== 'undefined') {
                console.log('Shop info:', $shop);
            }
        }

        // Fonksiyonu çağır
        updateDiscountStorage();
    </script>
</body>

</html>