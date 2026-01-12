<?php
require_once 'db.php';
session_start();

// --- AJAX Limit Kontrolü ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_limit') {
    header('Content-Type: application/json');
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    if (str_starts_with($phone, '0')) {
        $phone = substr($phone, 1);
    }

    if (strlen($phone) !== 10) {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz telefon numarası.']);
        exit;
    }

    // Kullanıcıyı bul
    $stmt = $pdo->prepare("SELECT id, package_name, gallery_limit, active_until FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user) {
        $package_config = $GLOBALS['PACKAGES'][$user['package_name']] ?? $GLOBALS['PACKAGES']['free'];

        // 1. Süre Kontrolü
        if ($user['package_name'] !== 'free' && $user['active_until'] && strtotime($user['active_until']) < time()) {
            // Paket süresi dolmuş, Free'ye düşmüş varsayılır ama kullanıcıya bilgi verelim
            echo json_encode([
                'status' => 'limit_reached',
                'message' => 'Mevcut paketinizin süresi dolmuştur. Devam etmek için paket yenileyiniz veya ücretsiz kullanıma geçiş yapılacaktır.'
            ]);
            exit;
        }

        // 2. Galeri Limiti Kontrolü
        $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM galleries WHERE agent_phone = ? AND is_expired = 0");
        $stmt_count->execute([$phone]);
        $current_galleries = $stmt_count->fetchColumn();

        // Kullanıcının limiti veya paketin varsayılan limitini al
        $limit = $user['gallery_limit'] > 0 ? $user['gallery_limit'] : $package_config['gallery_limit'];

        if ($current_galleries >= $limit) {
            echo json_encode([
                'status' => 'limit_reached',
                'message' => "Galeri limitiniz dolmuştur ({$current_galleries}/{$limit}). Yeni ilan yüklemek için paket yükseltin."
            ]);
            exit;
        }
    } else {
        // Yeni kullanıcı (Free): Henüz hiç galerisi yok, izin ver.
        // Ancak IP bazlı vb. bir kontrol yoksa her yeni numara yeni limit demektir.
    }

    echo json_encode(['status' => 'ok']);
    exit;
}

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ilangoster: Güvenli Portföy Resim Paylaşım Platformu</title>
    <meta property="og:image" content="<?= SITE_URL ?>/logo.png">
    <meta property="og:description" content="Portföy resimlerinizi güvenle paylaşın.">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f9;
        }

        .hero {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .feature-card {
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-upload {
            background-color: #f59e0b;
        }

        .btn-upload:hover {
            background-color: #d97706;
        }

        .message-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .message-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .message-warning {
            background-color: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        /* Loading Overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(17, 24, 39, 0.9);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #loading-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .loader-content {
            text-align: center;
            color: #fff;
            width: 90%;
            max-width: 400px;
        }

        .progress-container {
            width: 100%;
            height: 8px;
            background: #374151;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #10b981, #3b82f6);
            border-radius: 99px;
            transition: width 0.2s ease;
            box-shadow: 0 0 15px #3b82f6;
        }

        .loading-text {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: 1px;
            background: linear-gradient(to right, #fff, #9ca3af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .upload-status {
            margin-top: 15px;
            font-family: monospace;
            font-size: 1.1rem;
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="loader-content">
            <div class="mb-4">
                <svg class="animate-spin h-12 w-12 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
            </div>
            <div class="loading-text">YÜKLENİYOR...</div>
            <div class="text-sm text-gray-400 mb-4">Lütfen bekleyiniz, fotoğraflar işleniyor.</div>
            <div class="progress-container">
                <div id="progress-bar" class="progress-bar"></div>
            </div>
            <div id="upload-status" class="upload-status">%0</div>
        </div>
    </div>

    <!-- Mobile App Download Banner (Show only on Mobile) -->
    <div class="md:hidden bg-indigo-600 text-white px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="bg-white p-1 rounded-lg">
                <img src="logo.png" alt="App" class="h-8 w-auto">
            </div>
            <div>
                <p class="text-xs font-bold leading-tight">İlan Göster Android Uygulaması</p>
                <p class="text-[10px] opacity-80">Hemen indir, ilanlarını cebinden yönet!</p>
            </div>
        </div>
        <a href="indir.php"
            class="bg-yellow-400 text-indigo-900 text-xs font-black px-4 py-2 rounded-full shadow-lg">İNDİR</a>
    </div>

    <!-- Header / Navbar -->

    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-2 group">
                <img src="logo.png" alt="İlanGöster" class="h-12 w-auto transition transform group-hover:scale-105">
                <span class="text-2xl font-bold text-gray-800">ilan<span class="text-green-600">goster.com</span></span>
            </a>
            <nav class="space-x-4">
                <a href="#packages" class="text-gray-600 hover:text-green-600 font-medium">Fiyatlar</a>
                <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
                    <a href="<?php echo isset($_SESSION['admin_id']) ? 'admin.php' : 'dashboard.php'; ?>"
                        class="text-indigo-600 hover:text-indigo-800 font-medium">Panelim</a>
                <?php else: ?>
                    <a href="login.php" class="text-indigo-600 hover:text-indigo-800 font-medium">Giriş</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Ana Başlık ve Form Bölümü -->
    <div class="hero pt-16 pb-24 text-center">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
                <h1 class="text-4xl font-extrabold mb-4 leading-tight">
                    Portföy Yönetim Ekranı
                </h1>
                <p class="text-xl font-light mb-12 opacity-90">
                    Yeni ilan fotoğraflarınızı güvenle yükleyin.
                </p>
            <?php else: ?>
                <h1 class="text-6xl font-extrabold mb-4 leading-tight">
                    Güvenli Resim Paylaşma Platformu
                </h1>
                <p class="text-2xl font-light mb-12 opacity-90">
                    Sadece Görsel, Risk Yok, Endişe Yok Portföy Resimlerini Özgürce Paylaş.
                </p>
            <?php endif; ?>

            <!-- Yükleme Formu -->
            <div class="bg-white p-8 md:p-10 rounded-xl shadow-2xl text-gray-800 mx-auto max-w-lg">
                <?php if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>
                    <h3 class="text-xl font-semibold mb-6 text-green-600">Hemen Başla (Ücretsiz ve Kayıtsız!)</h3>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div
                        class="p-4 mb-4 rounded-lg text-sm <?php echo $message['type'] === 'success' ? 'message-success' : ($message['type'] === 'warning' ? 'message-warning' : 'message-error'); ?>">
                        <?php echo htmlspecialchars($message['text']); ?>
                    </div>
                <?php endif; ?>

                <!-- Limit Uyarısı Alanı -->
                <div id="limit-alert" class="hidden p-4 mb-4 rounded-lg text-sm message-error text-left">
                    <p id="limit-message" class="font-bold mb-2"></p>
                    <a href="#packages"
                        class="inline-block bg-indigo-600 text-white text-xs font-bold py-2 px-4 rounded hover:bg-indigo-700">Fiyatları
                        İncele</a>
                </div>

                <!-- FORM BAŞLANGICI - EKSİK OLAN KISIM -->
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <!-- Telefon Numarası -->
                    <div class="mb-6">
                        <label for="agent_phone" class="block text-left text-sm font-medium text-gray-700 mb-2">
                            Telefon Numarası (örn: 5XXXXXXXXX)
                        </label>
                        <input type="text" id="agent_phone" name="agent_phone"
                            value="<?php echo isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : (isset($_SESSION['admin_phone']) ? htmlspecialchars($_SESSION['admin_phone']) : ''); ?>"
                            <?php echo (isset($_SESSION['user_phone']) || isset($_SESSION['admin_phone'])) ? 'readonly' : ''; ?>
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 <?php echo (isset($_SESSION['user_phone']) || isset($_SESSION['admin_phone'])) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''; ?>"
                            placeholder="05XXXXXXXXX" required pattern="(0?5[0-9]{9})"
                            title="Telefon numaranızı giriniz (örn: 05xxxxxxxxx)">
                    </div>

                    <!-- Fotoğraflar -->
                    <div class="mb-6">
                        <label for="photos" class="block text-left text-sm font-medium text-gray-700 mb-2">
                            Mülk Fotoğrafları (Birden Fazla Seçilebilir)
                        </label>
                        <input type="file" id="photos" name="photos[]" multiple required
                            accept="image/jpeg, image/png, image/webp"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 cursor-pointer">
                    </div>

                    <!-- Gönder Butonu -->
                    <button type="submit" id="btn-submit"
                        class="btn-upload text-white font-bold py-3 px-6 rounded-lg w-full shadow-lg hover:shadow-xl transition duration-200">
                        Yükle ve Güvenli Linki Oluştur
                    </button>

                    <p class="text-xs text-gray-500 mt-2">
                        * Ücretsiz kullanımda link 24 saat sonra otomatik silinir.
                    </p>
                </form>
                <!-- FORM SONU -->
            </div>
        </div>
    </div>

    <!-- Özellikler Bölümü -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-12">Neden İlan Göster?</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Kart 1: Komisyon Güvencesi -->
                <div class="feature-card p-6 rounded-xl bg-gray-50 border border-gray-100 shadow-md">
                    <span class="text-4xl mb-4 inline-block">🛡️</span>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Güvenlik</h3>
                    <p class="text-gray-600">Paylaştığınız tüm görsellerde hiçbir iletişim bilgisi (telefon, logo) yer
                        almaz.</p>
                </div>

                <!-- Kart 2: 24 Saat Sonra Silinme -->
                <div class="feature-card p-6 rounded-xl bg-gray-50 border border-gray-100 shadow-md">
                    <span class="text-4xl mb-4 inline-block">👻</span>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">24 Saat Silinme</h3>
                    <p class="text-gray-600">Fotoğraflar, seçtiğiniz pakete göre 24 saat veya 90 gün sonra sunucudan
                        otomatik silinir. Fotoğraf hırsızlığına son verir.</p>
                </div>

                <!-- Kart 3: Tekrarlayan Filigran -->
                <div class="feature-card p-6 rounded-xl bg-gray-50 border border-gray-100 shadow-md">
                    <span class="text-4xl mb-4 inline-block">🖼️</span>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Kırılmaz Filigran Teknolojisi</h3>
                    <p class="text-gray-600">Imagick teknolojisi ile 45 derece açıyla ve şeffaflıkla eklenen filigran,
                        ekran
                        görüntüsü alınmasını zorlaştırmış oluruz.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Paketler Bölümü -->
    <section id="packages" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-12">Paket Seçenekleri</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">

                <!-- FREE PACKAGE -->
                <div
                    class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8 flex flex-col relative overflow-hidden transition transform hover:-translate-y-1 hover:shadow-2xl">
                    <h3 class="text-xl font-bold text-gray-400 uppercase tracking-wide mb-2">Başlangıç</h3>
                    <div class="text-5xl font-extrabold text-gray-900 mb-2">Ücretsiz</div>
                    <p class="text-gray-500 mb-6">Hızlı ve anlık paylaşımlar için ideal.</p>

                    <div class="bg-gray-100 rounded-lg p-4 mb-6 text-center">
                        <span class="block text-xs text-gray-500 uppercase font-bold">Paket Kullanım Süresi</span>
                        <span
                            class="block text-2xl font-black text-gray-800"><?= $GLOBALS['PACKAGES']['free']['duration_days'] ?>
                            SAAT</span>
                    </div>

                    <ul class="space-y-4 mb-8 text-left text-gray-600 flex-1">
                        <li class="flex items-start"><span class="text-green-500 mr-2">✔</span>
                            <?= $GLOBALS['PACKAGES']['free']['gallery_limit'] ?> Adet İlan Hakkı</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2">✔</span>
                            <?= $GLOBALS['PACKAGES']['free']['photo_limit'] ?> Fotoğraf/İlan</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2">✔</span> Güvenli Filigran</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2">✔</span> WhatsApp Desteği</li>
                    </ul>

                    <a href="#"
                        class="block w-full py-4 px-6 bg-gray-800 hover:bg-gray-900 text-white font-bold rounded-xl transition text-center">
                        Hemen Başla
                    </a>
                </div>

                <!-- STANDARD PACKAGE -->
                <div
                    class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 p-8 flex flex-col relative overflow-hidden transition transform hover:-translate-y-1 hover:shadow-2xl">
                    <div
                        class="absolute top-0 right-0 bg-indigo-100 text-indigo-800 text-xs font-bold px-3 py-1 rounded-bl-lg">
                        EMLAKÇI DOSTU</div>
                    <h3 class="text-xl font-bold text-indigo-600 uppercase tracking-wide mb-2">Standart</h3>
                    <div class="text-5xl font-extrabold text-gray-900 mb-2">
                        <?php echo number_format($GLOBALS['PACKAGES']['standard']['price'], 0); ?><span
                            class="text-2xl font-normal text-gray-500">TL</span>
                    </div>
                    <p class="text-gray-500 mb-6">Düzenli ilan paylaşan profesyoneller için.</p>

                    <div class="bg-indigo-50 rounded-lg p-4 mb-6 text-center border border-indigo-100">
                        <span class="block text-xs text-indigo-500 uppercase font-bold">Paket Kullanım Süresi</span>
                        <span class="block text-2xl font-black text-indigo-900">30 GÜN (1 AY)</span>
                    </div>

                    <ul class="space-y-4 mb-8 text-left text-gray-600 flex-1">
                        <li class="flex items-start"><span class="text-indigo-500 mr-2">✔</span> <strong>10
                                Adet</strong> İlan Hakkı</li>
                        <li class="flex items-start"><span class="text-indigo-500 mr-2">✔</span> <strong>100
                                Fotoğraf</strong>/İlan</li>
                        <li class="flex items-start"><span class="text-indigo-500 mr-2">✔</span> Panel Erişimi
                        </li>
                        <li class="flex items-start"><span class="text-indigo-500 mr-2">✔</span> Güvenli Filigran</li>
                        <li class="flex items-start"><span class="text-indigo-500 mr-2">✔</span> WhatsApp Desteği</li>
                    </ul>

                    <a href="payment/pay.php?package=standard"
                        class="block w-full py-4 px-6 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition text-center shadow-lg hover:shadow-xl">
                        Paket Satın Al
                    </a>
                </div>

                <!-- PREMIUM PACKAGE -->
                <div
                    class="bg-white rounded-2xl shadow-2xl border-2 border-yellow-400 p-8 flex flex-col relative overflow-hidden transform md:scale-105 z-10">
                    <div class="absolute top-0 inset-x-0 h-2 bg-gradient-to-r from-yellow-400 to-orange-500"></div>
                    <div class="absolute top-4 right-4 animate-pulse">
                        <span
                            class="bg-yellow-100 text-yellow-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase ring-2 ring-yellow-400">En
                            Popüler</span>
                    </div>

                    <h3 class="text-xl font-bold text-yellow-600 uppercase tracking-wide mb-2">Premium</h3>
                    <div class="text-6xl font-extrabold text-gray-900 mb-2">
                        <?php echo number_format($GLOBALS['PACKAGES']['premium']['price'], 0); ?><span
                            class="text-2xl font-normal text-gray-500">TL</span>
                    </div>
                    <p class="text-gray-500 mb-6 font-medium">Kurumsal ofisler ve yoğun kullanım için.</p>

                    <div class="bg-yellow-50 rounded-lg p-4 mb-6 text-center border border-yellow-200">
                        <span class="block text-xs text-yellow-600 uppercase font-bold">Paket Kullanım Süresi</span>
                        <span class="block text-3xl font-black text-gray-900">90 GÜN (3 AY)</span>
                    </div>

                    <ul class="space-y-4 mb-8 text-left text-gray-700 flex-1">
                        <li class="flex items-start"><span
                                class="bg-yellow-400 text-white rounded-full p-1 mr-2 text-xs flex-shrink-0">✔</span>
                            <strong>50 Adet</strong> İlan Hakkı
                        </li>
                        <li class="flex items-start"><span
                                class="bg-yellow-400 text-white rounded-full p-1 mr-2 text-xs flex-shrink-0">✔</span>
                            <strong>500 Fotoğraf</strong>/İlan
                        </li>
                        <li class="flex items-start"><span
                                class="bg-yellow-400 text-white rounded-full p-1 mr-2 text-xs flex-shrink-0">✔</span>
                            Panel Erişimi</li>
                        <li class="flex items-start"><span
                                class="bg-yellow-400 text-white rounded-full p-1 mr-2 text-xs flex-shrink-0">✔</span>
                            Güvenli Filigran</li>
                        <li class="flex items-start"><span
                                class="bg-yellow-400 text-white rounded-full p-1 mr-2 text-xs flex-shrink-0">✔</span>
                            WhatsApp Desteği</li>
                    </ul>

                    <a href="payment/pay.php?package=premium"
                        class="block w-full py-4 px-6 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white font-bold rounded-xl transition text-center shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
                        Hemen Yükselt
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-auto">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 text-center text-sm">
            <p>&copy; <?php echo date('Y'); ?> ilangoster.com - Tüm hakları saklıdır. | Güvenli Resim Paylaşım Platformu
                | Teknik Destek için : 0542 340 89 43'i arayabilirsiniz.</p>
        </div>
    </footer>

    <script>
        const phoneInput = document.getElementById('agent_phone');
        const submitBtn = document.getElementById('btn-submit');
        const alertBox = document.getElementById('limit-alert');
        const alertMsg = document.getElementById('limit-message');

        phoneInput.addEventListener('keyup', function () {
            let phone = this.value.replace(/[^0-9]/g, '');
            if (phone.startsWith('0')) { phone = phone.substring(1); }

            if (phone.length === 10) {
                // Limit kontrol isteği
                const formData = new FormData();
                formData.append('action', 'check_limit');
                formData.append('phone', phone);

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'limit_reached') {
                            submitBtn.disabled = true;
                            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            submitBtn.innerText = 'Limit Doldu';

                            alertMsg.innerText = data.message;
                            alertBox.classList.remove('hidden');
                        } else {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                            submitBtn.innerText = 'Yükle ve Güvenli Linki Oluştur';

                            alertBox.classList.add('hidden');
                        }
                    })
                    .catch(err => console.error(err));
            }
        });

        // Upload Progress Animation
        const form = document.querySelector('form');
        const overlay = document.getElementById('loading-overlay');
        const progressBar = document.getElementById('progress-bar');
        const statusText = document.getElementById('upload-status');

        form.addEventListener('submit', function (e) {
            // Basit HTML5 validasyon kontrolü (tarayıcı zaten engeller ama garanti olsun)
            if (!this.checkValidity()) return;

            // Overlay'i göster
            overlay.classList.add('active');

            // Simüle edilmiş progress (Dosya yüklenirken kullanıcının içi ferah olsun)
            let width = 0;
            const interval = setInterval(() => {
                if (width >= 90) {
                    // 90'da dur, sunucu yanıt verene kadar
                    clearInterval(interval);
                } else {
                    // Logaritmik yavaşlama
                    let increment = Math.max(0.5, (90 - width) / 10);
                    width += increment;

                    if (width > 90) width = 90;

                    progressBar.style.width = width + '%';
                    statusText.innerText = '%' + Math.floor(width);
                }
            }, 150); // Her 150ms'de bir güncelle
        });
    </script>
</body>

</html>