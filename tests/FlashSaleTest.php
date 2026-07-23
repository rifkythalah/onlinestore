<?php

// Konfigurasi via Argumen CLI
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
echo "  Testing in progress...\n\n";

$payload = json_encode([
    'items' => [['product_id' => $productId, 'quantity' => 1]],
    'notes' => 'Flash sale order',
]);

$mh      = curl_multi_init();
$handles = [];

// Setup cURL handles
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

// Eksekusi asinkron
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

$successCount  = 0;
$failedCount   = 0;
$errorMessages = [];

// Kalkulasi hasil
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

if ($successCount > 0 && ($successCount + $failedCount) === $total) {
    echo "  STATUS : AMAN (Pessimistic Locking bekerja)\n";
} elseif ($successCount === 0) {
    echo "  STATUS : GAGAL (Cek koneksi DB atau pastikan stok ada)\n";
} else {
    echo "  STATUS : ERROR (Hasil tidak konsisten)\n";
}

echo "========================================================\n\n";
