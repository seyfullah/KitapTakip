<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $outId = (int) post('stock_out_id');
        $units = stock_quantity(post('adet'));
        $date = post('tarih');
        $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$validDate || $validDate->format('Y-m-d') !== $date) throw new DomainException('Geçerli bir iade tarihi girin.');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            $out = row('SELECT id, tarih, adet FROM stock_out WHERE id = ?' . $lock, [$outId]);
            if (!$out) throw new DomainException('İade edilecek stok çıkışını seçin.');
            $returned = (int) val('SELECT COALESCE(SUM(adet),0) FROM stock_out_returns WHERE stock_out_id = ?', [$outId]);
            $remaining = (int) $out['adet'] - $returned;
            if ($units > $remaining) throw new DomainException("İade miktarı kalan çıkış adedini aşamaz ($remaining adet).");
            if ($date < $out['tarih']) throw new DomainException('İade tarihi çıkış tarihinden önce olamaz.');
            q('INSERT INTO stock_out_returns (stock_out_id, tarih, adet, aciklama) VALUES (?,?,?,?)',
                [$outId, $date, $units, post('aciklama')]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash("Stok çıkış iadesi kaydedildi ($units adet). Stok güncellendi.");
        after_write();
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=returns_stock_out');
}

layout_head('Stok Çıkış İadesi', 'returns_stock_out');
$outs = rows('SELECT o.id, o.tarih, o.adet, c.ad musteri, b.kod, b.ad kitap, COALESCE(r.adet,0) iade
    FROM stock_out o JOIN customers c ON c.id = o.customer_id JOIN books b ON b.id = o.book_id
    LEFT JOIN (SELECT stock_out_id, SUM(adet) adet FROM stock_out_returns GROUP BY stock_out_id) r ON r.stock_out_id = o.id
    WHERE o.adet > COALESCE(r.adet,0) ORDER BY o.tarih DESC, o.id DESC');
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Stok Çıkışı<select name="stock_out_id" required><option value="">— Seçiniz —</option><?php foreach ($outs as $out) echo '<option value="' . h($out['id']) . '">' . h('#' . $out['id'] . ' · ' . dtr($out['tarih']) . ' · ' . $out['musteri'] . ' · ' . $out['kod'] . ' - ' . $out['kitap'] . ' · kalan ' . ((int) $out['adet'] - (int) $out['iade']) . ' adet') . '</option>'; ?></select></label>
  <label>İade Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>İadeyi Kaydet</button>
</form>
<p class="muted">İade stoğa eklenir; satışa bağlı olmayan bu hareket cari bakiye veya kâr/zararı değiştirmez. Satış iadeleri için <a href="?p=returns_customer">Müşteri İadesi</a> ekranını kullanın.</p>
<?php
$data = [];
foreach (rows('SELECT r.*, o.id cikis_no, c.ad musteri, b.kod, b.ad kitap
    FROM stock_out_returns r JOIN stock_out o ON o.id = r.stock_out_id JOIN customers c ON c.id = o.customer_id
    JOIN books b ON b.id = o.book_id ORDER BY r.tarih DESC, r.id DESC LIMIT 200') as $return) {
    $data[] = [dtr($return['tarih']), '#' . $return['cikis_no'], $return['musteri'], $return['kod'] . ' - ' . $return['kitap'], $return['adet'], $return['aciklama']];
}
table(['Tarih', 'Stok Çıkışı', 'Müşteri', 'Kitap', 'Adet', 'Açıklama'], $data, ['int' => [4], 'totals' => [4]]);
layout_foot();
