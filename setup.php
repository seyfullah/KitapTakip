<?php
/**
 * Web Tabanlı Kurulum Sayfası
 * 
 * Kullanım: https://sitesi.com/uy/setup.php
 * 
 * GÜVENLIK: Kurulumdan sonra bu dosyayı SİLİN!
 * rm setup.php
 */

// Güvenlik: İlk 100 erişimden sonra otomatik devre dışı
$log_file = __DIR__ . '/.setup-log';
$access_count = 0;
if (is_file($log_file)) {
    $access_count = (int) file_get_contents($log_file);
}
if ($access_count > 100) {
    die("❌ Kurulum devre dışı. Bu dosya henüz kaldırılmamış olabilir. setup.php dosyasını silin.\n");
}
file_put_contents($log_file, $access_count + 1);

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// HTML başlık
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kitap Cari - Kurulum</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 2rem; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 1rem; }
        .status { margin: 1rem 0; padding: 1rem; border-radius: 4px; }
        .status.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .progress { margin: 1rem 0; }
        .step { padding: 0.5rem 0; font-size: 0.95rem; }
        .step.done { color: #28a745; }
        .step.error { color: #dc3545; }
        .step.pending { color: #999; }
        button { background: #007bff; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem; margin-top: 1rem; }
        button:hover { background: #0056b3; }
        code { background: #f4f4f4; padding: 0.2rem 0.5rem; border-radius: 3px; font-family: monospace; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 1rem; border-radius: 4px; margin: 1rem 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Kitap Cari Kurulum</h1>
<?php

// Ortam bilgisi
echo '<div class="status info">';
echo '<strong>Ortam:</strong><br>';
echo 'PHP: ' . PHP_VERSION . '<br>';
echo 'Sunucu: ' . php_uname('s') . '<br>';
echo 'Çalışan Kullanıcı: ' . get_current_user() . '<br>';
echo '</div>';

// 1. Konfigürasyon dosyası kontrol (lib.php OLMADAN)
echo '<h2>Adım 1: Konfigürasyon</h2>';
echo '<div class="progress"><div class="step">';

if (is_file(__DIR__ . '/config.php')) {
    echo '✅ config.php mevcut';
    try {
        $cfg = require __DIR__ . '/config.php';
        echo '</div><div class="status ok">✅ Konfigürasyon yüklendi<br>';
        echo 'Depolama: ' . ($cfg['app_storage'] ?? 'sqlite') . '<br>';
        if (($cfg['app_storage'] ?? 'sqlite') === 'sqlite') {
            $data_file = $cfg['app_data_file'] ?? dirname(__DIR__) . '/kitaptakip-data/kitaptakip.sqlite';
            echo 'SQLite dosyası: ' . htmlspecialchars($data_file) . '<br>';
            $data_dir = dirname($data_file);
            echo 'Veri klasörü: ' . htmlspecialchars($data_dir) . '<br>';
            if (is_dir($data_dir)) {
                echo 'Klasör izinleri: ' . substr(sprintf('%o', fileperms($data_dir)), -4) . '<br>';
            }
        }
        echo '</div>';
    } catch (Exception $e) {
        echo '</div><div class="status error">❌ Hata: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
} else {
    echo '❌ config.php bulunamadı</div>';
    echo '<div class="status error">Setup yapılamıyor. config.php dosyası gerekli.</div>';
    exit;
}

// 2. SQLite dosyasını kontrol et
echo '<h2>Adım 2: SQLite Dosyası</h2>';
echo '<div class="progress"><div class="step">';

if (($cfg['app_storage'] ?? 'sqlite') === 'sqlite') {
    $data_file = $cfg['app_data_file'] ?? dirname(__DIR__) . '/kitaptakip-data/kitaptakip.sqlite';
    $data_dir = dirname($data_file);
    
    // Klasör var mı?
    if (!is_dir($data_dir)) {
        echo '📁 Veri klasörü yok, oluşturuluyor...' . "</div>\n";
        if (@mkdir($data_dir, 0755, true)) {
            echo '<div class="status ok">✅ Klasör oluşturuldu: ' . htmlspecialchars($data_dir) . '</div>';
        } else {
            echo '<div class="status error">❌ Klasör oluşturulamadı. FTP yoluyla oluşturun: ' . htmlspecialchars($data_dir) . '</div>';
            exit;
        }
    } else {
        echo '✅ Klasör mevcut: ' . htmlspecialchars($data_dir) . "</div>\n";
        echo '<div class="status ok">' . (is_writable($data_dir) ? '✅ Yazılabilir' : '⚠️ Yazı izni yok') . '</div>';
    }
    
    // Dosya var mı?
    if (is_file($data_file)) {
        echo '<div class="step">✅ SQLite dosyası mevcut</div>';
        echo '<div class="status info">' . filesize($data_file) . ' byte</div>';
    } else {
        echo '<div class="step">📄 SQLite dosyası yok, oluşturuluyor...</div>';
        try {
            $pdo = new PDO("sqlite:$data_file");
            $pdo = null;
            echo '<div class="status ok">✅ Dosya oluşturuldu</div>';
        } catch (Exception $e) {
            echo '<div class="status error">❌ Dosya oluşturulamadı: ' . htmlspecialchars($e->getMessage()) . '</div>';
            exit;
        }
    }
} else {
    echo '✅ MySQL modu seçili</div>';
    echo '<div class="status info">SQLite dosyası gerekmez</div>';
}

// 3. Veritabanı şemasını oluştur
echo '<h2>Adım 3: Veritabanı Şeması</h2>';
echo '<div class="progress"><div class="step">';

try {
    // lib.php'yi YÖK require etmeden doğrudan PDO aç
    $data_file = $cfg['app_data_file'] ?? dirname(__DIR__) . '/kitaptakip-data/kitaptakip.sqlite';
    $pdo = new PDO("sqlite:$data_file", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $table_count = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'uy_%'")->fetchColumn();
    
    if ($table_count > 0) {
        echo "✅ Tablolar mevcut ($table_count tablo)</div>";
        echo '<div class="status ok">Veritabanı zaten kurulu</div>';
    } else {
        echo "📊 Tablolar oluşturuluyor...</div>\n";
        
        // Schema dosyasını yükle ve migrasyonları çalıştır
        $schema_file = __DIR__ . '/sql/schema.sqlite.sql';
        
        if (!is_file($schema_file)) {
            echo '<div class="status error">❌ Schema dosyası bulunamadı: ' . htmlspecialchars($schema_file) . '</div>';
            exit;
        }
        
        // PRAGMA user_version sıfır olduğundan, lib.php'deki sqlite_upgrade() işlevini çalıştır
        // (Bunu buraya inline yazacağız çünkü lib.php henüz yüklü değil)
        require_once __DIR__ . '/lib.php';
        sqlite_upgrade($pdo);
        
        echo '<div class="status ok">✅ Tablolar oluşturuldu</div>';
    }
} catch (Exception $e) {
    echo '</div><div class="status error">❌ Veritabanı hatası: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// 4. Admin kullanıcısını oluştur
echo '<h2>Adım 4: Admin Kullanıcısı</h2>';
echo '<div class="progress"><div class="step">';

try {
    // lib.php olmadan doğrudan SQL sorgusu yapacağız
    $stmt = $pdo->prepare('SELECT id FROM uy_users WHERE kullanici = ?');
    $stmt->execute(['admin']);
    $admin_exists = (bool) $stmt->fetchColumn();
    
    if ($admin_exists) {
        echo '✅ Admin kullanıcısı mevcut</div>';
        echo '<div class="status info">admin / admin123 ile giriş yapabilirsiniz</div>';
    } else {
        echo '👤 Admin kullanıcısı oluşturuluyor...</div>';
        $stmt = $pdo->prepare('INSERT INTO uy_users (kullanici, sifre_hash) VALUES (?, ?)');
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
        echo '<div class="status ok">✅ Admin kullanıcısı oluşturuldu<br>Kullanıcı: <code>admin</code><br>Şifre: <code>admin123</code></div>';
    }
} catch (Exception $e) {
    echo '</div><div class="status error">❌ Kullanıcı oluşturma hatası: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// 5. Başarı mesajı
echo '<h2>✅ Kurulum Tamamlandı!</h2>';
echo '<div class="status ok">';
echo 'Uygulamaya <a href="?">buradan</a> girebilirsiniz.<br>';
echo 'Giriş: <code>admin</code> / <code>admin123</code>';
echo '</div>';

echo '<div class="warning">';
echo '<strong>⚠️ ÖNEMLİ: Bu dosyayı silin!</strong><br>';
echo 'Güvenlik için bu dosya (<code>setup.php</code>) silinmelidir.<br>';
echo 'FTP yoluyla silebilir veya sunucu panelinden File Manager ile silinebilir.';
echo '</div>';

echo '</div></body></html>';
?>
