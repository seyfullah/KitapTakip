<?php require '_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    if (post('act') === 'delete') {
        try { q('DELETE FROM uy_books WHERE id = ?', [$id]); flash('Kitap silindi.'); }
        catch (PDOException $e) { flash('Hareketi olan kitap silinemez.'); }
    } else {
        $v = [post('kod'), post('ad'), dec(post('alis')), dec(post('satis')), (int) post('kritik')];
        try {
            if ($id) q('UPDATE uy_books SET kod=?, ad=?, alis_fiyati=?, satis_fiyati=?, kritik_stok=? WHERE id=?', [...$v, $id]);
            else q('INSERT INTO uy_books (kod, ad, alis_fiyati, satis_fiyati, kritik_stok) VALUES (?,?,?,?,?)', $v);
            flash('Kitap kaydedildi.'); after_write();
        } catch (PDOException $e) { flash('Kayıt başarısız (kitap kodu tekrarlı olabilir).'); }
    }
    redirect('books.php');
}
$e = get('edit') ? row('SELECT * FROM uy_books WHERE id = ?', [(int) get('edit')]) : null;
$e ??= ['id' => '', 'kod' => '', 'ad' => '', 'alis_fiyati' => '', 'satis_fiyati' => '', 'kritik_stok' => 0];
layout_head('Kitap Kartları', 'uy_books'); ?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= h($e['id']) ?>">
  <label>Kitap Kodu<input name="kod" required value="<?= h($e['kod']) ?>"></label>
  <label>Kitap Adı<input name="ad" required value="<?= h($e['ad']) ?>"></label>
  <label>Alış Fiyatı<input name="alis" inputmode="decimal" value="<?= h($e['alis_fiyati']) ?>"></label>
  <label>Satış Fiyatı<input name="satis" inputmode="decimal" value="<?= h($e['satis_fiyati']) ?>"></label>
  <label>Kritik Stok<input name="kritik" type="number" min="0" value="<?= h($e['kritik_stok']) ?>"></label>
  <button>Kaydet</button><?php if ($e['id']) echo '<a class="btn alt" href="uy_books.php">Vazgeç</a>'; ?>
</form>
<?php
$fq = get('q');
$list = rows('SELECT * FROM uy_books WHERE kod LIKE :q OR ad LIKE :q2 ORDER BY ad', ['q' => "%$fq%", 'q2' => "%$fq%"]);
echo '<form class="f no-print"><input type="hidden" name="p" value="uy_books"><label>Ara<input name="q" value="' . h($fq) . '"></label><button>Filtrele</button></form>';
$data = [];
foreach ($list as $b) $data[] = [$b['kod'], $b['ad'], $b['alis_fiyati'], $b['satis_fiyati'], $b['kritik_stok'],
    '<a href="uy_books.php&edit=' . $b['id'] . '">Düzenle</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $b['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Kod', 'Ad', 'Alış', 'Satış', 'Kritik', ''], $data, ['money' => [2, 3], 'raw' => [5 => true]]);
layout_foot();
