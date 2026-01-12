<?php
require_once 'db.php';
session_start();

function redirect_with_error($message)
{
    $_SESSION['message'] = ['type' => 'error', 'text' => $message];
    header('Location: index.php');
    exit;
}

function process_and_watermark_image($temp_path, $target_path, $watermark_text, $user_id, $custom_logo_path = null)
{
    if (!extension_loaded('imagick')) {
        return false;
    }

    try {
        $img = new Imagick($temp_path);
        $img->stripImage();

        if ($img->getImageWidth() > 1600) {
            $img->resizeImage(1600, 0, Imagick::FILTER_LANCZOS, 1);
        }

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();

        if ($custom_logo_path && file_exists($custom_logo_path)) {
            // --- LOGO FİLİGRAN (Premium) ---
            $watermark = new Imagick($custom_logo_path);

            // Logoyu makul bir boyuta getir (Örn: Genişliğin %15'i)
            $logoW = $w * 0.15;
            $watermark->resizeImage($logoW, 0, Imagick::FILTER_LANCZOS, 1);
            $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, 0.4, Imagick::CHANNEL_ALPHA); // Şeffaflık

            // Logoyu 45 derece döndür ve tekrarla
            $watermark->rotateImage(new ImagickPixel('none'), 45);

            $spacing = 400;
            for ($x = 0; $x < $w + 500; $x += $spacing) {
                for ($y = 0; $y < $h + 500; $y += $spacing) {
                    $img->compositeImage($watermark, Imagick::COMPOSITE_OVER, $x - 200, $y - 200);
                }
            }
            $watermark->clear();
        } else {
            // --- METİN FİLİGRAN (Klasik) ---
            $draw = new ImagickDraw();
            $draw->setFontSize(32);
            $draw->setFillColor('white');
            $draw->setStrokeAntialias(true);
            $draw->setTextAntialias(true);
            $draw->setStrokeOpacity(0.15);
            $draw->setStrokeColor('white');
            $draw->setStrokeWidth(1);
            $draw->rotate(-45);

            $spacing = 500;
            for ($i = 0; $i < ($w + $h) / cos(deg2rad(45)); $i += $spacing) {
                for ($j = 0; $j < $w + $h; $j += $spacing) {
                    $img->annotateImage($draw, $i - $w / 2, $j - $h / 2, 45, $watermark_text);
                }
            }
        }

        // --- 4. Kaydetme ---
        $img->setImageFormat('jpeg');
        $img->setImageCompression(Imagick::COMPRESSION_JPEG);
        $img->setImageCompressionQuality(85);

        $img->writeImage($target_path);
        $img->clear();
        return true;

    } catch (ImagickException $e) {
        // Imagick hatası durumunda (ör: dosya bozuk)
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error("Geçersiz istek metodu.");
}

$agent_phone = trim($_POST['agent_phone'] ?? '');

// EĞER DASHBOARD KAYNAKLIYSA VE SESSION VARSA, SESSION TELEFONUNU KULLAN
if (isset($_POST['source']) && $_POST['source'] === 'dashboard' && isset($_SESSION['user_phone'])) {
    $agent_phone = $_SESSION['user_phone'];
}

if (empty($agent_phone)) {
    redirect_with_error("Telefon numarası gerekli.");
}

$agent_phone = preg_replace('/[^0-9]/', '', $agent_phone); // Sadece rakamları al
$new_photo_count = count($_FILES['photos']['tmp_name']);

if ($new_photo_count == 0) {
    redirect_with_error("Lütfen en az bir fotoğraf seçin.");
}

// --- 1. Kullanıcı ve Paket Durumu Kontrolü ---
$stmt = $pdo->prepare("SELECT id, package_name, active_until, gallery_limit, photo_limit FROM users WHERE phone = ?");
$stmt->execute([$agent_phone]);
$user = $stmt->fetch();

$user_id = $user ? $user['id'] : null;
$package_name = $user ? $user['package_name'] : 'free';
$package_config = $GLOBALS['PACKAGES'][$package_name] ?? $GLOBALS['PACKAGES']['free'];

// Süre kontrolü (Ücretli paket süresi dolduysa Free'ye çek)
if ($user && $user['package_name'] !== 'free' && $user['active_until'] && strtotime($user['active_until']) < time()) {
    $package_name = 'free';
    // DB'de paketi düşür
    $pdo->prepare("UPDATE users SET package_name = 'free' WHERE id = ?")->execute([$user_id]);
    $package_config = $GLOBALS['PACKAGES']['free'];
    $_SESSION['message'] = ['type' => 'warning', 'text' => 'Paket süreniz dolduğu için ücretsiz kullanıma geçiş yapıldı.'];
}

// Toplam galeri ve fotoğraf sayısını hesapla
$stmt_counts = $pdo->prepare("SELECT 
    COUNT(id) as current_galleries, 
    SUM(photo_count) as current_photos 
    FROM galleries WHERE agent_phone = ? AND is_expired = 0");
$stmt_counts->execute([$agent_phone]);
$counts = $stmt_counts->fetch();

$current_galleries = $counts['current_galleries'] ?? 0;
$current_photos = $counts['current_photos'] ?? 0;

// --- 2. Limit Kontrolü ---
if ($current_galleries >= $package_config['gallery_limit']) {
    redirect_with_error("Galeri (ilan) limitiniz dolmuştur ({$package_config['gallery_limit']} adet). Lütfen paket yükseltin.");
}
if ($current_photos + $new_photo_count > $package_config['photo_limit']) {
    redirect_with_error("Toplam fotoğraf limitiniz dolmuştur ({$package_config['photo_limit']} adet). Lütfen daha az fotoğraf yükleyin veya paket yükseltin.");
}


// --- 3. Galeri Oluşturma ---
$unique_token = bin2hex(random_bytes(16)); // 32 karakterlik token
$expire_at = date('Y-m-d H:i:s', strtotime("+" . $package_config['duration_days'] . " days"));

$stmt_gallery = $pdo->prepare("INSERT INTO galleries 
    (user_id, agent_phone, unique_token, photo_count, expire_at) 
    VALUES (?, ?, ?, ?, ?)");
$stmt_gallery->execute([$user_id, $agent_phone, $unique_token, $new_photo_count, $expire_at]);
$gallery_id = $pdo->lastInsertId();

$uploaded_count = 0;
foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
    if (!empty($tmp_name) && $_FILES['photos']['error'][$key] == 0) {

        // --- GÜVENLİK GÜNCELLEMESİ ---
        // 1. Dosya boyutu kontrolü (Örn: 5MB)
        if ($_FILES['photos']['size'][$key] > 5 * 1024 * 1024) {
            continue; // Bu dosyayı atla
        }

        // 2. MIME Type Kontrolü (Sadece uzantıya güvenme)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmp_name);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes)) {
            continue; // Geçersiz MIME tipi, atla
        }

        $fileType = strtolower(pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION));

        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'webp'])) {
            $fileName = uniqid('img_') . time() . '.' . $fileType;
            $targetFile = UPLOAD_DIR . $fileName;

            // Filigran ve Logo Ayarları
            $watermark_text = get_setting($pdo, 'watermark_text', WATERMARK_TEXT);
            $custom_logo_path = null;
            if (isset($user['id']) && in_array($user['package_name'], ['premium', 'standard'])) {
                if ($user['custom_logo'] && file_exists(UPLOAD_DIR . $user['custom_logo'])) {
                    $custom_logo_path = UPLOAD_DIR . $user['custom_logo'];
                }
            }

            // Imagick ile işlemi gerçekleştir
            if (process_and_watermark_image($tmp_name, $targetFile, $watermark_text, $user_id ?? $agent_phone, $custom_logo_path)) {

                // Başarılıysa DB'ye kaydet
                $stmt_image = $pdo->prepare("INSERT INTO images (gallery_id, image_path) VALUES (?, ?)");
                $stmt_image->execute([$gallery_id, 'uploads/' . $fileName]); // DB'ye path'i göreceli kaydet
                $uploaded_count++;
            }
        }
    }
}

// Eğer hiç resim yüklenmediyse galeriyi sil ve hata ver
if ($uploaded_count == 0) {
    $pdo->prepare("DELETE FROM galleries WHERE id = ?")->execute([$gallery_id]);
    redirect_with_error("Yüklediğiniz hiçbir dosya geçerli resim formatında değildi veya Imagick hatası oluştu.");
}

// Başarı mesajı ve yönlendirme
// Başarı mesajı
$_SESSION['message'] = [
    'type' => 'success',
    'text' => "Yükleme başarılı! ({$uploaded_count} fotoğraf yüklendi). Şimdi müşteri numarasını girip linki paylaşabilirsiniz."
];
$_SESSION['temp_gallery_id'] = $gallery_id;

// Yönlendirme Kontrolü
if (isset($_POST['source']) && $_POST['source'] === 'dashboard') {
    // Dashboard'a geri dön
    header('Location: dashboard.php?success=1');
} else {
    // Normal akış (Müşteri numarası alma sayfasına)
    header('Location: share_customer.php');
}
exit;