<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlan Göster - Android Uygulamasını İndir</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .hero-gradient { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <header class="bg-white shadow-sm py-4">
        <div class="max-w-4xl mx-auto px-4 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-2">
                <img src="logo.png" alt="İlanGöster" class="h-10 w-auto">
                <span class="text-xl font-bold text-gray-800">ilan<span class="text-green-600">goster.com</span></span>
            </a>
            <a href="index.php" class="text-gray-500 hover:text-gray-700 font-medium text-sm">Geri Dön</a>
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="hero-gradient p-8 text-center text-white">
                <div class="bg-white p-4 rounded-2xl inline-block mb-4 shadow-inner">
                    <img src="logo.png" alt="App Icon" class="h-16 w-auto">
                </div>
                <h1 class="text-3xl font-black mb-2">Android Uygulaması</h1>
                <p class="opacity-90 font-medium">Portföyünüz cebinizde, ilanlarınız güvende!</p>
            </div>

            <div class="p-8">
                <a href="android_uygulama/ilangoster_v1.apk" class="block w-full py-4 px-6 bg-green-600 hover:bg-green-700 text-white text-center font-bold rounded-2xl shadow-lg transition transform hover:-translate-y-1 mb-8 text-lg">
                    Hemen İndir (.APK)
                </a>

                <div class="space-y-6">
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Kurulum Adımları</h3>
                    
                    <div class="flex items-start gap-4">
                        <div class="bg-green-100 text-green-600 rounded-full h-8 w-8 flex items-center justify-center flex-shrink-0 font-bold">1</div>
                        <div>
                            <p class="font-bold text-gray-800">APK Dosyasını İndirin</p>
                            <p class="text-sm text-gray-600">Yukarıdaki butona tıklayarak dosyayı telefonunuza kaydedin.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="bg-green-100 text-green-600 rounded-full h-8 w-8 flex items-center justify-center flex-shrink-0 font-bold">2</div>
                        <div>
                            <p class="font-bold text-gray-800">Dosyayı Açın</p>
                            <p class="text-sm text-gray-600">İndirme tamamlandığında bildirim panelinden veya "Dosyalarım" klasöründen dosyayı açın.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="bg-green-100 text-green-600 rounded-full h-8 w-8 flex items-center justify-center flex-shrink-0 font-bold">3</div>
                        <div>
                            <p class="font-bold text-gray-800">İzne Onay Verin</p>
                            <p class="text-sm text-gray-600">Eğer "Bilinmeyen Kaynaklar" uyarısı alırsanız, ayarlar kısmından bu tarayıcıya izin verin.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-10 p-4 bg-yellow-50 rounded-xl border border-yellow-100">
                    <p class="text-xs text-yellow-800 leading-relaxed text-center">
                        <strong>Not:</strong> Uygulamamız henüz Google Play Store'da onay aşamasındadır. Bu nedenle "Bilinmeyen Kaynak" uyarısı almanız normaldir, güvenle kurabilirsiniz.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-6 text-center text-gray-400 text-xs">
        <p>&copy; <?php echo date('Y'); ?> ilangoster.com - Mobil Uygulama Merkezi</p>
    </footer>
</body>
</html>
