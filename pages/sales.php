<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'delete') {
        $id = (int) post('id');
        if ((int) val('SELECT COUNT(*) FROM uy_customer_returns WHERE sale_item_id IN (SELECT id FROM uy_sale_items WHERE sale_id = ?)', [$id])
            || (int) val('SELECT COUNT(*) FROM uy_sale_deliveries WHERE sale_item_id IN (SELECT id FROM uy_sale_items WHERE sale_id = ?)', [$id])) {
            flash('İadesi veya teslimatı bulunan satış silinemez.');
        } else {
            q('DELETE FROM uy_sales WHERE id = ?', [$id]);
            flash('Satış silindi, stok geri eklendi.'); after_write();
        }
        redirect('?p=sales');
    }
    $cid = (int) post('customer_id'); $tarih = post('tarih', date('Y-m-d'));
    $vade = post('vade_tarihi') ?: date('Y-m-d', strtotime($tarih . ' +' . (int) post('vade_gun', '0') . ' days'));
    $lines = []; $need = []; $err = '';
    foreach ($_POST['book_id'] ?? [] as $i => $bid) {
        $bid = (int) $bid; if (!$bid) continue;
        $b = row('SELECT * FROM uy_books WHERE id = ?', [$bid]); if (!$b) continue;
        $rawAdet = trim((string) ($_POST['adet'][$i] ?? ''));
        if ($rawAdet === '' || $rawAdet === '0') continue;
        try { $adet = stock_quantity($rawAdet); }
        catch (DomainException $e) { flash($e->getMessage()); redirect('?p=uy_sales'); }
        $fiyat = trim((string) ($_POST['fiyat'][$i] ?? '')) !== '' ? dec($_POST['fiyat'][$i]) : (float) $b['satis_fiyati'];
        $lines[] = [$bid, $adet, $fiyat, (float) $b['alis_fiyati'], round($adet * $fiyat, 2)];
        $need[$bid] = ($need[$bid] ?? 0) + $adet;
    }
    if (!$cid || !$lines) $err = 'Müşteri ve en az bir kitap satırı zorunludur.';
    if ($err) { flash(trim($err)); redirect('?p=uy_sales'); }
    $pdo = db(); $pdo->beginTransaction();
    try {
        $bookIds = array_keys($need);
        sort($bookIds, SORT_NUMERIC);
        foreach ($bookIds as $bookId) q('SELECT id FROM uy_books WHERE id = ?' . (cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE'), [$bookId]);
        foreach ($need as $bid => $n) {
            $available = book_stock((int) $bid);
            if ($n > $available) throw new DomainException(val('SELECT ad FROM uy_books WHERE id = ?', [$bid]) . " için stok yetersiz (stok: $available).");
        }
        q('INSERT INTO uy_sales (customer_id, tarih, vade_tarihi, aciklama, toplam, durum) VALUES (?,?,?,?,?,?)', [$cid, $tarih, $vade, post('aciklama'), array_sum(array_column($lines, 4)), 'Yeni']);
        $sid = (int) $pdo->lastInsertId();
        foreach ($lines as $l) q('INSERT INTO uy_sale_items (sale_id, book_id, adet, birim_fiyat, maliyet_birim, toplam) VALUES (?,?,?,?,?,?)', [$sid, ...$l]);
        $pdo->commit();
    } catch (DomainException $e) {
        $pdo->rollBack(); flash($e->getMessage()); redirect('?p=sales');
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    flash("Satış #$sid kaydedildi, stoktan düşüldü."); after_write();
    redirect('?p=sales');
}

layout_head('Satış Kayıt', 'sales');
$uy_books = rows('SELECT b.id, b.kod, b.ad, b.satis_fiyati FROM uy_books b ORDER BY b.ad');
$stock = array_column(stock_report(), 'stok', 'id');
$opts = '<option value="">— Kitap —</option>';
foreach ($uy_books as $b) $opts .= '<option value="' . $b['id'] . '" data-fiyat="' . $b['satis_fiyati'] . '">' . h($b['kod'] . ' - ' . $b['ad'] . ' (stok ' . $stock[$b['id']] . ')') . '</option>';
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
  <div class="table-wrap"><table class="lines"><thead><tr><th>Kitap</th><th>Adet</th><th>Birim Fiyat</th><th>Tutar</th></tr></thead><tbody>
  <?php for ($i = 0; $i < 6; $i++) echo '<tr><td><select name="book_id[]" class="bk">' . $opts . '</select></td><td><input type="number" min="0" name="adet[]" class="ad"></td><td><input name="fiyat[]" class="fy" inputmode="decimal"></td><td class="r tt">0,00</td></tr>'; ?>
  </tbody><tfoot><tr class="tot"><td colspan="3">Genel Toplam</td><td class="r" id="gt">0,00 TL</td></tr></tfoot></table></div>
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
layout_foot();
