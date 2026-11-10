<?php
declare(strict_types=1);

$path = tempnam(sys_get_temp_dir(), 'kitaptakip-upgrade-');
if ($path === false) throw new RuntimeException('Geçici dosya oluşturulamadı.');

try {
    $legacy = new PDO('sqlite:' . $path);
    $legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $legacy->exec(<<<'SQL'
        CREATE TABLE books (id INTEGER PRIMARY KEY, kod TEXT, ad TEXT, yayinevi TEXT, sinif TEXT, alis_fiyati NUMERIC, satis_fiyati NUMERIC, koli_adedi INTEGER, kritik_stok INTEGER);
        CREATE TABLE customers (id INTEGER PRIMARY KEY, tip TEXT, ad TEXT, il TEXT, ilce TEXT, telefon TEXT, eposta TEXT);
        CREATE TABLE stock_in (id INTEGER PRIMARY KEY, book_id INTEGER, tarih TEXT, adet INTEGER, aciklama TEXT);
        CREATE TABLE stock_out (id INTEGER PRIMARY KEY, book_id INTEGER, customer_id INTEGER, tarih TEXT, adet INTEGER, koli NUMERIC, alan_kisi TEXT NOT NULL, teslim_yeri TEXT NOT NULL, aciklama TEXT);
        CREATE TABLE sales (id INTEGER PRIMARY KEY, customer_id INTEGER, tarih TEXT, vade_tarihi TEXT, aciklama TEXT, toplam NUMERIC);
        CREATE TABLE sale_items (id INTEGER PRIMARY KEY, sale_id INTEGER, book_id INTEGER, adet INTEGER, koli NUMERIC, birim_fiyat NUMERIC, toplam NUMERIC);
        INSERT INTO books VALUES (1, 'BK', 'Kitap', 'Eski yayinevi', 'Eski sinif', 5, 10, 10, 0);
        INSERT INTO customers VALUES (1, 'Okul', 'Musteri', '', '', '', '');
        INSERT INTO stock_in VALUES (1, 1, '2025-01-01', 20, '');
        INSERT INTO stock_out VALUES (1, 1, 1, '2025-01-02', 1, 0.1, 'Eski teslim alan', 'Eski adres', '');
        INSERT INTO sales VALUES (1, 1, '2025-01-03', '2025-02-01', '', 20);
        INSERT INTO sale_items VALUES (1, 1, 1, 2, 0.2, 10, 20);
    SQL);
    $legacy = null;

    putenv('APP_STORAGE=sqlite');
    putenv('APP_DATA_FILE=' . $path);
    require __DIR__ . '/../lib.php';
    $pdo = db();
    $check = static function (bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
    };

    $check((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 1, 'Şema sürümü güncellenmedi.');
    $check(count(stock_report()) === 1 && book_stock(1) === 17, 'Mevcut stok korunmadı.');
    $check((float) val('SELECT maliyet_birim FROM sale_items WHERE id = 1') === 5.0, 'Satış maliyeti doldurulmadı.');
    $old = row('SELECT alan_kisi, teslim_yeri FROM stock_out WHERE id = 1');
    $check($old['alan_kisi'] === 'Eski teslim alan' && $old['teslim_yeri'] === 'Eski adres', 'Eski teslim bilgileri korunmadı.');
    $check(val('SELECT yayinevi FROM books WHERE id = 1') === 'Eski yayinevi', 'Eski kitap bilgileri korunmadı.');

    insert_stock_out(1, 1, '2025-01-04', 1, 0.1, 'Götüren', '');
    $check(book_stock(1) === 16, 'Eski dosyaya yeni stok çıkışı yazılamadı.');
    $new = row('SELECT goturen_kisi, alan_kisi, teslim_yeri FROM stock_out WHERE id = 2');
    $check($new['goturen_kisi'] === 'Götüren' && $new['alan_kisi'] === '' && $new['teslim_yeri'] === '', 'Yeni stok çıkışı yanlış kaydedildi.');

    q('UPDATE books SET alis_fiyati = 9 WHERE id = 1');
    sqlite_upgrade($pdo);
    $check((float) val('SELECT maliyet_birim FROM sale_items WHERE id = 1') === 5.0, 'Tekrarlanan güncelleme eski maliyeti değiştirdi.');
    $check((float) financial_summary()['profit'] === 10.0, 'Finans özeti eski verilerle çalışmıyor.');
    echo "SQLite yükseltmesi ve eski veriler doğrulandı.\n";
} finally {
    unlink($path);
}
