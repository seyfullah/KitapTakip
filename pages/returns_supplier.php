<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = (int) post('book_id');
    $book = row('SELECT id, alis_fiyati FROM books WHERE id = ?', [$bookId]);
    try {
        if (!$book || post('tedarikci') === '') throw new DomainException('Kitap ve tedarikçi zorunludur.');
        $units = stock_quantity(post('adet'));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            if (!row('SELECT id FROM books WHERE id = ?' . $lock, [$bookId])) throw new DomainException('Kitap bulunamadı.');
            $available = book_stock($bookId);
            if ($units > $available) throw new DomainException("Stok yetersiz (mevcut: $available adet).");
            $price = (float) $book['alis_fiyati'];
            q('INSERT INTO supplier_returns (book_id, tarih, tedarikci, adet, birim_fiyat, toplam, aciklama) VALUES (?,?,?,?,?,?,?)',
                [$bookId, post('tarih', date('Y-m-d')), post('tedarikci'), $units, $price, round($units * $price, 2), post('aciklama')]);
            $pdo->commit();
            flash("Tedarikçi iadesi kaydedildi ($units adet). Stok güncellendi.");
            after_write();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=returns_supplier');
}

layout_head('Tedarikçiye İade', 'returns_supplier');
$books = rows('SELECT id, kod, ad FROM books ORDER BY ad');
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Kitap<select name="book_id" required><option value="">— Seçiniz —</option><?php foreach ($books as $b) echo '<option value="' . h($b['id']) . '">' . h($b['kod'] . ' - ' . $b['ad'] . ' (stok ' . book_stock((int) $b['id']) . ')') . '</option>'; ?></select></label>
  <label>Tedarikçi<input name="tedarikci" required></label>
  <label>İade Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>İadeyi Kaydet</button>
</form>
<p class="muted">İade tutarı, kitap kartındaki güncel alış fiyatına göre kaydedilir. İade edilecek miktar mevcut stoğu aşamaz.</p>
<?php
$data = [];
foreach (rows('SELECT r.*, b.kod, b.ad kitap FROM supplier_returns r JOIN books b ON b.id = r.book_id ORDER BY r.tarih DESC, r.id DESC LIMIT 200') as $r)
    $data[] = [dtr($r['tarih']), $r['tedarikci'], $r['kod'] . ' - ' . $r['kitap'], $r['adet'], $r['toplam'], $r['aciklama']];
table(['Tarih', 'Tedarikçi', 'Kitap', 'Adet', 'İade Tutarı', 'Açıklama'], $data, ['int' => [3], 'money' => [4], 'totals' => [3, 4]]);
layout_foot();
