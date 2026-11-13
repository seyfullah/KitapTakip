<?php
declare(strict_types=1);

$path = tempnam(sys_get_temp_dir(), 'kitaptakip-upgrade-');
if ($path === false) throw new RuntimeException('Geçici dosya oluşturulamadı.');

try {
    $legacy = new PDO('sqlite:' . $path);
    $legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $legacy->exec(<<<'SQL'
        CREATE TABLE books (id INTEGER PRIMARY KEY, kod TEXT, ad TEXT, yayinevi TEXT, sinif TEXT, alis_fiyati NUMERIC, satis_fiyati NUMERIC, koli_adedi INTEGER, kritik_stok INTEGER);
        CREATE TABLE customers (id INTEGER PRIMARY KEY, tip TEXT, ad TEXT, il TEXT, ilce TEXT, telefon TEXT, eposta TEXT, yetkili TEXT DEFAULT '', adres TEXT DEFAULT '');
        CREATE TABLE stock_in (id INTEGER PRIMARY KEY, book_id INTEGER, tarih TEXT, adet INTEGER, aciklama TEXT);
        CREATE TABLE stock_out (id INTEGER PRIMARY KEY, book_id INTEGER, customer_id INTEGER, tarih TEXT, adet INTEGER, koli NUMERIC DEFAULT 0, alan_kisi TEXT NOT NULL, teslim_yeri TEXT NOT NULL, aciklama TEXT);
        CREATE TABLE sales (id INTEGER PRIMARY KEY, customer_id INTEGER, tarih TEXT, vade_tarihi TEXT, aciklama TEXT, toplam NUMERIC);
        CREATE TABLE sale_items (id INTEGER PRIMARY KEY, sale_id INTEGER, book_id INTEGER, adet INTEGER, koli NUMERIC NOT NULL DEFAULT 0, birim_fiyat NUMERIC, toplam NUMERIC);
        INSERT INTO uy_books VALUES (1, 'BK', 'Kitap', 'Eski yayinevi', 'Eski sinif', 5, 10, 10, 0);
        INSERT INTO uy_customers (id, tip, ad, il, ilce, telefon, eposta) VALUES (1, 'Okul', 'Musteri', '', '', '', '');
        INSERT INTO uy_stock_in VALUES (1, 1, '2025-01-01', 20, '');
        INSERT INTO uy_stock_out VALUES (1, 1, 1, '2025-01-02', 1, 0.1, 'Eski teslim alan', 'Eski adres', '');
        INSERT INTO uy_sales VALUES (1, 1, '2025-01-03', '2025-02-01', '', 20);
        INSERT INTO uy_sale_items VALUES (1, 1, 1, 2, 0.2, 10, 20);
    SQL);
    $legacy = null;

    putenv('APP_STORAGE=sqlite');
    putenv('APP_DATA_FILE=' . $path);
    require __DIR__ . '/../lib.php';
    $pdo = db();
    $check = static function (bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
    };

    $check((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 6, 'Şema sürümü güncellenmedi.');
    $check(count(stock_report()) === 1 && book_stock(1) === 17, 'Mevcut stok korunmadı.');
    $check((float) val('SELECT maliyet_birim FROM uy_sale_items WHERE id = 1') === 5.0, 'Satış maliyeti doldurulmadı.');
    $old = row('SELECT alan_kisi, teslim_yeri FROM uy_stock_out WHERE id = 1');
    $check($old['alan_kisi'] === 'Eski teslim alan' && $old['teslim_yeri'] === 'Eski adres', 'Eski teslim bilgileri korunmadı.');
    $check(val('SELECT yayinevi FROM uy_books WHERE id = 1') === 'Eski yayinevi', 'Eski kitap bilgileri korunmadı.');
    foreach (['books' => 'koli_adedi', 'stock_out' => 'koli', 'sale_items' => 'koli'] as $table => $column) {
        $columns = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        $check(!in_array($column, $columns, true), "$table tablosundaki koli sütunu kaldırılmadı.");
    }
    q('INSERT INTO uy_sale_items (sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?)', [1, 1, 1, 10, 5, 10]);
    $check((int) val('SELECT adet FROM uy_sale_items WHERE id = 2') === 1, 'Eski şemaya yeni satış satırı yazılamadı.');
    q('DELETE FROM uy_sale_items WHERE id = 2');

    insert_stock_out(1, 1, '2025-01-04', 1, 'Götüren', '');
    $check(book_stock(1) === 16, 'Eski dosyaya yeni stok çıkışı yazılamadı.');
    $new = row('SELECT goturen_kisi, alan_kisi, teslim_yeri FROM uy_stock_out WHERE id = 2');
    $check($new['goturen_kisi'] === 'Götüren' && $new['alan_kisi'] === '' && $new['teslim_yeri'] === '', 'Yeni stok çıkışı yanlış kaydedildi.');

    q('UPDATE books SET alis_fiyati = 9 WHERE id = 1');
    sqlite_upgrade($pdo);
    $check((float) val('SELECT maliyet_birim FROM uy_sale_items WHERE id = 1') === 5.0, 'Tekrarlanan güncelleme eski maliyeti değiştirdi.');
    $check((float) financial_summary()['profit'] === 10.0, 'Finans özeti eski verilerle çalışmıyor.');
    q("UPDATE stock_in SET teslim_alan = 'Önceki kişi' WHERE id = 1");
    q("UPDATE stock_out SET goturen_kisi = 'Önceki kişi' WHERE id = 1");
    $pdo->exec('PRAGMA user_version = 1');
    sqlite_upgrade($pdo);
    $check((int) val("SELECT COUNT(*) FROM uy_people WHERE ad = 'Önceki kişi'") === 1, 'Mevcut kişi adları tekilleştirilerek aktarılmadı.');
    q("UPDATE people SET ad = 'Yeni kişi' WHERE ad = 'Önceki kişi'");
    sqlite_upgrade($pdo);
    $check((int) val("SELECT COUNT(*) FROM uy_people WHERE ad = 'Önceki kişi'") === 0, 'Geçiş tekrar çalıştırıldığında eski ad yeniden eklendi.');
    $check(val('SELECT teslim_alan FROM uy_stock_in WHERE id = 1') === 'Önceki kişi', 'Geçmiş kişi adı değişti.');
    $pdo->exec('DROP TABLE stock_out_returns');
    $pdo->exec('PRAGMA user_version = 2');
    sqlite_upgrade($pdo);
    q('INSERT INTO uy_stock_out_returns (stock_out_id, tarih, adet, aciklama) VALUES (?,?,?,?)', [1, '2025-01-05', 1, '']);
    $check(book_stock(1) === 17, 'Stok çıkış iadesi stoka eklenmedi.');
    $stock = stock_report()[0];
    $check((int) $stock['stok'] === 17 && (int) $stock['diger_cikis'] === 1, 'Stok raporunda iade hesaba katılmadı.');
    $sets = export_sets();
    $check(count($sets['Stok Çıkış İadeleri'][1]) === 1, 'Stok çıkış iadesi dışa aktarılmadı.');
    $check((float) financial_summary()['profit'] === 10.0, 'Satış dışı iade kâr/zararı değiştirdi.');
    $check(amount_cents('1.234,56') === 123456 && amount_cents('100.01') === 10001, 'Tutar dönüşümü hatalı.');
    try { amount_cents('10.123'); throw new RuntimeException('Hatalı tutar kabul edildi.'); } catch (DomainException $e) {}
    q('INSERT INTO uy_supplier_purchases (stock_in_id, total_cents, due_date) VALUES (?,?,?)', [1, 10000, '2025-02-01']);
    q('INSERT INTO uy_supplier_payments (stock_in_id, tarih, amount_cents, yontem) VALUES (?,?,?,?)', [1, '2025-01-05', 8000, 'Nakit']);
    q('INSERT INTO uy_supplier_returns (book_id, tarih, tedarikci, adet, birim_fiyat, toplam, aciklama) VALUES (?,?,?,?,?,?,?)', [1, '2025-01-06', '', 1, 30, 30, '']);
    q('INSERT INTO uy_supplier_return_links (return_id, stock_in_id) VALUES (?,?)', [(int) $pdo->lastInsertId(), 1]);
    $supplier = supplier_balance_summary();
    $check($supplier['debt'] === 0 && $supplier['credit'] === 10, 'Tedarikçi iade alacağı hesaplanamadı.');
    q('INSERT INTO uy_customer_returns (sale_item_id, tarih, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?)', [1, '2025-01-06', 1, 10, 5, 10]);
    $pdo->exec('PRAGMA user_version = 4');
    sqlite_upgrade($pdo);
    $check((int) val('SELECT adet FROM uy_legacy_sale_returns WHERE sale_item_id = 1') === 1, 'Eski satış iadesi teslimat geçişinde korunmadı.');
    $before = book_stock(1);
    q('INSERT INTO uy_sale_deliveries (sale_item_id, tarih, adet, goturen_kisi, teslim_edilen) VALUES (?,?,?,?,?)', [1, '2025-01-07', 1, 'Görevli', 'Alıcı']);
    $check(book_stock(1) === $before, 'Satış teslimatı stoğu tekrar düşürdü.');
    $sets = export_sets();
    $check(count($sets['Satış Teslimatları'][1]) === 1 && count($sets['Tedarikçi Ödemeleri'][1]) === 1, 'Yeni hareketler dışa aktarılmadı.');
    q('INSERT INTO uy_sales (id, customer_id, tarih, vade_tarihi, aciklama, toplam) VALUES (?,?,?,?,?,?)', [2, 1, '2025-01-08', '2025-02-08', 'Sipariş notu', 30]);
    q('INSERT INTO uy_sale_items (id, sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?,?)', [2, 2, 1, 1, 10, 5, 10]);
    q('INSERT INTO uy_sale_items (id, sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?,?)', [3, 2, 1, 2, 10, 5, 20]);
    q('INSERT INTO uy_sale_deliveries (sale_item_id, tarih, adet, goturen_kisi, teslim_edilen) VALUES (?,?,?,?,?)', [2, '2025-01-09', 1, 'Görevli', 'Alıcı']);
    q('INSERT INTO uy_users (id, kullanici, sifre_hash, person_id) VALUES (?,?,?,?)', [1, 'test', 'hash', (int) val("SELECT id FROM uy_people WHERE ad = 'Yeni kişi'")]);
    $_SESSION = ['uid' => 1];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    ob_start();
    require __DIR__ . '/../stock_out.php';
    $list = ob_get_clean();
    $check(str_contains($list, 'sale_id=2') && !str_contains($list, 'sale_id=1'), 'Bekleyen satış listesi yanlış.');
    $_GET = ['sale_id' => '2'];
    ob_start();
    require __DIR__ . '/../stock_out.php';
    $detail = ob_get_clean();
    $check(str_contains($detail, 'Sipariş notu') && str_contains($detail, 'Sipariş İçeriği'), 'Sipariş ayrıntıları gösterilmedi.');
    $check(str_contains($detail, 'value="' . $personId . '" selected'), 'Giriş yapan kişinin seçimi gösterilmedi.');
    $check(!str_contains($detail, 'name="sale_item_id"') && !str_contains($detail, 'name="adet"'), 'Teslimat formunda kitap veya adet seçimi bulunuyor.');
    q('INSERT INTO uy_sale_items (id, sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?,?)', [4, 2, 1, 3, 10, 5, 30]);
    $pdo->exec("CREATE TRIGGER reject_delivery BEFORE INSERT ON sale_deliveries WHEN NEW.sale_item_id = 4 BEGIN SELECT RAISE(ABORT, 'test failure'); END");
    try {
        deliver_sale(2, '2025-01-10', 'Görevli', 'Alıcı');
        throw new RuntimeException('Hatalı teslimat başarılı oldu.');
    } catch (PDOException $e) {}
    $check((int) val('SELECT COUNT(*) FROM sale_deliveries WHERE sale_item_id = 3') === 0, 'Başarısız teslimat kısmen kaydedildi.');
    $pdo->exec('DROP TRIGGER reject_delivery');
    $stockBefore = book_stock(1);
    $check(deliver_sale(2, '2025-01-10', 'Görevli', 'Alıcı') === 5, 'Bekleyen tüm kalemler teslim edilmedi.');
    $check((int) val('SELECT SUM(adet) FROM sale_deliveries WHERE sale_item_id = 2') === 1
        && (int) val('SELECT SUM(adet) FROM sale_deliveries WHERE sale_item_id = 3') === 2
        && (int) val('SELECT SUM(adet) FROM sale_deliveries WHERE sale_item_id = 4') === 3, 'Teslimat miktarları yanlış.');
    $check(book_stock(1) === $stockBefore, 'Teslimat stoğu tekrar değiştirdi.');
    try {
        deliver_sale(2, '2025-01-10', 'Görevli', 'Alıcı');
        throw new RuntimeException('Tekrarlanan teslimat kabul edildi.');
    } catch (DomainException $e) {}
    echo "SQLite yükseltmesi ve eski veriler doğrulandı.\n";
} finally {
    unlink($path);
}
