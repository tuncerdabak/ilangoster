<?php
require_once 'db.php';
session_start();

// --- Güvenlik: Giriş Kontrolü ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_phone = $_SESSION['user_phone'];

// Kullanıcı Bilgilerini Tazele
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Yeni İlan Yükle - Panelim</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
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

<body class="bg-gray-100 min-h-screen font-sans">

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

    <!-- Navbar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center py-4 sm:h-16 gap-4 sm:gap-0">
                <div class="flex items-center">
                    <a href="dashboard.php" class="flex-shrink-0 flex items-center">
                        <img class="h-8 w-auto" src="logo.png" alt="İlanGöster">
                        <span class="ml-2 font-bold text-gray-800">Panelim</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">Hoş geldin,
                        <strong><?= htmlspecialchars($user_phone) ?></strong></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <a href="dashboard.php" class="text-indigo-600 hover:text-indigo-800 font-bold">&larr; Panele Dön</a>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-lg">
            <h1 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-4">Yeni İlan Fotoğrafları Yükle</h1>

            <form action="upload.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="source" value="dashboard">
                <!-- User Phone is handled by session in upload.php, but let's pass it for consistency check if needed, 
                     though upload.php should prioritize session. We won't show the input. -->

                <!-- Fotoğraflar -->
                <div class="mb-6">
                    <label for="photos" class="block text-left text-sm font-medium text-gray-700 mb-2">
                        Mülk Fotoğrafları (Birden Fazla Seçilebilir)
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:bg-gray-50 transition cursor-pointer"
                        onclick="document.getElementById('photos').click()">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none"
                                viewBox="0 0 48 48" aria-hidden="true">
                                <path
                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <span
                                    class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                    Dosyaları Seçin
                                </span>
                                <p class="pl-1">veya sürükleyip bırakın</p>
                            </div>
                            <p class="text-xs text-gray-500">
                                PNG, JPG, WEBP
                            </p>
                        </div>
                    </div>
                    <input type="file" id="photos" name="photos[]" multiple required
                        accept="image/jpeg, image/png, image/webp" class="hidden">
                    <div id="file-list" class="mt-4 text-sm text-gray-600 space-y-1"></div>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <!-- Heroicon name: solid/information-circle -->
                            <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                İlan fotoğraflarınız yüklendikten sonra doğrudan Dashboard ekranına yönlendirileceksiniz
                                ve oradan paylaşım yapabileceksiniz.
                            </p>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    Yüklemeyi Başlat
                </button>
            </form>
        </div>
    </div>

    <script>
        // File selection feedback
        document.getElementById('photos').addEventListener('change', function (e) {
            const list = document.getElementById('file-list');
            list.innerHTML = '';
            if (this.files.length > 0) {
                for (let i = 0; i < this.files.length; i++) {
                    list.innerHTML += '<div>📸 ' + this.files[i].name + '</div>';
                }
            }
        });

        // Upload Progress Animation
        const form = document.querySelector('form');
        const overlay = document.getElementById('loading-overlay');
        const progressBar = document.getElementById('progress-bar');
        const statusText = document.getElementById('upload-status');

        form.addEventListener('submit', function (e) {
            if (document.getElementById('photos').files.length === 0) {
                alert('Lütfen en az bir fotoğraf seçin.');
                e.preventDefault();
                return;
            }

            // Overlay'i göster
            overlay.classList.add('active');

            // Simüle edilmiş progress
            let width = 0;
            const interval = setInterval(() => {
                if (width >= 90) {
                    clearInterval(interval);
                } else {
                    let increment = Math.max(0.5, (90 - width) / 10);
                    width += increment;
                    if (width > 90) width = 90;
                    progressBar.style.width = width + '%';
                    statusText.innerText = '%' + Math.floor(width);
                }
            }, 150);
        });
    </script>
</body>

</html>