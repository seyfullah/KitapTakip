<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') {
        q('DELETE FROM donations WHERE id = ?', [(int) post('id')]);
        flash('Bağış kaydı silindi.');
    } else {
        $amount = dec(post('tutar'));
        if ($amount <= 0) flash('Pozitif bağış tutarı zorunludur.');
        else {
            q('INSERT INTO donations (tarih, bagisci, tutar, yontem, aciklama) VALUES (?,?,?,?,?)',
                [post('tarih', date('Y-m-d')), post('bagisci'), $amount, post('yontem'), post('aciklama')]);
            flash('Bağış kaydedildi.');
        }
    }
    after_write();
    redirect('?p=donations');
}
layout_head('Bağış Kaydı', 'donations'); ?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Bağışçı (isteğe bağlı)<input name="bagisci"></label>
  <label>Tutar (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Yöntem<?= select('yontem', array_combine(['Nakit', 'Havale/EFT', 'Diğer'], ['Nakit', 'Havale/EFT', 'Diğer'])) ?></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>Bağışı Kaydet</button>
</form>
<?php
$data = [];
foreach (rows('SELECT * FROM donations ORDER BY tarih DESC, id DESC LIMIT 300') as $d)
    $data[] = [dtr($d['tarih']), $d['bagisci'], $d['tutar'], $d['yontem'], $d['aciklama'], '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $d['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Bağışçı', 'Tutar', 'Yöntem', 'Açıklama', ''], $data, ['money' => [2], 'totals' => [2], 'raw' => [5 => true]]);
layout_foot();
