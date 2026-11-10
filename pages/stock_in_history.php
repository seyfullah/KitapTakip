<?php
require __DIR__ . '/_stock_action.php';
layout_head('Stok Giriş Hareketleri', 'stock_in_history');
$data = [];
foreach (rows('SELECT i.*, b.kod, b.ad FROM stock_in i JOIN books b ON b.id = i.book_id ORDER BY i.tarih DESC, i.id DESC LIMIT 100') as $i) {
    $data[] = [dtr($i['tarih']), $i['kod'] . ' - ' . $i['ad'], $i['adet'], $i['teslim_alan'], $i['aciklama'],
        '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $i['id'] . '"><input type="hidden" name="act" value="delete_in"><button class="danger">Sil</button></form>'];
}
table(['Tarih', 'Kitap', 'Adet', 'Teslim Alan', 'Açıklama', ''], $data, ['int' => [2], 'raw' => [5 => true]]);
layout_foot();
