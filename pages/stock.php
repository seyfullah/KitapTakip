<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') {
        $r = row('SELECT book_id FROM stock_in WHERE id = ?', [(int) post('id')]);
        if ($r && book_stock((int) $r['book_id']) - (int) val('SELECT adet FROM stock_in WHERE id = ?', [(int) post('id')]) < 0) flash('Silinirse stok eksiye düşer; silinemedi.');
        else { q('DELETE FROM stock_in WHERE id = ?', [(int) post('id')]); flash('Stok girişi silindi.'); after_write(); }
    } elseif (post('act') === 'out') {
        $bid = (int) post('book_id');
        $cid = (int) post('customer_id');
        $adet = (int) post('adet');
        $b = row('SELECT koli_adedi FROM books WHERE id = ?', [$bid]);
        if (!$adet && post('koli') !== '' && $b) $adet = (int) round(dec(post('koli')) * $b['koli_adedi']);
        if (!$bid || !$cid || !$adet || post('alan_kisi') === '' || post('teslim_yeri') === '') {
            flash('Kitap, müşteri, teslim alan kişi, teslim yeri ve pozitif adet/koli zorunludur.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if (!row('SELECT id FROM books WHERE id = ? FOR UPDATE', [$bid])) throw new DomainException('Kitap bulunamadı.');
                $available = book_stock($bid);
                if ($adet > $available) throw new DomainException("Stok yetersiz (mevcut: $available adet).");
                q('INSERT INTO stock_out (book_id, customer_id, tarih, adet, koli, alan_kisi, teslim_yeri, aciklama) VALUES (?,?,?,?,?,?,?,?)',
                    [$bid, $cid, post('tarih', date('Y-m-d')), $adet, round($adet / max(1, (int) $b['koli_adedi']), 2), post('alan_kisi'), post('teslim_yeri'), post('aciklama')]);
                $pdo->commit();
                flash("Stok çıkışı kaydedildi ($adet adet).");
                after_write();
                redirect('?p=stock');
            } catch (DomainException $e) {
                $pdo->rollBack();
                flash($e->getMessage());
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
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
<div class="form-grid no-print">
  <form method="post" class="card entry-form">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <input type="hidden" name="act" value="in">
    <h2>Stok Girişi</h2>
    <label>Kitap<?= select('book_id', book_opts(true), '', 'required') ?></label>
    <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
    <div class="field-row"><label>Adet<input type="number" name="adet" min="1"></label><label>veya Koli<input name="koli" inputmode="decimal"></label></div>
    <label>Açıklama<input name="aciklama"></label>
    <button>Stok Ekle</button>
  </form>
  <form method="post" class="card entry-form" id="stock-out-form">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <input type="hidden" name="act" value="out">
    <h2>Stok Çıkışı / Teslimat</h2>
    <label>Kitap<?= select('book_id', book_opts(true), '', 'required') ?></label>
    <label>Okul / Kırtasiye
      <select name="customer_id" id="out-customer" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad, tip, adres FROM customers ORDER BY ad') as $c) echo '<option value="' . h($c['id']) . '" data-address="' . h($c['adres']) . '">' . h($c['ad'] . ' (' . $c['tip'] . ')') . '</option>'; ?></select>
    </label>
    <label>Teslim Alan Kişi<input name="alan_kisi" required></label>
    <label>Teslim Yeri / Adres<input name="teslim_yeri" id="out-address" required></label>
    <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
    <div class="field-row"><label>Adet<input type="number" name="adet" min="1"></label><label>veya Koli<input name="koli" inputmode="decimal"></label></div>
    <label>Açıklama<input name="aciklama"></label>
    <button>Çıkışı Kaydet</button>
  </form>
</div>
<script>
document.getElementById('out-customer').addEventListener('change', e => {
  const address = document.getElementById('out-address');
  if (!address.value) address.value = e.target.selectedOptions[0].dataset.address || '';
});
</script>
<h2>Güncel Stok</h2>
<?php
$data = [];
foreach (stock_report() as $s) $data[] = ['<span class="' . ($s['stok'] <= $s['kritik_stok'] ? 'crit' : '') . '">' . h($s['kod']) . '</span>', $s['ad'], $s['yayinevi'], $s['giris'], $s['satilan'], $s['diger_cikis'], $s['stok'], $s['koli_adedi'] > 0 ? round($s['stok'] / $s['koli_adedi'], 1) : '', $s['kritik_stok'], $s['stok'] <= $s['kritik_stok'] ? '<span class="crit">KRİTİK</span>' : 'Yeterli'];
table(['Kod', 'Kitap', 'Yayınevi', 'Giriş', 'Satılan', 'Diğer Çıkış', 'Stok', 'Koli', 'Kritik', 'Durum'], $data, ['int' => [3, 4, 5, 6], 'raw' => [0 => true, 9 => true], 'totals' => [3, 4, 5, 6]]);
echo '<h2>Son Stok Girişleri</h2>';
$data = [];
foreach (rows('SELECT i.*, b.kod, b.ad FROM stock_in i JOIN books b ON b.id = i.book_id ORDER BY i.tarih DESC, i.id DESC LIMIT 50') as $i)
    $data[] = [dtr($i['tarih']), $i['kod'] . ' - ' . $i['ad'], $i['adet'], $i['aciklama'], '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $i['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Kitap', 'Adet', 'Açıklama', ''], $data, ['int' => [2], 'raw' => [4 => true]]);
echo '<h2>Son Teslimat / Stok Çıkışları</h2>';
$data = [];
foreach (rows('SELECT o.*, b.kod, b.ad kitap, c.ad musteri FROM stock_out o JOIN books b ON b.id = o.book_id JOIN customers c ON c.id = o.customer_id ORDER BY o.tarih DESC, o.id DESC LIMIT 50') as $o)
    $data[] = [dtr($o['tarih']), $o['kod'] . ' - ' . $o['kitap'], $o['musteri'], $o['alan_kisi'], $o['teslim_yeri'], $o['adet'], $o['koli'] + 0, $o['aciklama']];
table(['Tarih', 'Kitap', 'Müşteri', 'Teslim Alan', 'Teslim Yeri', 'Adet', 'Koli', 'Açıklama'], $data, ['int' => [5]]);
layout_foot();
