<?php
// Demo veri: php install.php admin admin123 --demo
if ((int) val('SELECT COUNT(*) FROM customers') > 0) {
    echo "Demo verisi zaten var; mevcut kayıtlar korunuyor.\n";
    return;
}
$today = date('Y-m-d');
$bk = [['MAT5', 'Matematik 5', 'Başarı Yayınları', '5. Sınıf', 90, 150, 50, 100], ['TRK6', 'Türkçe 6', 'Başarı Yayınları', '6. Sınıf', 95, 170, 40, 80], ['FEN7', 'Fen Bilimleri 7', 'Yıldız Yayınları', '7. Sınıf', 100, 180, 40, 60]];
$ignore = cfg('app_storage') === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
foreach ($bk as $b) q("$ignore INTO books (kod, ad, yayinevi, sinif, alis_fiyati, satis_fiyati, koli_adedi, kritik_stok) VALUES (?,?,?,?,?,?,?,?)", $b);
$cs = [['Okul', 'Özel Başarı Okulu', 'Ankara', 'Çankaya'], ['Okul', 'Atatürk İlkokulu', 'Ankara', 'Keçiören'], ['Kırtasiye', 'Güneş Kırtasiye', 'İzmir', 'Karşıyaka']];
foreach ($cs as $c) q('INSERT INTO customers (tip, ad, il, ilce, telefon) VALUES (?,?,?,?,?)', [...$c, '0555 000 00 00']);
foreach (rows('SELECT id FROM books') as $b) q('INSERT INTO stock_in (book_id, tarih, adet, aciklama) VALUES (?,?,?,?)', [$b['id'], date('Y-m-d', strtotime('-90 days')), 1000, 'Açılış']);
$mk = function (string $cust, string $kod, int $adet, int $ago, int $vadeGun) {
    $c = (int) val('SELECT id FROM customers WHERE ad = ?', [$cust]); $b = row('SELECT * FROM books WHERE kod = ?', [$kod]);
    $t = date('Y-m-d', strtotime("-$ago days")); $tot = $adet * $b['satis_fiyati'];
    q('INSERT INTO sales (customer_id, tarih, vade_tarihi, toplam) VALUES (?,?,?,?)', [$c, $t, date('Y-m-d', strtotime("$t +$vadeGun days")), $tot]);
    q('INSERT INTO sale_items (sale_id, book_id, adet, koli, birim_fiyat, toplam) VALUES (?,?,?,?,?,?)', [db()->lastInsertId(), $b['id'], $adet, round($adet / $b['koli_adedi'], 2), $b['satis_fiyati'], $tot]);
};
$mk('Özel Başarı Okulu', 'MAT5', 250, 60, 30); $mk('Özel Başarı Okulu', 'TRK6', 150, 20, 30);
$mk('Atatürk İlkokulu', 'FEN7', 100, 45, 30); $mk('Güneş Kırtasiye', 'MAT5', 80, 10, 45);
q('INSERT INTO payments (customer_id, tarih, tutar, yontem) VALUES (?,?,?,?)', [(int) val('SELECT id FROM customers WHERE ad = ?', ['Özel Başarı Okulu']), $today, 30000, 'Havale/EFT']);
echo "Demo veri yüklendi.\n";
