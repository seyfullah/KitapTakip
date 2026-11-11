<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int) post('stock_in_id');
        $amount = amount_cents(post('tutar'));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            $stock = row('SELECT id, tarih, adet FROM uy_stock_in WHERE id = ?' . $lock, [$id]);
            if (!$stock) throw new DomainException('Stok girişi bulunamadı.');
            if ((int) $stock['adet'] <= 0) throw new DomainException('Stok girişinin adedi geçersiz.');
            $purchase = row('SELECT total_cents, due_date FROM uy_supplier_purchases WHERE stock_in_id = ?', [$id]);
            if (post('act') === 'purchase') {
                if ($purchase) throw new DomainException('Bu stok girişinin alış bilgisi zaten mevcut.');
                $due = valid_date(post('vade_tarihi'));
                if ($due < $stock['tarih']) throw new DomainException('Vade tarihi stok girişinden önce olamaz.');
                if ((int) val('SELECT COUNT(*) FROM uy_supplier_return_links WHERE stock_in_id = ?', [$id])) throw new DomainException('İadesi bulunan giriş için alış bilgisi eklenemez.');
                q('INSERT INTO uy_supplier_purchases (stock_in_id, total_cents, due_date) VALUES (?,?,?)', [$id, $amount, $due]);
                $message = 'Alış bilgisi kaydedildi.';
            } elseif (post('act') === 'pay') {
                if (!$purchase) throw new DomainException('Önce alış bilgilerini kaydedin.');
                $date = valid_date(post('tarih'));
                if ($date < $stock['tarih']) throw new DomainException('Ödeme tarihi stok girişinden önce olamaz.');
                $method = post('yontem');
                if (!in_array($method, ['Nakit', 'Havale/EFT', 'Kart', 'Diğer'], true)) throw new DomainException('Ödeme yöntemi seçin.');
                $paid = (int) val('SELECT COALESCE(SUM(amount_cents),0) FROM uy_supplier_payments WHERE stock_in_id = ?', [$id]);
                $returned = (int) val('SELECT COALESCE(SUM(ROUND(r.toplam * 100)),0) FROM uy_supplier_return_links l JOIN uy_supplier_returns r ON r.id = l.return_id WHERE l.stock_in_id = ?', [$id]);
                $remaining = (int) $purchase['total_cents'] - $paid - $returned;
                if ($amount > $remaining) throw new DomainException('Ödeme kalan borcu aşamaz.');
                q('INSERT INTO uy_supplier_payments (stock_in_id, tarih, amount_cents, yontem, aciklama) VALUES (?,?,?,?,?)', [$id, $date, $amount, $method, post('aciklama')]);
                $message = 'Tedarikçi ödemesi kaydedildi.';
            } else {
                throw new DomainException('Geçersiz işlem.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash($message);
        after_write();
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=uy_supplier_payments');
}

$purchases = supplier_purchase_rows();
layout_head('Tedarikçi Ödemeleri', 'uy_supplier_payments');
?>
<div class="form-grid">
<form method="post" class="card">
  <h2>Ödeme Kaydı</h2><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="pay">
  <label>Stok Girişi<select name="stock_in_id" required><option value="">— Seçiniz —</option><?php foreach ($purchases as $p) {
      if ($p['total_cents'] === null) continue;
      $balance = (int) $p['total_cents'] - (int) $p['paid_cents'] - (int) $p['return_cents'];
      if ($balance > 0) echo '<option value="' . h($p['id']) . '">' . h('#' . $p['id'] . ' · ' . $p['kitap'] . ' · kalan ' . money($balance / 100) . ' · vade ' . dtr($p['due_date'])) . '</option>';
  } ?></select></label>
  <label>Ödeme Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Tutar (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Yöntem<?= select('yontem', array_combine(['Nakit', 'Havale/EFT', 'Kart', 'Diğer'], ['Nakit', 'Havale/EFT', 'Kart', 'Diğer'])) ?></label>
  <label>Açıklama<input name="aciklama"></label><button>Ödemeyi Kaydet</button>
</form>
<form method="post" class="card">
  <h2>Eski Girişin Alış Bilgisi</h2><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="purchase">
  <label>Stok Girişi<select name="stock_in_id" required><option value="">— Seçiniz —</option><?php foreach ($purchases as $p) if ($p['total_cents'] === null) echo '<option value="' . h($p['id']) . '">' . h('#' . $p['id'] . ' · ' . dtr($p['tarih']) . ' · ' . $p['kitap'] . ' · ' . $p['adet'] . ' adet') . '</option>'; ?></select></label>
  <label>Toplam Alış Tutarı (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Ödeme Vadesi<input type="date" name="vade_tarihi" required></label><button>Alış Bilgisini Kaydet</button>
</form>
</div>
<p class="muted">Eski girişlere alış bilgisi eklemek önceki tedarikçi iadelerini veya ödemeleri otomatik eşleştirmez. İade fazlası, ilgili girişte tedarikçiden alacak olarak gösterilir.</p>
<?php
$data = [];
foreach ($purchases as $p) {
    if ($p['total_cents'] === null) continue;
    $balance = (int) $p['total_cents'] - (int) $p['paid_cents'] - (int) $p['return_cents'];
    $status = $balance < 0 ? 'Alacak' : ($balance === 0 ? 'Kapalı' : ($p['due_date'] < date('Y-m-d') ? 'Vadesi geçmiş' : 'Açık'));
    $data[] = ['#' . $p['id'], dtr($p['tarih']), $p['kod'] . ' - ' . $p['kitap'], dtr($p['due_date']), $p['total_cents'] / 100, $p['paid_cents'] / 100, $p['return_cents'] / 100, max(0, $balance) / 100, max(0, -$balance) / 100, $status];
}
table(['Giriş', 'Tarih', 'Kitap', 'Vade', 'Alış', 'Ödenen', 'İade', 'Kalan Borç', 'Alacak', 'Durum'], $data, ['money' => [4, 5, 6, 7, 8], 'totals' => [4, 5, 6, 7, 8]]);
echo '<h2>Ödeme Geçmişi</h2>';
$data = [];
foreach (rows('SELECT p.*, b.ad kitap FROM uy_supplier_payments p JOIN uy_stock_in i ON i.id = p.stock_in_id JOIN uy_books b ON b.id = i.book_id ORDER BY p.tarih DESC, p.id DESC') as $payment) {
    $data[] = [dtr($payment['tarih']), '#' . $payment['stock_in_id'], $payment['kitap'], $payment['amount_cents'] / 100, $payment['yontem'], $payment['aciklama']];
}
table(['Tarih', 'Giriş', 'Kitap', 'Tutar', 'Yöntem', 'Açıklama'], $data, ['money' => [3], 'totals' => [3]]);
layout_foot();
