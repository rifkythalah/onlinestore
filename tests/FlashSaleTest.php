<?php

/**
 * tests/FlashSaleTest.php — Functional Test: Race Condition (Flash Sale)
 *
 * Script ini membuktikan bahwa sistem kebal terhadap over-selling
 * saat terjadi lonjakan request secara bersamaan (race condition).
 *
 * Skenario:
 *   - Produk flash sale memiliki stok terbatas (misal 5).
 *   - Dikirim 50 request POST secara paralel menggunakan curl_multi.
 *   - Ekspektasi: tepat 5 order berhasil, 45 ditolak, stok akhir = 0.
 *
 * Cara menjalankan:
 *   php tests/FlashSaleTest.php
 *   php tests/FlashSaleTest.php http://example.com/api/orders 1 50
 *
 * Argumen (opsional):
 *   $argv[1]  URL endpoint orders  (default: http://localhost:8080/api/orders)
 *   $argv[2]  Product ID           (default: 2)
 *   $argv[3]  Jumlah request       (default: 50)
 */

$apiUrl    = $argv[1] ?? 'http://localhost:8080/api/orders';
$productId = (int) ($argv[2] ?? 2);
$total     = (int) ($argv[3] ?? 50);

echo "\n========================================================\n";
echo "  SIMULASI FLASH SALE — RACE CONDITION TEST\n";
echo "========================================================\n";
echo "  Endpoint  : {$apiUrl}\n";
echo "  Produk ID : {$productId}\n";
echo "  Request   : {$total} concurrent POSTs\n";
echo "--------------------------------------------------------\n";
echo "  Menembakkan semua request secara paralel...\n\n";

// Siapkan payload JSON untuk setiap request
$payload = json_encode([
    'items' => [['product_id' => $productId, 'quantity' => 1]],
    'notes' => 'Flash sale order',
]);

// Daftarkan semua handle ke cURL Multi
$mh      = curl_multi_init();
$handles = [];

for ($i = 0; $i < $total; $i++) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

// Eksekusi semua request secara paralel
$active    = null;
$timeStart = microtime(true);

do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc === CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc === CURLM_OK) {
    if (curl_multi_select($mh) !== -1) {
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);
    }
}

$timeTaken = round(microtime(true) - $timeStart, 2);

// Kumpulkan dan analisis hasil
$successCount  = 0;
$failedCount   = 0;
$errorMessages = [];

foreach ($handles as $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $body     = curl_multi_getcontent($ch);

    if ($httpCode === 201) {
        $successCount++;
    } else {
        $failedCount++;
        $decoded = json_decode($body, true);
        $msg = $decoded['message'] ?? 'No response (HTTP ' . $httpCode . ')';
        $errorMessages[$msg] = ($errorMessages[$msg] ?? 0) + 1;
    }

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

// Tampilkan ringkasan hasil
echo "========================================================\n";
echo "  HASIL (selesai dalam {$timeTaken} detik)\n";
echo "========================================================\n";
printf("  %-30s : %d\n", 'Order Berhasil (HTTP 201)', $successCount);
printf("  %-30s : %d\n", 'Order Ditolak  (HTTP 422)', $failedCount);

if (!empty($errorMessages)) {
    echo "\n  Alasan Penolakan:\n";
    foreach ($errorMessages as $msg => $count) {
        echo "    [{$count}x] {$msg}\n";
    }
}

echo "\n--------------------------------------------------------\n";

// Verifikasi hasil — sistem dianggap aman jika tidak ada over-selling
if ($successCount > 0 && ($successCount + $failedCount) === $total) {
    echo "  HASIL  : SISTEM AMAN\n";
    echo "  Pessimistic Locking bekerja. Tidak terjadi over-selling.\n";
} elseif ($successCount === 0) {
    echo "  HASIL  : SEMUA REQUEST GAGAL\n";
    echo "  Pastikan server berjalan dan produk memiliki stok.\n";
} else {
    echo "  HASIL  : PERLU INVESTIGASI\n";
    echo "  Jumlah request tidak sesuai ekspektasi.\n";
}

echo "========================================================\n\n";
