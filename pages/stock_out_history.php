<?php
layout_head('Stok Çıkış Hareketleri', 'stock_out_history');
$data = [];
foreach (rows('SELECT o.*, b.kod, b.ad kitap, c.ad musteri FROM stock_out o JOIN books b ON b.id = o.book_id JOIN customers c ON c.id = o.customer_id ORDER BY o.tarih DESC, o.id DESC LIMIT 100') as $o) {
    $data[] = [dtr($o['tarih']), $o['kod'] . ' - ' . $o['kitap'], $o['musteri'], $o['goturen_kisi'], $o['adet'], $o['koli'] + 0, $o['aciklama']];
}
table(['Tarih', 'Kitap', 'Müşteri', 'Götüren Kişi', 'Adet', 'Koli', 'Açıklama'], $data, ['int' => [4]]);
layout_foot();
