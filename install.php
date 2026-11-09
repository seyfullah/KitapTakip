<?php
// Kullanım: php install.php [kullanıcı] [şifre]   (şemayı kurar, yönetici oluşturur)
require __DIR__ . '/lib.php';
db()->exec((string) file_get_contents(__DIR__ . '/sql/schema.sql'));
$u = $argv[1] ?? 'admin'; $pw = $argv[2] ?? 'admin123';
q('INSERT INTO users (kullanici, sifre_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE sifre_hash = VALUES(sifre_hash)', [$u, password_hash($pw, PASSWORD_DEFAULT)]);
echo "Kurulum tamam. Kullanıcı: $u\n";
if (in_array('--demo', $argv, true)) require __DIR__ . '/sql/demo.php';
