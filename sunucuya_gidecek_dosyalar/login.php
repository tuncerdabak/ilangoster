<?php
require_once 'db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_phone'])) {
    $redirect_url = isset($_GET['package']) ? 'payment/pay.php?package=' . $_GET['package'] : 'dashboard.php';
    header("Location: $redirect_url");
    exit;
}

$error_msg = '';
$success_msg = '';
$active_tab = 'login'; // 'login' or 'register'

// Check for package parameter to redirect after auth
$package_param = isset($_GET['package']) ? '?package=' . htmlspecialchars($_GET['package']) : '';
$post_package = isset($_POST['package']) ? '?package=' . htmlspecialchars($_POST['package']) : '';
$final_package_url_suffix = $package_param ?: $post_package;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone_clean, '0')) {
        $phone_clean = substr($phone_clean, 1);
    }

    if (empty($phone_clean) || empty($password)) {
        $error_msg = "Lütfen telefon ve şifre giriniz.";
        $active_tab = $action;
    } else {
        if ($action === 'register') {
            // --- REGISTRATION LOGIC ---
            $active_tab = 'register';

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone_clean]);
            if ($stmt->fetch()) {
                $error_msg = "Bu telefon numarası zaten kayıtlı. Lütfen giriş yapın.";
            } else {
                // Register new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $pdo->prepare("INSERT INTO users (phone, password, package_name) VALUES (?, ?, 'free')");
                if ($stmt_insert->execute([$phone_clean, $hashed_password])) {
                    // Auto Login
                    $user_id = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_phone'] = $phone_clean;
                    $_SESSION['is_admin'] = false;

                    // Redirect
                    $redirect_target = $package_param ? 'payment/pay.php' . $package_param : 'dashboard.php';
                    header("Location: $redirect_target");
                    exit;
                } else {
                    $error_msg = "Kayıt sırasında bir hata oluştu.";
                }
            }

        } else {
            // --- LOGIN LOGIC ---
            $stmt = $pdo->prepare("SELECT id, password, is_admin, phone FROM users WHERE phone = ?");
            $stmt->execute([$phone_clean]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];

                if ($user['is_admin']) {
                    $_SESSION['admin_phone'] = $user['phone']; // Legacy
                    header('Location: admin.php');
                } else {
                    $redirect_target = $package_param ? 'payment/pay.php' . $package_param : 'dashboard.php';
                    header("Location: $redirect_target");
                }
                exit;
            } else {
                $error_msg = "Hatalı telefon numarası veya şifre.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap / Kayıt Ol - İlanGöster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f9;
        }
    </style>
    <script>
        function switchTab(tab) {
            document.getElementById('form-action').value = tab;
            if (tab === 'register') {
                document.getElementById('title-text').innerText = 'Hesap Oluşturun';
                document.getElementById('submit-btn').innerText = 'Kayıt Ol ve Devam Et';
                document.getElementById('tab-register').classList.add('border-indigo-600', 'text-indigo-600');
                document.getElementById('tab-login').classList.remove('border-indigo-600', 'text-indigo-600');
                document.getElementById('tab-login').classList.add('border-transparent', 'text-gray-500');
            } else {
                document.getElementById('title-text').innerText = 'Hesabınıza Giriş Yapın';
                document.getElementById('submit-btn').innerText = 'Giriş Yap';
                document.getElementById('tab-login').classList.add('border-indigo-600', 'text-indigo-600');
                document.getElementById('tab-register').classList.remove('border-indigo-600', 'text-indigo-600');
                document.getElementById('tab-register').classList.add('border-transparent', 'text-gray-500');
            }
        }
    </script>
</head>

<body class="min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-6">
            <a href="index.php" class="inline-block mb-4">
                <img src="logo.png" alt="İlanGöster" class="h-24 w-auto mx-auto">
            </a>
            <h2 id="title-text" class="text-2xl font-bold text-gray-800">
                <?= $active_tab == 'register' ? 'Hesap Oluşturun' : 'Hesabınıza Giriş Yapın' ?>
            </h2>
            <?php if (isset($_GET['package'])): ?>
                <p class="text-green-600 text-sm mt-2 font-semibold">Paket satın almak için lütfen giriş yapın veya kayıt
                    olun.</p>
            <?php endif; ?>
        </div>

        <!-- TABS -->
        <div class="flex border-b border-gray-200 mb-6">
            <button id="tab-login" onclick="switchTab('login')"
                class="w-1/2 py-2 text-center text-sm font-medium border-b-2 transition-colors <?= $active_tab == 'login' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                Giriş Yap
            </button>
            <button id="tab-register" onclick="switchTab('register')"
                class="w-1/2 py-2 text-center text-sm font-medium border-b-2 transition-colors <?= $active_tab == 'register' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                Kayıt Ol
            </button>
        </div>

        <?php if ($error_msg): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 text-sm border-l-4 border-red-500">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" id="form-action" value="<?= $active_tab ?>">
            <?php if (isset($_GET['package'])): ?>
                <input type="hidden" name="package" value="<?= htmlspecialchars($_GET['package']) ?>">
            <?php endif; ?>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefon Numarası</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">📞</span>
                    <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($phone ?? '') ?>" required
                        placeholder="05XXXXXXXXX"
                        class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition shadow-sm">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Şifre</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">🔒</span>
                    <input type="password" name="password" id="password" required placeholder="********"
                        class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition shadow-sm">
                </div>
            </div>

            <button type="submit" id="submit-btn"
                class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition duration-200 shadow-md">
                <?= $active_tab == 'register' ? 'Kayıt Ol ve Devam Et' : 'Giriş Yap' ?>
            </button>
        </form>
    </div>

</body>

</html>