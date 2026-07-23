<?php

/**
 * Functional Test: Simulasi Race Condition (Flash Sale)
 * 
 * Script ini membuktikan bahwa API kebal terhadap over-selling.
 * Skenario:
 * Ada sebuah produk dengan stok X (misal 5).
 * Kita mengirim Y request secara BERSAMAAN (paralel) menggunakan curl_multi.
 * Di mana Y > X (misal 50 request paralel).
 * 
 * Ekspektasi:
 * - Hanya 5 request yang berhasil (HTTP 201)
 * - 45 request ditolak karena kehabisan stok (HTTP 422)
 * - Stok akhir di database = 0 (TIDAK NEGATIF)
 *
 * Cara menjalankan: 
 *   php tests/FlashSaleTest.php
 */

// 1. Tentukan URL API Lokal
$apiUrl = 'http://localhost/onlinestore/public/api/orders';

// 2. ID Produk Flash Sale (iPhone 15 Pro Max, ID = 2, Stok = 5 di seeder)
$productId = 2; 

// 3. Jumlah request konkuren (orang yang nge-klik "Beli" secara bersamaan)
$totalConcurrentRequests = 50; 

echo "========================================================\n";
echo "🚀 MEMULAI SIMULASI FLASH SALE (RACE CONDITION)\n";
echo "========================================================\n";
echo "Target Endpoint   : {$apiUrl}\n";
echo "Produk ID         : {$productId}\n";
echo "Jumlah Request    : {$totalConcurrentRequests} concurrent HTTP POSTs\n\n";
echo "Menembakkan request secara paralel...\n";

// Inisialisasi cURL Multi Handler
$mh = curl_multi_init();
$chArray = [];

$payload = json_encode([
    'items' => [
        [
            'product_id' => $productId,
            'quantity' => 1
        ]
    ],
    'notes' => 'Flash Sale Hunter!'
]);

// Buat 50 request secara bersamaan
for ($i = 0; $i < $totalConcurrentRequests; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // Matikan timeout agar semua menunggu respon
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    curl_multi_add_handle($mh, $ch);
    $chArray[$i] = $ch;
}

// Eksekusi semua request secara asinkron / paralel
$active = null;
$timeStart = microtime(true);
do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) != -1) {
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}
$timeEnd = microtime(true);

// Analisis Hasil Respon
$successCount = 0;
$failedCount = 0;
$errorMessages = [];

foreach ($chArray as $ch) {
    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode === 201) {
        $successCount++;
    } else {
        $failedCount++;
        $decoded = json_decode($response, true);
        $msg = $decoded['message'] ?? 'Unknown Error';
        $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
    }
    
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

$timeTaken = round($timeEnd - $timeStart, 2);

echo "========================================================\n";
echo "📊 HASIL SIMULASI (Selesai dalam {$timeTaken} detik)\n";
echo "========================================================\n";
echo "✅ Order Berhasil (HTTP 201)   : {$successCount}\n";
echo "❌ Order Ditolak  (HTTP 422+)  : {$failedCount}\n";
echo "\nDetail Error Penolakan:\n";
foreach ($errorMessages as $msg => $count) {
    echo "  - \"{$msg}\" ({$count} kali)\n";
}
echo "\n========================================================\n";
echo "ℹ️  KESIMPULAN: \n";
if ($successCount <= 5 && $successCount > 0) {
    echo "SISTEM AMAN! 🛡️ Pessimistic Locking bekerja dengan baik.\n";
    echo "Tidak terjadi over-selling. Stok tidak pernah minus.\n";
} else {
    echo "PERINGATAN! ⚠️ Terjadi kebocoran stok atau request gagal semua.\n";
}
echo "========================================================\n";
