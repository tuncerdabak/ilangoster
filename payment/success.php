<?php
require_once '../db.php';
session_start();

// Güvenlik: Giriş Kontrolü (Opsiyonel ama iyi olur)
if (!isset($_SESSION['user_id'])) {
    // Eğer session yoksa (belki zaman aşımı), yine de göster ama dashboard'a login'e yönlendir
    $redirect_url = '../login.php';
} else {
    $redirect_url = '../dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Ödeme Başarılı! - İlanGöster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        body {
            background: #f0fdf4;
            /* Light Green */
        }

        .checkmark-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #22c55e;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.5);
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .checkmark {
            width: 50px;
            height: 50px;
            color: white;
        }

        @keyframes popIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            80% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-10 rounded-2xl shadow-2xl text-center max-w-md w-full">
        <div class="checkmark-circle">
            <svg class="checkmark" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Ödeme Başarılı!</h1>
        <p class="text-gray-600 mb-6">Paketiniz hesabınıza tanımlandı. Artık özgürce ilan paylaşabilirsiniz.</p>

        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-6 dark:bg-gray-700 overflow-hidden">
            <div id="progress" class="bg-green-600 h-2.5 rounded-full"
                style="width: 100%; transition: width 3s linear;"></div>
        </div>

        <p class="text-sm text-gray-500">
            <span id="countdown">3</span> saniye içinde panelinize yönlendiriliyorsunuz...
        </p>

        <a href="<?php echo $redirect_url; ?>"
            class="mt-6 inline-block bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700 transition transform hover:scale-105">
            Hemen Başla
        </a>
    </div>

    <script>
        // Confetti Patlaması 
        confetti({
            particleCount: 150,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#22c55e', '#16a34a', '#4ade80', '#ffffff']
        });

        // Geri Sayım ve Yönlendirme
        let count = 3;
        const countdownEl = document.getElementById('countdown');
        const progressEl = document.getElementById('progress');

        // Progress bar animasyonu başlat (CSS transition ile)
        setTimeout(() => {
            progressEl.style.width = '0%';
        }, 100);

        const timer = setInterval(() => {
            count--;
            countdownEl.innerText = count;
            if (count <= 0) {
                clearInterval(timer);
                window.location.href = "<?php echo $redirect_url; ?>";
            }
        }, 1000);
    </script>
</body>

</html>