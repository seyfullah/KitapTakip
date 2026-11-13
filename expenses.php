<?php require '_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') {
        q('DELETE FROM uy_expenses WHERE id = ?', [(int) post('id')]);
        flash('Gider silindi.');
    } else {
        $amount = dec(post('tutar'));
        if (post('kategori') === '' || $amount <= 0) flash('Gider kategorisi ve pozitif tutar zorunludur.');
        else {
            q('INSERT INTO uy_expenses (tarih, kategori, tutar, yontem, aciklama) VALUES (?,?,?,?,?)',
                [post('tarih', date('Y-m-d')), post('kategori'), $amount, post('yontem'), post('aciklama')]);
            flash('Gider kaydedildi.');
        }
    }
    after_write();
    redirect('expenses.php');
}
layout_head('Gider Kaydı', 'expenses'); ?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Kategori<input name="kategori" list="expense-categories" required></label>
  <datalist id="expense-categories"><?php foreach (['Kira', 'Ulaşım', 'Maaş', 'Fatura', 'Kırtasiye', 'Bakım', 'Yarışmacı hediyesi', 'Görevli hediyesi', 'Diğer'] as $category) echo '<option value="' . h($category) . '">'; ?></datalist>
  <label>Tutar (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Yöntem<?= select('yontem', array_combine(['Nakit', 'Havale/EFT', 'Kart', 'Diğer'], ['Nakit', 'Havale/EFT', 'Kart', 'Diğer'])) ?></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>Gideri Kaydet</button>
</form>
<?php
$data = [];
foreach (rows('SELECT * FROM uy_expenses ORDER BY tarih DESC, id DESC LIMIT 300') as $e)
    $data[] = [dtr($e['tarih']), $e['kategori'], $e['tutar'], $e['yontem'], $e['aciklama'], '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $e['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Kategori', 'Tutar', 'Yöntem', 'Açıklama', ''], $data, ['money' => [2], 'totals' => [2], 'raw' => [5 => true]]);
layout_foot();
