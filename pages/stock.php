<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') {
        $r = row('SELECT book_id FROM stock_in WHERE id = ?', [(int) post('id')]);
        if ($r && book_stock((int) $r['book_id']) - (int) val('SELECT adet FROM stock_in WHERE id = ?', [(int) post('id')]) < 0) flash('Silinirse stok eksiye düşer; silinemedi.');
        else { q('DELETE FROM stock_in WHERE id = ?', [(int) post('id')]); flash('Stok girişi silindi.'); after_write(); }
    } else {
        $bid = (int) post('book_id'); $adet = (int) post('adet');
        $b = row('SELECT koli_adedi FROM books WHERE id = ?', [$bid]);
        if (!$adet && post('koli') !== '' && $b) $adet = (int) round(dec(post('koli')) * $b['koli_adedi']);
        if ($b && $adet > 0) {
            q('INSERT INTO stock_in (book_id, tarih, adet, aciklama) VALUES (?,?,?,?)', [$bid, post('tarih', date('Y-m-d')), $adet, post('aciklama')]);
            flash("Stok girişi yapıldı ($adet adet)."); after_write();
        } else flash('Kitap ve adet (ya da koli) zorunludur.');
    }
    redirect('?p=stock');
}
layout_head('Stok Girişi ve Güncel Stok', 'stock');
$crit = array_filter(stock_report(), fn($s) => $s['stok'] <= $s['kritik_stok']);
if ($crit) echo '<div class="flash err"><b>⚠ Kritik stok uyarısı:</b> ' . h(implode(', ', array_map(fn($s) => $s['ad'] . ' (' . $s['stok'] . ')', $crit))) . '</div>';
?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Kitap<?= select('book_id', book_opts(true), '', 'required') ?></label>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1"></label>
  <label>veya Koli<input name="koli" inputmode="decimal" size="5"></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>Stok Girişi Ekle</button>
</form>
<h2>Güncel Stok</h2>
<?php
$data = [];
foreach (stock_report() as $s) $data[] = ['<span class="' . ($s['stok'] <= $s['kritik_stok'] ? 'crit' : '') . '">' . h($s['kod']) . '</span>', $s['ad'], $s['yayinevi'], $s['giris'], $s['satilan'], $s['stok'], $s['koli_adedi'] > 0 ? round($s['stok'] / $s['koli_adedi'], 1) : '', $s['kritik_stok'], $s['stok'] <= $s['kritik_stok'] ? '<span class="crit">KRİTİK</span>' : 'Yeterli'];
table(['Kod', 'Kitap', 'Yayınevi', 'Giriş', 'Satılan', 'Stok', 'Koli', 'Kritik', 'Durum'], $data, ['int' => [3, 4, 5], 'raw' => [0 => true, 8 => true], 'totals' => [3, 4, 5]]);
echo '<h2>Son Stok Girişleri</h2>';
$data = [];
foreach (rows('SELECT i.*, b.kod, b.ad FROM stock_in i JOIN books b ON b.id = i.book_id ORDER BY i.tarih DESC, i.id DESC LIMIT 50') as $i)
    $data[] = [dtr($i['tarih']), $i['kod'] . ' - ' . $i['ad'], $i['adet'], $i['aciklama'], '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $i['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Kitap', 'Adet', 'Açıklama', ''], $data, ['int' => [2], 'raw' => [4 => true]]);
layout_foot();
