<?php
// Mevcut veritabanı kurulumlarına yeni stok kişi alanlarını ekler.
require __DIR__ . '/lib.php';

$pdo = db();
$sqlite = cfg('app_storage') === 'sqlite';
$columns = static function (string $table) use ($pdo, $sqlite): array {
    if ($sqlite) {
        return array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    }
    return array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(), 'Field');
};

$stockInColumns = $columns('stock_in');
if (!in_array('teslim_alan', $stockInColumns, true)) {
    $pdo->exec($sqlite
        ? "ALTER TABLE stock_in ADD COLUMN teslim_alan TEXT NOT NULL DEFAULT ''"
        : "ALTER TABLE stock_in ADD COLUMN teslim_alan VARCHAR(120) NOT NULL DEFAULT ''");
}

$stockOutColumns = $columns('stock_out');
if (!in_array('goturen_kisi', $stockOutColumns, true)) {
    $pdo->exec($sqlite
        ? "ALTER TABLE stock_out ADD COLUMN goturen_kisi TEXT NOT NULL DEFAULT ''"
        : "ALTER TABLE stock_out ADD COLUMN goturen_kisi VARCHAR(120) NOT NULL DEFAULT ''");
}
foreach (['alan_kisi', 'teslim_yeri'] as $column) {
    if (in_array($column, $stockOutColumns, true)) {
        $pdo->exec("ALTER TABLE stock_out DROP COLUMN $column");
    }
}

$bookColumns = $columns('books');
if ($sqlite) {
    $pdo->exec('DROP INDEX IF EXISTS books_yayinevi');
    foreach (['yayinevi', 'sinif'] as $column) {
        if (in_array($column, $bookColumns, true)) {
            $pdo->exec("ALTER TABLE books DROP COLUMN $column");
        }
    }
} else {
    foreach ($pdo->query("SHOW INDEX FROM books WHERE Column_name IN ('yayinevi', 'sinif')")->fetchAll() as $index) {
        if ($index['Key_name'] !== 'PRIMARY') {
            $indexName = str_replace('`', '``', $index['Key_name']);
            $pdo->exec("ALTER TABLE books DROP INDEX `$indexName`");
        }
    }
    foreach (['yayinevi', 'sinif'] as $column) {
        if (in_array($column, $bookColumns, true)) {
            $pdo->exec("ALTER TABLE books DROP COLUMN $column");
        }
    }
}

$saleItemColumns = $columns('sale_items');
if (!in_array('maliyet_birim', $saleItemColumns, true)) {
    $pdo->exec($sqlite
        ? 'ALTER TABLE sale_items ADD COLUMN maliyet_birim NUMERIC NOT NULL DEFAULT 0'
        : 'ALTER TABLE sale_items ADD COLUMN maliyet_birim DECIMAL(12,2) NOT NULL DEFAULT 0');
    $pdo->exec('UPDATE sale_items SET maliyet_birim = COALESCE((SELECT alis_fiyati FROM books WHERE books.id = sale_items.book_id), 0)');
}

$schema = file_get_contents(__DIR__ . '/sql/schema' . ($sqlite ? '.sqlite' : '') . '.sql');
if ($schema === false) throw new RuntimeException('Şema dosyası okunamadı.');
$pdo->exec($schema);

echo "Veritabanı güncellendi.\n";
