<?php
// Kullanım: php install.php [kullanıcı] [şifre] [--demo] [--sqlite]
// --sqlite MySQL sunucusu kullanmadan tek bir yerel veri dosyası oluşturur.
if (in_array('--sqlite', $argv ?? [], true)) putenv('APP_STORAGE=sqlite');
require __DIR__ . '/lib.php';
db()->exec((string) file_get_contents(__DIR__ . '/sql/schema' . (cfg('app_storage') === 'sqlite' ? '.sqlite' : '') . '.sql'));
$args = array_values(array_filter($argv ?? [], static fn($arg) => !str_starts_with($arg, '--')));
$u = $args[1] ?? 'admin'; $pw = $args[2] ?? 'admin123';
if (cfg('app_storage') === 'sqlite') {
    q('INSERT INTO users (kullanici, sifre_hash) VALUES (?, ?) ON CONFLICT(kullanici) DO UPDATE SET sifre_hash = excluded.sifre_hash', [$u, password_hash($pw, PASSWORD_DEFAULT)]);
} else {
    q('INSERT INTO users (kullanici, sifre_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE sifre_hash = VALUES(sifre_hash)', [$u, password_hash($pw, PASSWORD_DEFAULT)]);
}
echo "Kurulum tamam. Kullanıcı: $u\n";
if (in_array('--demo', $argv, true)) require __DIR__ . '/sql/demo.php';
