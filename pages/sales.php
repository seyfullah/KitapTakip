<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') {
        $id = (int) post('id');
        if ((int) val('SELECT COUNT(*) FROM customer_returns WHERE sale_item_id IN (SELECT id FROM sale_items WHERE sale_id = ?)', [$id])) {
            flash('İadesi bulunan satış silinemez; önce iade kaydını gözden geçirin.');
        } else {
            q('DELETE FROM sales WHERE id = ?', [$id]);
            flash('Satış silindi, stok geri eklendi.'); after_write();
        }
        redirect('?p=sales');
    }
    $cid = (int) post('customer_id'); $tarih = post('tarih', date('Y-m-d'));
    $vade = post('vade_tarihi') ?: date('Y-m-d', strtotime($tarih . ' +' . (int) post('vade_gun', '0') . ' days'));
    $lines = []; $need = []; $err = '';
    foreach ($_POST['book_id'] ?? [] as $i => $bid) {
        $bid = (int) $bid; if (!$bid) continue;
        $b = row('SELECT * FROM books WHERE id = ?', [$bid]); if (!$b) continue;
        $rawAdet = trim((string) ($_POST['adet'][$i] ?? ''));
        if ($rawAdet === '' || $rawAdet === '0') continue;
        try { $adet = stock_quantity($rawAdet); }
        catch (DomainException $e) { flash($e->getMessage()); redirect('?p=sales'); }
        $fiyat = trim((string) ($_POST['fiyat'][$i] ?? '')) !== '' ? dec($_POST['fiyat'][$i]) : (float) $b['satis_fiyati'];
        $lines[] = [$bid, $adet, $fiyat, (float) $b['alis_fiyati'], round($adet * $fiyat, 2)];
        $need[$bid] = ($need[$bid] ?? 0) + $adet;
    }
    if (!$cid || !$lines) $err = 'Müşteri ve en az bir kitap satırı zorunludur.';
    if ($err) { flash(trim($err)); redirect('?p=sales'); }
    $pdo = db(); $pdo->beginTransaction();
    try {
        $bookIds = array_keys($need);
        sort($bookIds, SORT_NUMERIC);
        foreach ($bookIds as $bookId) q('SELECT id FROM books WHERE id = ?' . (cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE'), [$bookId]);
        foreach ($need as $bid => $n) {
            $available = book_stock((int) $bid);
            if ($n > $available) throw new DomainException(val('SELECT ad FROM books WHERE id = ?', [$bid]) . " için stok yetersiz (stok: $available).");
        }
        q('INSERT INTO sales (customer_id, tarih, vade_tarihi, aciklama, toplam) VALUES (?,?,?,?,?)', [$cid, $tarih, $vade, post('aciklama'), array_sum(array_column($lines, 4))]);
        $sid = (int) $pdo->lastInsertId();
        foreach ($lines as $l) q('INSERT INTO sale_items (sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?)', [$sid, ...$l]);
        $pdo->commit();
    } catch (DomainException $e) {
        $pdo->rollBack(); flash($e->getMessage()); redirect('?p=sales');
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    flash("Satış #$sid kaydedildi, stoktan düşüldü."); after_write();
    redirect('?p=sales');
}

layout_head('Satış ve Dağıtım', 'sales');
$books = rows('SELECT b.id, b.kod, b.ad, b.satis_fiyati FROM books b ORDER BY b.ad');
$stock = array_column(stock_report(), 'stok', 'id');
$opts = '<option value="">— Kitap —</option>';
foreach ($books as $b) $opts .= '<option value="' . $b['id'] . '" data-fiyat="' . $b['satis_fiyati'] . '">' . h($b['kod'] . ' - ' . $b['ad'] . ' (stok ' . $stock[$b['id']] . ')') . '</option>';
?>
<form method="post" class="card no-print" id="sf">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <div class="f" style="border:0;padding:0;margin-bottom:8px">
    <label>Okul / Kırtasiye<?= select('customer_id', customer_opts(true), '', 'required') ?></label>
    <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
    <label>Vade (gün)<input type="number" name="vade_gun" value="30" min="0" style="width:90px"></label>
    <label>veya Vade Tarihi<input type="date" name="vade_tarihi"></label>
    <label>Açıklama<input name="aciklama"></label>
  </div>
  <table class="lines"><thead><tr><th>Kitap</th><th>Adet</th><th>Birim Fiyat</th><th>Tutar</th></tr></thead><tbody>
  <?php for ($i = 0; $i < 6; $i++) echo '<tr><td><select name="book_id[]" class="bk">' . $opts . '</select></td><td><input type="number" min="0" name="adet[]" class="ad"></td><td><input name="fiyat[]" class="fy" inputmode="decimal"></td><td class="r tt">0,00</td></tr>'; ?>
  </tbody><tfoot><tr class="tot"><td colspan="3">Genel Toplam</td><td class="r" id="gt">0,00 TL</td></tr></tfoot></table>
  <p class="muted">Fiyat boşsa kitap kartındaki satış fiyatı kullanılır.</p>
  <button>Satışı Kaydet</button>
</form>
<script>
const fmt = n => n.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const pn = s => parseFloat(String(s).replace(/\./g, '').replace(',', '.')) || 0;
function calc() {
  let g = 0;
  document.querySelectorAll('#sf tbody tr').forEach(tr => {
    const o = tr.querySelector('.bk').selectedOptions[0];
    const ad = +tr.querySelector('.ad').value;
    const fy = tr.querySelector('.fy').value !== '' ? pn(tr.querySelector('.fy').value) : +(o?.dataset.fiyat || 0);
    const t = ad * fy; g += t; tr.querySelector('.tt').textContent = fmt(t);
  });
  document.getElementById('gt').textContent = fmt(g) + ' TL';
}
document.querySelectorAll('#sf tbody tr').forEach(tr => {
  tr.querySelector('.bk').addEventListener('change', e => { tr.querySelector('.fy').value = e.target.selectedOptions[0].dataset.fiyat || ''; calc(); });
  tr.querySelector('.ad').addEventListener('input', calc);
  tr.querySelector('.fy').addEventListener('input', calc);
});
</script>
<?php
$f = ['from' => get('from'), 'to' => get('to'), 'cid' => (int) get('customer_id'), 'bid' => (int) get('book_id')];
echo '<form class="f no-print"><input type="hidden" name="p" value="sales"><label>Başlangıç<input type="date" name="from" value="' . h($f['from']) . '"></label><label>Bitiş<input type="date" name="to" value="' . h($f['to']) . '"></label><label>Müşteri' . select('customer_id', customer_opts(true), $f['cid']) . '</label><label>Kitap' . select('book_id', book_opts(true), $f['bid']) . '</label><button>Filtrele</button></form>';
$w = ' WHERE 1=1'; $p = [];
if ($f['from']) { $w .= ' AND s.tarih >= :f'; $p['f'] = $f['from']; }
if ($f['to']) { $w .= ' AND s.tarih <= :t'; $p['t'] = $f['to']; }
if ($f['cid']) { $w .= ' AND s.customer_id = :c'; $p['c'] = $f['cid']; }
if ($f['bid']) { $w .= ' AND si.book_id = :b'; $p['b'] = $f['bid']; }
$data = [];
foreach (rows("SELECT s.id, s.tarih, s.vade_tarihi, c.ad musteri, c.tip, b.ad kitap, si.adet, si.birim_fiyat, si.toplam
    FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id $w ORDER BY s.tarih DESC, s.id DESC LIMIT 500", $p) as $r)
    $data[] = ['#' . $r['id'], dtr($r['tarih']), dtr($r['vade_tarihi']), $r['musteri'] . ' (' . $r['tip'] . ')', $r['kitap'], $r['adet'], $r['birim_fiyat'], $r['toplam'],
        '<form method="post" onsubmit="return confirm(\'Satış #' . $r['id'] . ' tümüyle silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $r['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['No', 'Tarih', 'Vade', 'Müşteri', 'Kitap', 'Adet', 'Birim Fiyat', 'Tutar', ''], $data, ['int' => [5], 'money' => [6, 7], 'totals' => [5, 7], 'raw' => [8 => true]]);
layout_foot();
