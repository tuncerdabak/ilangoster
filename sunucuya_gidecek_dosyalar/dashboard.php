<?php
require_once 'db.php';
session_start();

// --- Güvenlik: Giriş Kontrolü ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Şimdilik sadece normal kullanıcılar için dashboard (Admin de user olabilir ama admin paneli var)
// Admin dashboard'a girerse ne görsün? Normal user paneli görebilir.

$user_id = $_SESSION['user_id'];
$user_phone = $_SESSION['user_phone'];

// Kullanıcı Bilgilerini Tazele
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

if (!$user) {
    // Session var ama user yok silinmiş
    session_destroy();
    header('Location: login.php');
    exit;
}

$package_key = $user['package_name'];
$package_info = $GLOBALS['PACKAGES'][$package_key] ?? $GLOBALS['PACKAGES']['free'];
$is_expired = $user['active_until'] && strtotime($user['active_until']) < time();

// --- Galerileri Çek ---
// Silme İşlemi
if (isset($_POST['delete_gallery']) && isset($_POST['gallery_id'])) {
    $gid = $_POST['gallery_id'];
    // Kendi galerisi mi kontrol et
    $stmt_check = $pdo->prepare("SELECT id FROM galleries WHERE id = ? AND user_id = ?");
    $stmt_check->execute([$gid, $user_id]);
    if ($stmt_check->fetch()) {
        // Resimleri sil (Disk)
        $stmt_paths = $pdo->prepare("SELECT image_path FROM images WHERE gallery_id = ?");
        $stmt_paths->execute([$gid]);
        foreach ($stmt_paths->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $full = realpath(__DIR__ . '/' . $path);
            if ($full && file_exists($full))
                unlink($full);
        }
        // DB sil
        $pdo->prepare("DELETE FROM galleries WHERE id = ?")->execute([$gid]);
        $msg = "Galeri başarıyla silindi.";
    }
}

$stmt_galleries = $pdo->prepare("SELECT * FROM galleries WHERE user_id = ? ORDER BY id DESC");
$stmt_galleries->execute([$user_id]);
$galleries = $stmt_galleries->fetchAll();
$gallery_count = count($galleries);

// Toplam Kullanılan Fotoğraf Sayısını Hesapla
$stmt_photo_usage = $pdo->prepare("SELECT SUM(photo_count) FROM galleries WHERE user_id = ?");
$stmt_photo_usage->execute([$user_id]);
$total_photos_used = $stmt_photo_usage->fetchColumn() ?: 0;

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - İlanGöster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .safe-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }

        @media (max-width: 480px) {
            .xs-hidden {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen font-sans">

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center py-4 sm:h-16 gap-4 sm:gap-0">
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0 flex items-center">
                        <img class="h-8 w-auto" src="logo.png" alt="İlanGöster">
                        <span class="ml-2 font-bold text-gray-800">İlanlarım</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">
                        <span class="hidden xs:inline">Hoş geldin, </span>
                        <strong><?= htmlspecialchars($user_phone) ?></strong></span>
                    <a href="admin.php?logout=true"
                        class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1 rounded text-sm font-bold border border-red-200 transition">Çıkış</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">

        <?php if (isset($msg)): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-6"><?= $msg ?></div>
        <?php endif; ?>

        <!-- Paket Bilgisi -->
        <div class="bg-white overflow-hidden shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Mevcut Üyelik Durumu</h3>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div
                        class="bg-white p-5 rounded-2xl shadow-sm border border-indigo-100 flex flex-col justify-between">
                        <div>
                            <dt class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">Mevcut Paket
                            </dt>
                            <dd class="text-2xl font-black text-indigo-900">
                                <?= ($package_key == 'free') ? 'Kısıtlı Ücretsiz' : $package_info['name_tr'] ?>
                            </dd>
                        </div>
                        <?php if ($package_key == 'free' || $is_expired): ?>
                            <div class="mt-4">
                                <a href="index.php#packages"
                                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-bold rounded-xl text-white bg-gradient-to-r from-orange-500 to-yellow-500 hover:from-orange-600 hover:to-yellow-600 shadow-lg transform transition hover:-translate-y-0.5 active:scale-95">
                                    ⭐ PAKET YÜKSELT
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-indigo-100">
                        <dt class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Kullanım Hakları
                        </dt>

                        <!-- Galeri Limiti -->
                        <div class="mb-4">
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-sm font-bold text-gray-700">İlan (Galeri)</span>
                                <span class="text-xs font-medium text-gray-500"><?= $gallery_count ?> /
                                    <?= $package_info['gallery_limit'] ?></span>
                            </div>
                            <?php $gallery_pct = min(100, ($gallery_count / $package_info['gallery_limit']) * 100); ?>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500"
                                    style="width: <?= $gallery_pct ?>%"></div>
                            </div>
                        </div>

                        <!-- Foto Limiti -->
                        <div>
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-sm font-bold text-gray-700">Fotoğraf</span>
                                <span class="text-xs font-medium text-gray-500"><?= $total_photos_used ?> /
                                    <?= $package_info['photo_limit'] ?></span>
                            </div>
                            <?php $photo_pct = min(100, ($total_photos_used / $package_info['photo_limit']) * 100); ?>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-500"
                                    style="width: <?= $photo_pct ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-indigo-100">
                        <dt class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">Geçerlilik Tarihi
                        </dt>
                        <dd class="mt-1">
                            <?php if ($package_key == 'free'): ?>
                                <span class="text-sm font-bold text-gray-400">SÜRESİZ</span>
                                <p class="text-[10px] text-gray-400 mt-1 uppercase">Kısıtlı Ücretsiz Mod</p>
                            <?php else: ?>
                                <span
                                    class="text-xl font-black text-indigo-900"><?= date('d.m.Y', strtotime($user['active_until'])) ?></span>
                                <?php if ($is_expired): ?>
                                    <div class="mt-1 flex items-center gap-1 text-red-600">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs font-bold uppercase">Süresi Dolmuş</span>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-1 flex items-center gap-1 text-green-600">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs font-bold uppercase">Aktif</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                </div>
            </div>
        </div>

        <!-- Galeriler -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Galerilerim</h3>
                <a href="dashboard_new.php"
                    class="bg-green-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-green-700 transition">
                    + Yeni İlan Yükle
                </a>
            </div>

            <?php if (empty($galleries)): ?>
                <div class="p-10 text-center text-gray-500">
                    Henüz hiç galeri oluşturmadınız.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($galleries as $g):
                        $g_expired = $g['is_expired'] || strtotime($g['expire_at']) < time();
                        ?>
                        <li class="px-4 py-4 sm:px-6 hover:bg-gray-50 transition border-b border-gray-100 last:border-0">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-sm font-medium text-indigo-600 truncate">
                                            Galeri #<?= $g['id'] ?>
                                            <span class="ml-2 text-xs text-gray-400 font-mono"><?= $g['unique_token'] ?></span>
                                        </p>
                                        <div class="ml-2 flex-shrink-0 flex">
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $g_expired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                                <?= $g_expired ? 'Süresi Doldu' : 'Aktif' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="sm:flex sm:justify-between">
                                        <div class="sm:flex">
                                            <p class="flex items-center text-sm text-gray-500 mr-6">
                                                <span>📷 <?= $g['photo_count'] ?> Fotoğraf</span>
                                            </p>
                                            <p class="flex items-center text-sm text-gray-500">
                                                <span>⏳ Bitiş: <?= date('d.m.Y H:i', strtotime($g['expire_at'])) ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </div> <!-- End flex-1 min-w-0 -->
                                <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap">
                                    <button onclick="openShareModal(<?= $g['id'] ?>, '<?= $g['unique_token'] ?>')"
                                        class="flex-grow sm:flex-grow-0 bg-green-600 text-white hover:bg-green-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center gap-2 transition shadow-lg transform active:scale-95">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                        </svg>
                                        PAYLAŞ
                                    </button>
                                    <a href="g/<?= $g['unique_token'] ?>" target="_blank"
                                        class="flex-grow sm:flex-grow-0 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center transition border border-indigo-100 italic">
                                        GİZLİ LİNK
                                    </a>
                                    <form method="POST"
                                        onsubmit="return confirm('Bu galeriyi silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="gallery_id" value="<?= $g['id'] ?>">
                                        <button type="submit" name="delete_gallery"
                                            class="w-full bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg text-xs font-bold border border-red-100 transition uppercase">
                                            SİL
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>

    <!-- SHARE MODAL -->
    <div id="shareModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden opacity-0 transition-opacity duration-300">
        <div
            class="bg-white rounded-lg p-8 max-w-sm w-full shadow-2xl transform transition-all scale-95 duration-300 relative">
            <button onclick="closeShareModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>

            <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">İlanı Paylaş</h3>

            <div class="space-y-6">
                <input type="hidden" id="modalGalleryId">
                <input type="hidden" id="modalUniqueToken">

                <!-- OPTION 1: QUICK SHARE -->
                <div class="p-1">
                    <button onclick="submitShare(true)" id="btnQuickShare"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-xl flex justify-center items-center gap-2 transition shadow-lg transform active:scale-95">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                        </svg>
                        WhatsApp'ta Paylaş
                    </button>
                    <p class="text-[10px] text-gray-400 mt-2 text-center uppercase tracking-widest font-bold">REHBERDEN
                        KİŞİ SEÇİN</p>
                </div>

                <div class="relative flex py-2 items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="flex-shrink mx-4 text-gray-400 text-xs font-bold uppercase">veya</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>

                <!-- OPTION 2: SPECIFIC NUMBER -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <p class="text-xs text-gray-500 mb-3 text-center">Numara yazarak doğrudan gönderin</p>
                    <div class="mb-3">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Müşteri
                            Numarası</label>
                        <input type="tel" id="modalCustomerPhone" placeholder="5XXXXXXXXX" maxlength="10"
                            class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-green-500 focus:border-green-500 text-sm font-bold">
                    </div>

                    <button onclick="submitShare(false)" id="btnShareSubmit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg flex justify-center items-center gap-2 transition active:scale-95">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                        </svg>
                        Numaraya Gönder
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('shareModal');
        const modalContent = modal.querySelector('div');
        const modalGalleryId = document.getElementById('modalGalleryId');
        const modalUniqueToken = document.getElementById('modalUniqueToken');
        const modalPhone = document.getElementById('modalCustomerPhone');

        function openShareModal(galleryId, token) {
            modalGalleryId.value = galleryId;
            modalUniqueToken.value = token;
            modalPhone.value = ''; // Reset phone
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }

        function closeShareModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function submitShare(isQuick) {
            const id = modalGalleryId.value;
            const token = modalUniqueToken.value;
            const phone = modalPhone.value;
            const btn = isQuick ? document.getElementById('btnQuickShare') : document.getElementById('btnShareSubmit');

            if (isQuick) {
                // QUICK SHARE: Just open WA with the message, let user pick contact
                const text = `Merhaba, ilan fotoğraflarına bu linkten bakabilirsiniz: https://ilangoster.com/g/${token}`;
                window.location.href = `https://wa.me/?text=${encodeURIComponent(text)}`;
                closeShareModal();
                return;
            }

            // SPECIFIC NUMBER: Original logic
            if (phone.length !== 10) {
                alert('Lütfen 10 haneli (örn: 5xxxxxxxxx) bir numara giriniz.');
                return;
            }

            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Hazırlanıyor...';

            const formData = new FormData();
            formData.append('gallery_id', id);
            formData.append('customer_phone', phone);

            fetch('share_ajax.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = data.whatsapp_url;
                        closeShareModal();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                })
                .catch(err => {
                    console.error(err);
                    alert('Bir hata oluştu.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }
    </script>
</body>

</html>