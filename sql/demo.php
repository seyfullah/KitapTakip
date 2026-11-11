<?php
// Demo veri yalnızca boş bir uygulama veritabanına eklenir.
$demoTables = ['uy_people', 'uy_books', 'uy_customers', 'uy_stock_in', 'uy_supplier_purchases', 'uy_supplier_payments',
    'uy_stock_out', 'uy_stock_out_returns', 'uy_sales', 'uy_sale_items', 'uy_sale_deliveries', 'uy_legacy_sale_returns',
    'uy_customer_returns', 'uy_supplier_returns', 'uy_supplier_return_links', 'uy_payments', 'uy_expenses', 'uy_donations',
    'uy_loans', 'uy_loan_payments', 'uy_notification_log'];
foreach ($demoTables as $table) {
    if ((int) val("SELECT COUNT(*) FROM $table") > 0) {
        echo "Veritabanında kayıt var; örnek veriler eklenmedi.\n";
        return;
    }
}
$pdo = db();
$pdo->beginTransaction();
try {
    $ago = static fn(int $days): string => date('Y-m-d', strtotime("-$days days"));
    $people = ['Demo kullanıcısı', 'Ayşe Örnek', 'Mehmet Örnek'];
    $personIds = [];
    foreach ($people as $person) {
        q('INSERT INTO uy_people (ad) VALUES (?)', [$person]);
        $personIds[] = (int) $pdo->lastInsertId();
    }
    $users = rows('SELECT id FROM uy_users WHERE kullanici = ?', [$u]);
    $userId = (int) $users[0]['id'];
    q('UPDATE uy_users SET person_id = ? WHERE id = ?', [$personIds[0], $userId]);
    for ($i = 1; $i < 3; $i++) {
        $username = 'demo_' . bin2hex(random_bytes(8));
        q('INSERT INTO uy_users (kullanici, sifre_hash, person_id) VALUES (?,?,?)', [$username, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $personIds[$i]]);
    }
    $bookIds = [];
    foreach ([['MAT5', 'Matematik 5', 90, 150, 100], ['TRK6', 'Türkçe 6', 95, 170, 80], ['FEN7', 'Fen Bilimleri 7', 100, 180, 60]] as $book) {
        q('INSERT INTO uy_books (kod, ad, alis_fiyati, satis_fiyati, kritik_stok) VALUES (?,?,?,?,?)', $book);
        $bookIds[] = (int) $pdo->lastInsertId();
    }
    $customerIds = [];
    foreach ([['Okul', 'Özel Başarı Okulu', 'Ankara', 'Çankaya'], ['Okul', 'Atatürk İlkokulu', 'Ankara', 'Keçiören'], ['Kırtasiye', 'Güneş Kırtasiye', 'İzmir', 'Karşıyaka']] as $customer) {
        q('INSERT INTO uy_customers (tip, ad, il, ilce) VALUES (?,?,?,?)', $customer);
        $customerIds[] = (int) $pdo->lastInsertId();
    }
    foreach ($bookIds as $i => $bookId) {
        $purchasePrice = [90, 95, 100][$i];
        $salePrice = [150, 170, 180][$i];
        q('INSERT INTO uy_stock_in (book_id, tarih, adet, teslim_alan, aciklama) VALUES (?,?,?,?,?)', [$bookId, $ago(90), 1000, $people[$i], 'Demo alış']);
        $stockId = (int) $pdo->lastInsertId();
        q('INSERT INTO uy_supplier_purchases (stock_in_id, total_cents, due_date) VALUES (?,?,?)', [$stockId, $purchasePrice * 1000 * 100, $ago(30)]);
        q('INSERT INTO uy_supplier_payments (stock_in_id, tarih, amount_cents, yontem, aciklama) VALUES (?,?,?,?,?)', [$stockId, $ago(40), 1000000, 'Havale/EFT', 'Demo ödeme']);
        q('INSERT INTO uy_supplier_returns (book_id, tarih, tedarikci, adet, birim_fiyat, toplam, aciklama) VALUES (?,?,?,?,?,?,?)', [$bookId, $ago(35), '', 2, $purchasePrice, 2 * $purchasePrice, 'Demo iade']);
        q('INSERT INTO uy_supplier_return_links (return_id, stock_in_id) VALUES (?,?)', [(int) $pdo->lastInsertId(), $stockId]);
        insert_stock_out($bookId, $customerIds[$i], $ago(60), 10, $people[$i], 'Eski demo çıkış');
        $outId = (int) $pdo->lastInsertId();
        q('INSERT INTO uy_stock_out_returns (stock_out_id, tarih, adet, aciklama) VALUES (?,?,?,?)', [$outId, $ago(50), 1, 'Demo iade']);
        q('INSERT INTO sales (customer_id, tarih, vade_tarihi, toplam) VALUES (?,?,?,?)', [$customerIds[$i], $ago(45), $ago(15), 50 * $salePrice]);
        $saleId = (int) $pdo->lastInsertId();
        q('INSERT INTO sale_items (sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?)', [$saleId, $bookId, 50, $salePrice, $purchasePrice, 50 * $salePrice]);
        $itemId = (int) $pdo->lastInsertId();
        q('INSERT INTO sale_deliveries (sale_item_id, tarih, adet, goturen_kisi, teslim_edilen) VALUES (?,?,?,?,?)', [$itemId, $ago(40), 20, $people[$i], 'Demo yetkili']);
        q('INSERT INTO customer_returns (sale_item_id, tarih, adet, birim_fiyat, maliyet_birim, toplam, aciklama) VALUES (?,?,?,?,?,?,?)', [$itemId, $ago(30), 1, $salePrice, $purchasePrice, $salePrice, 'Demo iade']);
        q('INSERT INTO legacy_sale_returns (sale_item_id, adet) VALUES (?,?)', [$itemId, 1]);
        q('INSERT INTO payments (customer_id, tarih, tutar, yontem, aciklama) VALUES (?,?,?,?,?)', [$customerIds[$i], $ago(20), 1000, 'Havale/EFT', 'Demo tahsilat']);
        q('INSERT INTO expenses (tarih, kategori, tutar, yontem, aciklama) VALUES (?,?,?,?,?)', [$ago(10), ['Yarışmacı hediyesi', 'Görevli hediyesi', 'Ulaşım'][$i], 100 + 50 * $i, 'Nakit', 'Demo gider']);
        q('INSERT INTO donations (tarih, bagisci, tutar, yontem, aciklama) VALUES (?,?,?,?,?)', [$ago(9), 'Demo bağışçı ' . ($i + 1), 300 + 50 * $i, 'Nakit', 'Demo bağış']);
        q('INSERT INTO loans (tarih, alacakli, tutar, aciklama) VALUES (?,?,?,?)', [$ago(70), 'Demo alacaklı ' . ($i + 1), 3000, 'Kitap alımı']);
        q('INSERT INTO loan_payments (loan_id, tarih, tutar, yontem, aciklama) VALUES (?,?,?,?,?)', [(int) $pdo->lastInsertId(), $ago(20), 500, 'Nakit', 'Demo ödeme']);
        q('INSERT INTO notification_log (customer_id, hafta_baslangici, kanal, durum, hedef, hata) VALUES (?,?,?,?,?,?)', [$customerIds[$i], $ago(100), 'E-posta', 'atlandı', '', 'Demo kaydı; bildirim gönderilmedi.']);
    }
    $pdo->commit();
    echo "Demo veri yüklendi.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
