<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    if (post('act') === 'delete') {
        try { q('DELETE FROM books WHERE id = ?', [$id]); flash('Kitap silindi.'); }
        catch (PDOException $e) { flash('Hareketi olan kitap silinemez.'); }
    } else {
        $v = [post('kod'), post('ad'), post('yayinevi'), post('sinif'), dec(post('alis')), dec(post('satis')), max(1, (int) post('koli', '1')), (int) post('kritik')];
        try {
            if ($id) q('UPDATE books SET kod=?, ad=?, yayinevi=?, sinif=?, alis_fiyati=?, satis_fiyati=?, koli_adedi=?, kritik_stok=? WHERE id=?', [...$v, $id]);
            else q('INSERT INTO books (kod, ad, yayinevi, sinif, alis_fiyati, satis_fiyati, koli_adedi, kritik_stok) VALUES (?,?,?,?,?,?,?,?)', $v);
            flash('Kitap kaydedildi.'); after_write();
        } catch (PDOException $e) { flash('Kayıt başarısız (kitap kodu tekrarlı olabilir).'); }
    }
    redirect('?p=books');
}
$e = get('edit') ? row('SELECT * FROM books WHERE id = ?', [(int) get('edit')]) : null;
$e ??= ['id' => '', 'kod' => '', 'ad' => '', 'yayinevi' => '', 'sinif' => '', 'alis_fiyati' => '', 'satis_fiyati' => '', 'koli_adedi' => 1, 'kritik_stok' => 0];
layout_head('Kitap Kartları', 'books'); ?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= h($e['id']) ?>">
  <label>Kitap Kodu<input name="kod" required value="<?= h($e['kod']) ?>"></label>
  <label>Kitap Adı<input name="ad" required value="<?= h($e['ad']) ?>"></label>
  <label>Yayınevi<input name="yayinevi" list="yl" value="<?= h($e['yayinevi']) ?>"></label>
  <datalist id="yl"><?php foreach (rows('SELECT DISTINCT yayinevi FROM books WHERE yayinevi<>""') as $y) echo '<option value="' . h($y['yayinevi']) . '">'; ?></datalist>
  <label>Sınıf Seviyesi<?= select('sinif', ['' => '—'] + array_combine(array_merge(['Anaokulu'], array_map(fn($i) => "$i. Sınıf", range(1, 12))), array_merge(['Anaokulu'], array_map(fn($i) => "$i. Sınıf", range(1, 12)))), $e['sinif']) ?></label>
  <label>Alış Fiyatı<input name="alis" inputmode="decimal" value="<?= h($e['alis_fiyati']) ?>"></label>
  <label>Satış Fiyatı<input name="satis" inputmode="decimal" value="<?= h($e['satis_fiyati']) ?>"></label>
  <label>Koli Adedi<input name="koli" type="number" min="1" value="<?= h($e['koli_adedi']) ?>"></label>
  <label>Kritik Stok<input name="kritik" type="number" min="0" value="<?= h($e['kritik_stok']) ?>"></label>
  <button>Kaydet</button><?php if ($e['id']) echo '<a class="btn alt" href="?p=books">Vazgeç</a>'; ?>
</form>
<?php
$fq = get('q');
$list = rows('SELECT * FROM books WHERE kod LIKE :q OR ad LIKE :q2 OR yayinevi LIKE :q3 ORDER BY ad', ['q' => "%$fq%", 'q2' => "%$fq%", 'q3' => "%$fq%"]);
echo '<form class="f no-print"><input type="hidden" name="p" value="books"><label>Ara<input name="q" value="' . h($fq) . '"></label><button>Filtrele</button></form>';
$data = [];
foreach ($list as $b) $data[] = [$b['kod'], $b['ad'], $b['yayinevi'], $b['sinif'], $b['alis_fiyati'], $b['satis_fiyati'], $b['koli_adedi'], $b['kritik_stok'],
    '<a href="?p=books&edit=' . $b['id'] . '">Düzenle</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $b['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Kod', 'Ad', 'Yayınevi', 'Sınıf', 'Alış', 'Satış', 'Koli Adedi', 'Kritik', ''], $data, ['money' => [4, 5], 'raw' => [8 => true]]);
layout_foot();
