<?php
require_once 'db.php';
session_start();

// --- 1. Login/Logout ---
$is_admin = $_SESSION['is_admin'] ?? false;
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php'); // Redirect to central login
    exit;
}

// Admin Değilse Direkt Login'e At
if (!$is_admin) {
    header('Location: login.php');
    exit;
}

// --- 2. Admin İşlemleri ---

// Galeri Silme (Manuel)
if (isset($_POST['delete_gallery']) && isset($_POST['gallery_id'])) {
    $gid = $_POST['gallery_id'];
    $stmt_paths = $pdo->prepare("SELECT image_path FROM images WHERE gallery_id = ?");
    $stmt_paths->execute([$gid]);
    foreach ($stmt_paths->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $full = realpath(__DIR__ . '/' . $path);
        if ($full && file_exists($full))
            unlink($full);
    }
    $pdo->prepare("DELETE FROM galleries WHERE id = ?")->execute([$gid]);
    $msg = "Galeri #$gid silindi.";
}

// Kullanıcı Düzenleme (Paket/Süre)
if (isset($_POST['update_user'])) {
    $uid = $_POST['user_id'];
    $pkg = $_POST['package_name'];
    $valid_until = $_POST['active_until'] ?: NULL;
    
    // Şifre Güncelleme Kontrolü
    if (!empty($_POST['new_password'])) {
        $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET package_name = ?, active_until = ?, password = ? WHERE id = ?")->execute([$pkg, $valid_until, $hashed, $uid]);
        $msg = "Kullanıcı #$uid güncellendi (Şifre değiştirildi).";
    } else {
        $pdo->prepare("UPDATE users SET package_name = ?, active_until = ? WHERE id = ?")->execute([$pkg, $valid_until, $uid]);
        $msg = "Kullanıcı #$uid güncellendi.";
    }
}

// Watermark Güncelleme (Basitçe config dosyası yerine DB tercih edilir ama şimdilik örnek)
// Ayarları Kaydet
if (isset($_POST['save_settings'])) {
    $settings_to_save = [
        'watermark_text' => $_POST['watermark_text'],
        'shopier_api_key' => $_POST['shopier_api_key'],
        'shopier_secret' => $_POST['shopier_secret'],
        'price_standard' => $_POST['price_standard'], // Legacy override check
        'price_premium' => $_POST['price_premium'],   // Legacy override check

        // Yeni Dinamik Ayarlar
        'pkg_free_limit_gallery' => $_POST['pkg_free_limit_gallery'],
        'pkg_free_limit_photo' => $_POST['pkg_free_limit_photo'],
        'pkg_free_days' => $_POST['pkg_free_days'],

        'pkg_standard_price' => $_POST['pkg_standard_price'],
        'pkg_standard_days' => $_POST['pkg_standard_days'],
        'pkg_standard_limit_gallery' => $_POST['pkg_standard_limit_gallery'],
        'pkg_standard_limit_photo' => $_POST['pkg_standard_limit_photo'],

        'pkg_premium_price' => $_POST['pkg_premium_price'],
        'pkg_premium_days' => $_POST['pkg_premium_days'],
        'pkg_premium_limit_gallery' => $_POST['pkg_premium_limit_gallery'],
        'pkg_premium_limit_photo' => $_POST['pkg_premium_limit_photo'],
    ];

    foreach ($settings_to_save as $key => $val) {
        // Varsa güncelle, yoksa ekle (Upsert mantığı mysql'de ON DUPLICATE KEY UPDATE ile veya önce kontrolle)
        // Basitçe:
        $stmt_check = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
        $stmt_check->execute([$key]);
        if ($stmt_check->fetch()) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $key]);
        } else {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
        }
    }
    $msg = "Ayarlar başarıyla güncellendi.";
}

// --- Veri Çekme ---
$tab = $_GET['tab'] ?? 'dashboard';

// Dashboard Stats
$disk_formatted = '0 B';
if (file_exists(UPLOAD_DIR)) {
    $size = 0;
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $disk_formatted = number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    } catch (Exception $e) {
        $disk_formatted = 'N/A';
    }
}

// Dashboard Stats
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'galleries' => $pdo->query("SELECT COUNT(*) FROM galleries")->fetchColumn(),
    'active_galleries' => $pdo->query("SELECT COUNT(*) FROM galleries WHERE is_expired=0")->fetchColumn(),
    'disk' => $disk_formatted
];

// --- Pagination Helper ---
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Search Query
$search = $_GET['search'] ?? '';

// Users (Pagination + Search)
$users = [];
$total_users = 0;
if ($tab === 'users') {
    $where = "";
    $params = [];
    if ($search) {
        $where = "WHERE phone LIKE ?";
        $params[] = "%$search%";
    }
    $total_users = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $total_users->execute($params);
    $total_users = $total_users->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $total_pages = ceil($total_users / $per_page);
}

// Galleries (Pagination + Search)
$galleries = [];
$total_galleries = 0;
if ($tab === 'galleries') {
    $where = "";
    $params = [];
    if ($search) {
        $where = "WHERE agent_phone LIKE ?";
        $params[] = "%$search%";
    }
    $total_galleries = $pdo->prepare("SELECT COUNT(*) FROM galleries $where");
    $total_galleries->execute($params);
    $total_galleries = $total_galleries->fetchColumn();

    $stmt = $pdo->prepare("SELECT g.*, u.phone as u_phone FROM galleries g LEFT JOIN users u ON g.user_id = u.id $where ORDER BY g.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $galleries = $stmt->fetchAll();

    $total_pages = ceil($total_galleries / $per_page);
}

// Payments (Pagination)
$payments = [];
if ($tab === 'payments') {
    $stmt = $pdo->prepare("SELECT p.*, u.phone FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute();
    $payments = $stmt->fetchAll();
}

// ... (rest of the file content for view logic)
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - İlan Göster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function openTab(name) { window.location.href = '?tab=' + name; }
    </script>
</head>

<body class="bg-gray-100 min-h-screen font-sans">
    <!-- Navbar (Same as before) -->
    <nav class="bg-gray-800 text-white p-4 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-2">
                <img src="logo.png" alt="İlanGöster" class="h-8 w-auto">
                <span class="text-xl font-bold tracking-wider text-white">İlanGöster <span
                        class="text-green-400">Admin</span></span>
            </a>
            <div class="space-x-4">
                <span class="text-gray-300 text-sm">Giriş: <?= $_SESSION['admin_phone'] ?></span>
                <a href="?logout=true"
                    class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm transition">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if (isset($msg) || isset($_SESSION['admin_message'])):
            $m = $msg ?? $_SESSION['admin_message'];
            unset($_SESSION['admin_message']);
            ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $m ?></div>
        <?php endif; ?>

        <!-- Tab Menü -->
        <div class="flex space-x-2 mb-6 border-b border-gray-300 pb-2 overflow-x-auto">
            <button onclick="openTab('dashboard')"
                class="px-4 py-2 font-medium <?= $tab == 'dashboard' ? 'bg-indigo-600 text-white rounded shadow' : 'text-gray-600 hover:text-indigo-600' ?>">Dashboard</button>
            <button onclick="openTab('users')"
                class="px-4 py-2 font-medium <?= $tab == 'users' ? 'bg-indigo-600 text-white rounded shadow' : 'text-gray-600 hover:text-indigo-600' ?>">Kullanıcılar</button>
            <button onclick="openTab('galleries')"
                class="px-4 py-2 font-medium <?= $tab == 'galleries' ? 'bg-indigo-600 text-white rounded shadow' : 'text-gray-600 hover:text-indigo-600' ?>">Galeriler</button>
            <button onclick="openTab('payments')"
                class="px-4 py-2 font-medium <?= $tab == 'payments' ? 'bg-indigo-600 text-white rounded shadow' : 'text-gray-600 hover:text-indigo-600' ?>">Ödemeler</button>
            <button onclick="openTab('maintenance')"
                class="px-4 py-2 font-medium <?= $tab == 'maintenance' ? 'bg-yellow-600 text-white rounded shadow' : 'text-gray-600 hover:text-indigo-600' ?>">Bakım
                & Yedek</button>
            <button onclick="openTab('settings')"
                class="px-4 py-2 font-medium <?= $tab == 'settings' ? 'bg-indigo-600 text-white rounded shadow' : 'text-gray-600 hover:text-indigo-600' ?>">Ayarlar</button>
        </div>

        <!-- Dashboard (Existing) -->
        <?php if ($tab === 'dashboard'): ?>
            <!-- ... (Keep existing dashboard cards) ... -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- (Existing Stats Logic) -->
                <div class="bg-white p-6 rounded-xl shadow border-l-4 border-blue-500">
                    <div class="text-gray-500 px-1">Toplam Kullanıcı</div>
                    <div class="text-3xl font-bold text-gray-800"><?= $stats['users'] ?></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow border-l-4 border-green-500">
                    <div class="text-gray-500 px-1">Toplam Galeri</div>
                    <div class="text-3xl font-bold text-gray-800"><?= $stats['galleries'] ?></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow border-l-4 border-yellow-500">
                    <div class="text-gray-500 px-1">Aktif Galeri</div>
                    <div class="text-3xl font-bold text-gray-800"><?= $stats['active_galleries'] ?></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow border-l-4 border-red-500">
                    <div class="text-gray-500 px-1">Disk Kullanımı</div>
                    <div class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($stats['disk']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- USERS TAB (Paginated) -->
        <?php if ($tab === 'users'): ?>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-bold flex justify-between items-center">
                    <span>Kullanıcılar (Toplam: <?= $total_users ?>)</span>
                    <form class="flex gap-2">
                        <input type="hidden" name="tab" value="users">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Telefon Ara..." class="border rounded px-2 py-1 text-sm">
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Ara</button>
                    </form>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefon</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bitiş Tarihi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Yeni Şifre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $u): ?>
                            <form method="POST">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $u['id'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $u['phone'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <select name="package_name" class="border rounded p-1 text-sm bg-gray-50">
                                            <option value="free" <?= $u['package_name'] == 'free' ? 'selected' : '' ?>>Free
                                            </option>
                                            <option value="standard" <?= $u['package_name'] == 'standard' ? 'selected' : '' ?>>
                                                Standard</option>
                                            <option value="premium" <?= $u['package_name'] == 'premium' ? 'selected' : '' ?>>
                                                Premium</option>
                                            <option value="admin" <?= $u['package_name'] == 'admin' ? 'selected' : '' ?>>Admin
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <input type="date" name="active_until"
                                            value="<?= $u['active_until'] ? date('Y-m-d', strtotime($u['active_until'])) : '' ?>"
                                            class="border rounded p-1 text-sm">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <input type="text" name="new_password" placeholder="Değiştir..." class="border rounded p-1 text-sm w-24">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="update_user"
                                            class="text-indigo-600 hover:text-indigo-900">Kaydet</button>
                                    </td>
                                </tr>
                            </form>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Pagination Controls -->
                <div class="p-4 border-t flex justify-center gap-2">
                    <?php if ($page > 1): ?><a href="?tab=users&page=<?= $page - 1 ?>&search=<?= $search ?>"
                            class="px-3 py-1 bg-gray-200 rounded">Önceki</a><?php endif; ?>
                    <span class="px-3 py-1">Sayfa <?= $page ?> / <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?><a href="?tab=users&page=<?= $page + 1 ?>&search=<?= $search ?>"
                            class="px-3 py-1 bg-gray-200 rounded">Sonraki</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- GALLERIES TAB (Paginated) -->
        <?php if ($tab === 'galleries'): ?>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-bold flex justify-between items-center">
                    <span>Galeriler (Toplam: <?= $total_galleries ?>)</span>
                    <form class="flex gap-2">
                        <input type="hidden" name="tab" value="galleries">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Telefon Ara..." class="border rounded px-2 py-1 text-sm">
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Ara</button>
                    </form>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Foto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bitiş</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($galleries as $g):
                            $is_expired = $g['is_expired'] || strtotime($g['expire_at']) < time();
                            ?>
                            <tr class="<?= $is_expired ? 'bg-red-50' : 'bg-green-50' ?> hover:opacity-90">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $g['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?= $g['agent_phone'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?= $g['photo_count'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $is_expired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= $is_expired ? 'Süresi Doldu' : 'Aktif' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d.m.Y', strtotime($g['expire_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <form method="POST" onsubmit="return confirm('Silinsin mi?');">
                                        <input type="hidden" name="gallery_id" value="<?= $g['id'] ?>">
                                        <button type="submit" name="delete_gallery"
                                            class="text-red-600 font-bold hover:text-red-900">Sil</button>
                                        <a href="../g/<?= $g['unique_token'] ?>" target="_blank"
                                            class="text-blue-600 ml-2">Gör</a>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Pagination Controls -->
                <div class="p-4 border-t flex justify-center gap-2">
                    <?php if ($page > 1): ?><a href="?tab=galleries&page=<?= $page - 1 ?>&search=<?= $search ?>"
                            class="px-3 py-1 bg-gray-200 rounded">Önceki</a><?php endif; ?>
                    <span class="px-3 py-1">Sayfa <?= $page ?> / <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?><a href="?tab=galleries&page=<?= $page + 1 ?>&search=<?= $search ?>"
                            class="px-3 py-1 bg-gray-200 rounded">Sonraki</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- MAINTENANCE & BACKUP TAB -->
        <?php if ($tab === 'maintenance'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Data Export -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">📂 Veri Dışa Aktarma (Excel/CSV)</h3>
                    <p class="text-gray-600 mb-4 text-sm">Pazarlama çalışmaları için tüm kayıtlı kullanıcıların telefon
                        numaralarını indirin.</p>
                    <a href="admin_actions.php?action=export_users" target="_blank"
                        class="block w-full bg-green-600 text-white text-center font-bold py-3 rounded hover:bg-green-700 transition">
                        Kullanıcı Listesini İndir (.csv)
                    </a>
                </div>

                <!-- Database Backup -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">💾 Veritabanı Yedeği</h3>
                    <p class="text-gray-600 mb-4 text-sm">Sistemin tam yedeğini (SQL) indirin. Bunu düzenli olarak yapmanız
                        önerilir.</p>
                    <a href="admin_actions.php?action=backup_db" target="_blank"
                        class="block w-full bg-blue-600 text-white text-center font-bold py-3 rounded hover:bg-blue-700 transition">
                        Veritabanı Yedeğini İndir (.sql)
                    </a>
                </div>

                <!-- System Cleanup -->
                <div class="bg-white p-6 rounded-xl shadow md:col-span-2 border-t-4 border-red-500">
                    <h3 class="text-xl font-bold mb-4 text-red-600 flex items-center gap-2">🧹 Sistem Temizliği (Disk
                        Yönetimi)</h3>
                    <p class="text-gray-600 mb-4 text-sm">Disk doluluğunu önlemek için süresi dolmuş galerilerin dosyalarını
                        manuel olarak silin. (Normalde otomatiktir).</p>
                    <div class="flex items-center justify-between bg-red-50 p-4 rounded mb-4">
                        <span class="font-bold text-red-800">Şu anki Disk Kullanımı:
                            <?= htmlspecialchars($stats['disk']) ?></span>
                    </div>
                    <form action="admin_actions.php" method="POST"
                        onsubmit="return confirm('Süresi dolmuş dosyalar kalıcı olarak silinecek. Emin misiniz?');">
                        <input type="hidden" name="action" value="cleanup_files">
                        <button type="submit"
                            class="bg-red-600 text-white font-bold py-3 px-6 rounded hover:bg-red-700 w-full">
                            Süresi Dolmuş Dosyaları Temizle
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- PAYMENTS & SETTINGS Tabs (Keep existing content mostly, just ensure logic flow) -->
        <?php if ($tab === 'payments'): ?>
            <!-- (Existing payments table logic) -->
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-bold">Son Ödemeler</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <!-- ... (Table Headers) ... -->
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kullanıcı (Tel)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sipariş No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $p): ?>
                            <!-- ... (Table Rows) ... -->
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $p['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?= $p['phone'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 text-xs">
                                    <?= $p['order_id'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?= $p['package_name'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold"><?= number_format($p['amount'], 2) ?>
                                    TL</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $p['status'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= $p['status'] ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $p['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'settings'): ?>
            <!-- (Existing Settings Form) -->
            <div class="bg-white p-6 rounded-xl shadow">
                <!-- ... (Keep exisiting form as is) ... -->
                <h3 class="text-xl font-bold mb-4">Sistem Ayarları</h3>
                <form method="POST">
                    <!-- ... fields ... -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Genel Ayarlar -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-gray-700 border-b pb-2">Genel Ayarlar</h4>
                            <div>
                                <label class="block text-gray-700 font-medium mb-1">Watermark Metni</label>
                                <input type="text" name="watermark_text"
                                    value="<?= htmlspecialchars(get_setting($pdo, 'watermark_text', WATERMARK_TEXT)) ?>"
                                    class="w-full border border-gray-300 p-2 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <!-- Shopier Ayarları -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-gray-700 border-b pb-2">Shopier API Ayarları</h4>
                            <div>
                                <label class="block text-gray-700 font-medium mb-1">API Key</label>
                                <input type="text" name="shopier_api_key"
                                    value="<?= htmlspecialchars(get_setting($pdo, 'shopier_api_key', SHOPIER_API_KEY)) ?>"
                                    class="w-full border border-gray-300 p-2 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-1">API Secret</label>
                                <input type="password" name="shopier_secret"
                                    value="<?= htmlspecialchars(get_setting($pdo, 'shopier_secret', SHOPIER_SECRET)) ?>"
                                    class="w-full border border-gray-300 p-2 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <!-- Fiyat Ayarları -->
                        <div class="space-y-4 md:col-span-2">
                            <h4 class="font-bold text-gray-700 border-b pb-2">Paket Ayarları</h4>

                            <!-- FREE -->
                            <div class="bg-gray-50 p-4 rounded border">
                                <h5 class="font-bold text-gray-500 mb-2">Ücretsiz Paket</h5>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-400">Galeri Limiti</label>
                                        <input type="number" name="pkg_free_limit_gallery"
                                            value="<?= get_setting($pdo, 'pkg_free_limit_gallery', 2) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-400">Foto Limiti</label>
                                        <input type="number" name="pkg_free_limit_photo"
                                            value="<?= get_setting($pdo, 'pkg_free_limit_photo', 10) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-400">Süre (Saat/Gün)</label>
                                        <input type="number" name="pkg_free_days"
                                            value="<?= get_setting($pdo, 'pkg_free_days', 1) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                </div>
                            </div>

                            <!-- STANDARD -->
                            <div class="bg-indigo-50 p-4 rounded border border-indigo-100">
                                <h5 class="font-bold text-indigo-600 mb-2">Standart Paket</h5>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-400">Fiyat (TL)</label>
                                        <input type="number" step="0.01" name="pkg_standard_price"
                                            value="<?= get_setting($pdo, 'pkg_standard_price', 50) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-400">Gün</label>
                                        <input type="number" name="pkg_standard_days"
                                            value="<?= get_setting($pdo, 'pkg_standard_days', 30) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-400">Galeri</label>
                                        <input type="number" name="pkg_standard_limit_gallery"
                                            value="<?= get_setting($pdo, 'pkg_standard_limit_gallery', 5) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-400">Foto</label>
                                        <input type="number" name="pkg_standard_limit_photo"
                                            value="<?= get_setting($pdo, 'pkg_standard_limit_photo', 50) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                </div>
                            </div>

                            <!-- PREMIUM -->
                            <div class="bg-yellow-50 p-4 rounded border border-yellow-200">
                                <h5 class="font-bold text-yellow-600 mb-2">Premium Paket</h5>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs font-bold text-yellow-500">Fiyat (TL)</label>
                                        <input type="number" step="0.01" name="pkg_premium_price"
                                            value="<?= get_setting($pdo, 'pkg_premium_price', 250) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-yellow-500">Gün</label>
                                        <input type="number" name="pkg_premium_days"
                                            value="<?= get_setting($pdo, 'pkg_premium_days', 90) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-yellow-500">Galeri</label>
                                        <input type="number" name="pkg_premium_limit_gallery"
                                            value="<?= get_setting($pdo, 'pkg_premium_limit_gallery', 10) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-yellow-500">Foto</label>
                                        <input type="number" name="pkg_premium_limit_photo"
                                            value="<?= get_setting($pdo, 'pkg_premium_limit_photo', 100) ?>"
                                            class="w-full border p-1 rounded">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8">
                        <button type="submit" name="save_settings"
                            class="bg-green-600 text-white font-bold py-3 px-8 rounded hover:bg-green-700 transition shadow-lg">Ayarları
                            Kaydet</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>