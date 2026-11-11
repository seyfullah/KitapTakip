<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int) post('stock_in_id');
        $units = stock_quantity(post('adet'));
        $date = valid_date(post('tarih'));
        $initial = row('SELECT book_id FROM uy_stock_in WHERE id = ?', [$id]);
        if (!$initial) throw new DomainException('Stok girişini seçin.');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            $book = row('SELECT id FROM uy_books WHERE id = ?' . $lock, [$initial['book_id']]);
            $stock = row('SELECT id, book_id, tarih, adet FROM uy_stock_in WHERE id = ?' . $lock, [$id]);
            if (!$book || !$stock) throw new DomainException('Stok girişi bulunamadı.');
            if ((int) $stock['adet'] <= 0) throw new DomainException('Stok girişinin adedi geçersiz.');
            $purchase = row('SELECT total_cents FROM uy_supplier_purchases WHERE stock_in_id = ?', [$id]);
            if (!$purchase) throw new DomainException('Önce tedarikçi ödemeleri ekranından bu girişin alış bilgilerini kaydedin.');
            if ($date < $stock['tarih']) throw new DomainException('İade tarihi stok girişinden önce olamaz.');
            $returned = (int) val('SELECT COALESCE(SUM(r.adet),0) FROM uy_supplier_return_links l JOIN uy_supplier_returns r ON r.id = l.return_id WHERE l.stock_in_id = ?', [$id]);
            if ($units > (int) $stock['adet'] - $returned) throw new DomainException('İade miktarı girişin kalan adedini aşamaz.');
            $available = book_stock((int) $stock['book_id']);
            if ($units > $available) throw new DomainException("Stok yetersiz (mevcut: $available adet).");
            $total = (int) $purchase['total_cents'];
            $before = (int) round($total * ($returned / (int) $stock['adet']));
            $after = (int) round($total * (($returned + $units) / (int) $stock['adet']));
            $refund = $after - $before;
            q('INSERT INTO uy_supplier_returns (book_id, tarih, tedarikci, adet, birim_fiyat, toplam, aciklama) VALUES (?,?,?,?,?,?,?)',
                [$stock['book_id'], $date, '', $units, round($total / (int) $stock['adet'] / 100, 2), $refund / 100, post('aciklama')]);
            q('INSERT INTO uy_supplier_return_links (return_id, stock_in_id) VALUES (?,?)', [(int) $pdo->lastInsertId(), $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash("İade kaydedildi ($units adet, " . money($refund / 100) . '). Stok ve alış bakiyesi güncellendi.');
        after_write();
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=returns_supplier');
}

$purchases = supplier_purchase_rows();
layout_head('Tedarikçiye İade', 'returns_supplier');
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Stok Girişi<select name="stock_in_id" required><option value="">— Seçiniz —</option><?php foreach ($purchases as $p) {
      if ($p['total_cents'] === null || (int) $p['returned_units'] >= (int) $p['adet']) continue;
      echo '<option value="' . h($p['id']) . '">' . h('#' . $p['id'] . ' · ' . dtr($p['tarih']) . ' · ' . $p['kitap'] . ' · kalan ' . ((int) $p['adet'] - (int) $p['returned_units']) . ' adet') . '</option>';
  } ?></select></label>
  <label>İade Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>İadeyi Kaydet</button>
</form>
<p class="muted">İade, girişin alış tutarıyla orantılı hesaplanır ve ilgili borçtan düşülür. Eski girişler için önce <a href="?p=uy_supplier_payments">alış bilgilerini</a> kaydedin. Önceki iadeler girişlerle otomatik eşleştirilmez.</p>
<?php
$data = [];
foreach (rows('SELECT r.*, l.stock_in_id, b.kod, b.ad kitap FROM uy_supplier_returns r LEFT JOIN uy_supplier_return_links l ON l.return_id = r.id JOIN uy_books b ON b.id = r.book_id ORDER BY r.tarih DESC, r.id DESC LIMIT 200') as $r)
    $data[] = [dtr($r['tarih']), $r['stock_in_id'] ? '#' . $r['stock_in_id'] : 'Eski kayıt', $r['kod'] . ' - ' . $r['kitap'], $r['adet'], $r['toplam'], $r['aciklama']];
table(['Tarih', 'Stok Girişi', 'Kitap', 'Adet', 'İade Tutarı', 'Açıklama'], $data, ['int' => [3], 'money' => [4], 'totals' => [3, 4]]);
layout_foot();
