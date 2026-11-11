<?php require '_init.php';

/**
 * SQLite'dan MariaDB/MySQL'e Veri Taşıma Script
 * 
 * Kullanım:
 *   php migrate_to_mysql.php
 * 
 * Ortam değişkenleri (config.php'de veya şu şekilde ayarlayın):
 *   DB_HOST=localhost
 *   DB_NAME=kitapcari
 *   DB_USER=kitapcari_app
 *   DB_PASS=password
 */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// SQLite bağlantısını ayarla
putenv('APP_STORAGE=sqlite');
require __DIR__ . '/lib.php';

echo "🔄 SQLite → MariaDB/MySQL Veri Taşıma\n";
echo "=" . str_repeat("=", 48) . "\n\n";

// Kaynak (SQLite) veritabanını kontrol et
try {
    $sqlite_count = (int) val('SELECT COUNT(*) FROM uy_users');
    echo "✅ SQLite bağlantısı başarılı\n";
    echo "   Kullanıcı tablosu: " . $sqlite_count . " kayıt\n\n";
} catch (Exception $e) {
    die("❌ SQLite bağlantı hatası: " . $e->getMessage() . "\n");
}

// Hedef (MariaDB) bağlantısını ayarla
try {
    $mysql_host = $_ENV['DB_HOST'] ?? 'localhost';
    $mysql_name = $_ENV['DB_NAME'] ?? '';
    $mysql_user = $_ENV['DB_USER'] ?? '';
    $mysql_pass = $_ENV['DB_PASS'] ?? '';
    
    if (!$mysql_name || !$mysql_user) {
        echo "❌ Hata: DB_NAME ve DB_USER ortam değişkenleri ayarlanmalı\n";
        echo "   Örnek: DB_HOST=localhost DB_NAME=kitapcari DB_USER=app DB_PASS=pass php migrate_to_mysql.php\n";
        exit(1);
    }
    
    $pdo_mysql = new PDO(
        "mysql:host=$mysql_host;dbname=$mysql_name;charset=utf8mb4",
        $mysql_user,
        $mysql_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $mysql_count = (int) $pdo_mysql->query('SELECT COUNT(*) FROM uy_users')->fetchColumn();
    echo "✅ MariaDB bağlantısı başarılı\n";
    echo "   Mevcut kullanıcı tablosu: " . $mysql_count . " kayıt\n\n";
} catch (Exception $e) {
    die("❌ MariaDB bağlantı hatası: " . $e->getMessage() . "\n");
}

// Tablo listesini al
$tables = [
    'uy_users', 'uy_customers', 'uy_people', 'uy_books', 
    'uy_stock_in', 'uy_stock_in_items', 'uy_stock_out', 'uy_stock_out_items',
    'uy_stock_transfer', 'uy_stock_transfer_items', 'uy_sales', 'uy_sale_items',
    'uy_sale_returns', 'uy_sale_return_items', 'uy_supplier_returns', 'uy_supplier_return_items',
    'uy_stock_return_items', 'uy_stock_out_return_items', 'uy_receivable', 'uy_receivable_items',
    'uy_transfer_stock_return_items', 'uy_notification'
];

echo "📋 Veri Taşıma Planı:\n";
echo "   Tablolar: " . count($tables) . "\n\n";

// Hedef tablolardaki verileri sil (uyarı ver)
echo "⚠️  Dikkat: MariaDB'deki mevcut veriler silinecek!\n";
echo "   Devam etmek için 'EVET' yazın: ";
$confirm = trim(fgets(STDIN));

if (strtoupper($confirm) !== 'EVET') {
    echo "❌ İşlem iptal edildi\n";
    exit(1);
}

echo "\n🗑️  Hedef tablolar temizleniyor...\n";

try {
    $pdo_mysql->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $pdo_mysql->exec("TRUNCATE TABLE `$table`");
        echo "   ✓ $table\n";
    }
    $pdo_mysql->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "\n✅ Tablolar temizlendi\n\n";
} catch (Exception $e) {
    echo "❌ Temizleme hatası: " . $e->getMessage() . "\n";
    exit(1);
}

// Verileri taşı
echo "📤 Veriler taşınıyor...\n\n";

$total_rows = 0;
$errors = [];

foreach ($tables as $table) {
    try {
        // Tablo yapısını al
        $columns_result = db()->query("PRAGMA table_info($table)");
        $columns = [];
        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['name'];
        }
        
        if (empty($columns)) {
            echo "   ⚠️  $table (boş tablo, atlandı)\n";
            continue;
        }
        
        // Verileri taşı
        $col_names = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $insert_stmt = $pdo_mysql->prepare(
            "INSERT INTO `$table` ($col_names) VALUES ($placeholders)"
        );
        
        $select_result = db()->query("SELECT * FROM `$table`");
        $row_count = 0;
        
        while ($row = $select_result->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                // Tarih formatı dönüşümü
                if (preg_match('/^\\d{4}-\\d{2}-\\d{2}/', (string)$value)) {
                    $values[] = $value;
                } else {
                    $values[] = $value;
                }
            }
            $insert_stmt->execute($values);
            $row_count++;
        }
        
        $total_rows += $row_count;
        $status = $row_count > 0 ? "✓" : "○";
        echo "   $status $table ($row_count satır)\n";
        
    } catch (Exception $e) {
        $errors[] = "$table: " . $e->getMessage();
        echo "   ❌ $table - Hata: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Taşıma Tamamlandı!\n";
echo "   Toplam satır: $total_rows\n";

if (!empty($errors)) {
    echo "\n⚠️  Hatalar:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

echo "\n📝 Sonraki Adımlar:\n";
echo "   1. config.php dosyasında veya ortam değişkenlerinde:\n";
echo "      APP_STORAGE=mysql\n";
echo "      DB_HOST=$mysql_host\n";
echo "      DB_NAME=$mysql_name\n";
echo "      DB_USER=$mysql_user\n";
echo "   \n";
echo "   2. Uygulamayı yeniden başlatın\n";
echo "   3. MariaDB'den verileri kontrol edin\n";
echo "\n";
