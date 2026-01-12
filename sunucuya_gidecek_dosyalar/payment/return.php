<?php
require_once '../db.php';
session_start();

// Shopier genellikle POST ile veri gönderir ama bazen GET ile dönebilir.
// Basit bir başarılı mesajı ve yönlendirme.
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Sonucu - İlan Göster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f9;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 md:p-10 rounded-xl shadow-2xl text-center max-w-lg">
        <div class="mb-6">
            <svg class="w-20 h-20 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-4">Ödeme Başarılı!</h2>
        <p class="text-gray-600 mb-8">
            Paketiniz hesabınıza tanımlanmıştır. Artık daha fazla ilan yükleyebilir ve paylaşabilirsiniz.
        </p>
        <a href="../index.php"
            class="bg-indigo-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-indigo-700 transition duration-200">
            Ana Sayfaya Dön ve İlan Yükle
        </a>
    </div>
</body>

</html>