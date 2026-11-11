<?php
declare(strict_types=1);

$path = tempnam(sys_get_temp_dir(), 'kitaptakip-demo-');
if ($path === false) throw new RuntimeException('Geçici dosya oluşturulamadı.');
unlink($path);
$env = getenv();
$env['APP_STORAGE'] = 'sqlite';
$env['APP_DATA_FILE'] = $path;
$run = static function () use ($env): int {
    $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', 'install.php', '--sqlite', '--demo'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
    if (!is_resource($process)) throw new RuntimeException('Demo kurulumu başlatılamadı.');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $result = proc_close($process);
    if ($result !== 0 && !str_contains($err, 'mevcut dosya değiştirilmedi')) throw new RuntimeException($out . $err);
    return $result;
};
try {
    if ($run() !== 0) throw new RuntimeException('Demo kurulumu başarısız.');
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables = ['uy_users', 'uy_people', 'uy_books', 'uy_customers', 'uy_stock_in', 'uy_supplier_purchases',
        'uy_supplier_payments', 'uy_stock_out', 'uy_stock_out_returns', 'uy_sales', 'uy_sale_items', 'uy_sale_deliveries',
        'uy_legacy_sale_returns', 'uy_customer_returns', 'uy_supplier_returns', 'uy_supplier_return_links',
        'uy_payments', 'uy_expenses', 'uy_donations', 'uy_loans', 'uy_loan_payments', 'uy_notification_log'];
    foreach ($tables as $table) {
        if ((int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() < 3) throw new RuntimeException("$table tablosunda üç kayıt yok.");
    }
    if ($pdo->query('PRAGMA foreign_key_check')->fetch() !== false) throw new RuntimeException('Demo verilerinde geçersiz ilişki var.');
    $pdo = null;
    $before = hash_file('sha256', $path);
    if ($run() === 0 || hash_file('sha256', $path) !== $before) throw new RuntimeException('Mevcut demo dosyası değiştirildi.');
    echo "Demo verileri ve mevcut dosya koruması doğrulandı.\n";
} finally {
    if (is_file($path)) unlink($path);
}
