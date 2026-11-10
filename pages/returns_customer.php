<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = (int) post('sale_item_id');
    $item = row('SELECT si.*, s.tarih satis_tarihi, s.id satis_no, c.ad musteri
        FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id
        WHERE si.id = ?', [$itemId]);
    try {
        if (!$item) throw new DomainException('İade edilecek satış satırını seçin.');
        $units = stock_quantity(post('adet'));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            $locked = row('SELECT adet FROM sale_items WHERE id = ?' . $lock, [$itemId]);
            if (!$locked) throw new DomainException('Satış satırı bulunamadı.');
            $returned = (int) val('SELECT COALESCE(SUM(adet),0) FROM customer_returns WHERE sale_item_id = ?', [$itemId]);
            $remaining = (int) $locked['adet'] - $returned;
            if ($units > $remaining) throw new DomainException("İade miktarı kalan satış adedini aşamaz ($remaining adet).");
            $date = post('tarih', date('Y-m-d'));
            if ($date < $item['satis_tarihi']) throw new DomainException('İade tarihi satış tarihinden önce olamaz.');
            $total = round($units * (float) $item['birim_fiyat'], 2);
            q('INSERT INTO customer_returns (sale_item_id, tarih, adet, birim_fiyat, maliyet_birim, toplam, aciklama) VALUES (?,?,?,?,?,?,?)',
                [$itemId, $date, $units, $item['birim_fiyat'], $item['maliyet_birim'], $total, post('aciklama')]);
            $pdo->commit();
            flash("Müşteri iadesi kaydedildi ($units adet, " . money($total) . '). Stok ve cari bakiye güncellendi.');
            after_write();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=returns_customer');
}

layout_head('Müşteri İadesi', 'returns_customer');
$items = rows("SELECT si.id, s.id satis_no, s.tarih satis_tarihi, c.ad musteri, b.kod, b.ad kitap,
        si.adet, si.birim_fiyat, COALESCE(r.adet,0) iade
    FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id
    LEFT JOIN (SELECT sale_item_id, SUM(adet) adet FROM customer_returns GROUP BY sale_item_id) r ON r.sale_item_id = si.id
    WHERE si.adet > COALESCE(r.adet,0) ORDER BY s.tarih DESC, s.id DESC");
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Satış / Kitap<select name="sale_item_id" required><option value="">— Seçiniz —</option><?php foreach ($items as $i) { $remaining = (int) $i['adet'] - (int) $i['iade']; echo '<option value="' . h($i['id']) . '">' . h('#' . $i['satis_no'] . ' · ' . $i['musteri'] . ' · ' . $i['kod'] . ' - ' . $i['kitap'] . ' · kalan ' . $remaining . ' adet · ' . money($i['birim_fiyat']) . '/adet') . '</option>'; } ?></select></label>
  <label>İade Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>İadeyi Kaydet</button>
</form>
<p class="muted">İade bedeli satıştaki birim fiyattan hesaplanır; müşterinin cari bakiyesinden düşülür ve ürün stoğa eklenir. Satış dışındaki teslimatlar için <a href="?p=returns_stock_out">Stok Çıkış İadesi</a> ekranını kullanın.</p>
<?php
$data = [];
foreach (rows('SELECT cr.*, s.id satis_no, c.ad musteri, b.kod, b.ad kitap FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id ORDER BY cr.tarih DESC, cr.id DESC LIMIT 200') as $r)
    $data[] = [dtr($r['tarih']), '#' . $r['satis_no'], $r['musteri'], $r['kod'] . ' - ' . $r['kitap'], $r['adet'], $r['toplam'], $r['aciklama']];
table(['Tarih', 'Satış', 'Müşteri', 'Kitap', 'Adet', 'İade Tutarı', 'Açıklama'], $data, ['int' => [4], 'money' => [5], 'totals' => [4, 5]]);
layout_foot();
