<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') { q('DELETE FROM payments WHERE id = ?', [(int) post('id')]); flash('Tahsilat silindi.'); }
    else {
        $tutar = dec(post('tutar'));
        if (!(int) post('customer_id') || $tutar <= 0) flash('Müşteri ve pozitif tutar zorunludur.');
        else { q('INSERT INTO payments (customer_id, tarih, tutar, yontem, aciklama) VALUES (?,?,?,?,?)', [(int) post('customer_id'), post('tarih', date('Y-m-d')), $tutar, post('yontem'), post('aciklama')]); flash('Tahsilat kaydedildi.'); }
    }
    after_write(); redirect('?p=payments');
}
layout_head('Tahsilat Girişi', 'payments'); ?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Müşteri<?= select('customer_id', customer_opts(true), get('customer_id'), 'required') ?></label>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Tutar (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Yöntem<?= select('yontem', array_combine(['Nakit', 'Havale/EFT', 'Çek', 'Senet', 'Kredi Kartı'], ['Nakit', 'Havale/EFT', 'Çek', 'Senet', 'Kredi Kartı'])) ?></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>Kaydet</button>
</form>
<?php
$data = [];
foreach (rows('SELECT p.*, c.ad FROM payments p JOIN customers c ON c.id = p.customer_id ORDER BY p.tarih DESC, p.id DESC LIMIT 200') as $r)
    $data[] = [dtr($r['tarih']), $r['ad'], $r['tutar'], $r['yontem'], $r['aciklama'], '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $r['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Müşteri', 'Tutar', 'Yöntem', 'Açıklama', ''], $data, ['money' => [2], 'totals' => [2], 'raw' => [5 => true]]);
layout_foot();
