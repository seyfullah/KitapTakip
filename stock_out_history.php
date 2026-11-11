<?php require '_init.php';

layout_head('Teslimat ve Eski Çıkış Hareketleri', 'stock_out_history');
$deliveries = [];
foreach (rows('SELECT d.*, s.id satis_no, c.ad musteri, b.kod, b.ad kitap FROM uy_sale_deliveries d
    JOIN uy_sale_items si ON si.id = d.sale_item_id JOIN uy_sales s ON s.id = si.sale_id
    JOIN uy_customers c ON c.id = s.customer_id JOIN uy_books b ON b.id = si.book_id ORDER BY d.tarih DESC, d.id DESC LIMIT 100') as $d) {
    $deliveries[] = [dtr($d['tarih']), '#' . $d['satis_no'], $d['kod'] . ' - ' . $d['kitap'], $d['musteri'], $d['goturen_kisi'], $d['teslim_edilen'], $d['adet']];
}
echo '<h2>Satış Teslimatları</h2>';
table(['Tarih', 'Satış', 'Kitap', 'Müşteri', 'Götüren', 'Teslim Edilen', 'Adet'], $deliveries, ['int' => [6]]);
echo '<h2>Eski Bağımsız Çıkışlar</h2>';
$data = [];
foreach (rows('SELECT o.*, b.kod, b.ad kitap, c.ad musteri FROM uy_stock_out o JOIN uy_books b ON b.id = o.book_id JOIN uy_customers c ON c.id = o.customer_id ORDER BY o.tarih DESC, o.id DESC LIMIT 100') as $o) {
    $data[] = [dtr($o['tarih']), $o['kod'] . ' - ' . $o['kitap'], $o['musteri'], $o['goturen_kisi'], $o['adet'], $o['aciklama']];
}
table(['Tarih', 'Kitap', 'Müşteri', 'Götüren Kişi', 'Adet', 'Açıklama'], $data, ['int' => [4]]);
layout_foot();
