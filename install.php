<?php
// Kullanım: php install.php [kullanıcı] [şifre] [--demo] [--sqlite]
// --sqlite MySQL sunucusu kullanmadan tek bir yerel veri dosyası oluşturur.
if (in_array('--sqlite', $argv ?? [], true)) putenv('APP_STORAGE=sqlite');
require __DIR__ . '/lib.php';
$demo = in_array('--demo', $argv ?? [], true);
if ($demo && cfg('app_storage') === 'sqlite' && is_file((string) cfg('app_data_file'))) {
    throw new RuntimeException('Demo kurulumu yalnızca yeni bir SQLite dosyasında yapılabilir; mevcut dosya değiştirilmedi.');
}
if ($demo && cfg('app_storage') === 'mysql' && (int) val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ?', ['BASE TABLE']) > 0) {
    throw new RuntimeException('Demo kurulumu yalnızca boş bir MySQL/MariaDB veritabanında yapılabilir; mevcut veriler değiştirilmedi.');
}
db()->exec((string) file_get_contents(__DIR__ . '/sql/schema' . (cfg('app_storage') === 'sqlite' ? '.sqlite' : '') . '.sql'));
$args = array_values(array_filter($argv ?? [], static fn($arg) => !str_starts_with($arg, '--')));
$u = $args[1] ?? 'admin'; $pw = $args[2] ?? 'admin123';
if (cfg('app_storage') === 'sqlite') {
    q('INSERT INTO uy_users (kullanici, sifre_hash) VALUES (?, ?) ON CONFLICT(kullanici) DO UPDATE SET sifre_hash = excluded.sifre_hash', [$u, password_hash($pw, PASSWORD_DEFAULT)]);
} else {
    q('INSERT INTO uy_users (kullanici, sifre_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE sifre_hash = VALUES(sifre_hash)', [$u, password_hash($pw, PASSWORD_DEFAULT)]);
}
echo "Kurulum tamam. Kullanıcı: $u\n";
if ($demo) require __DIR__ . '/sql/demo.php';
