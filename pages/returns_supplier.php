<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = (int) post('book_id');
    $book = row('SELECT id, koli_adedi, alis_fiyati FROM books WHERE id = ?', [$bookId]);
    try {
        if (!$book || post('tedarikci') === '') throw new DomainException('Kitap ve tedarikçi zorunludur.');
        [$units, $cartons] = stock_quantity(post('adet'), post('koli'), post('miktar_tipi'), $book);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            if (!row('SELECT id FROM books WHERE id = ?' . $lock, [$bookId])) throw new DomainException('Kitap bulunamadı.');
            $available = book_stock($bookId);
            if ($units > $available) throw new DomainException("Stok yetersiz (mevcut: $available adet).");
            $price = (float) $book['alis_fiyati'];
            q('INSERT INTO supplier_returns (book_id, tarih, tedarikci, adet, koli, birim_fiyat, toplam, aciklama) VALUES (?,?,?,?,?,?,?,?)',
                [$bookId, post('tarih', date('Y-m-d')), post('tedarikci'), $units, $cartons, $price, round($units * $price, 2), post('aciklama')]);
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
$books = rows('SELECT id, kod, ad, koli_adedi FROM books ORDER BY ad');
?>
<form method="post" class="card entry-form stock-entry" data-stock-quantity>
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="miktar_tipi" value="">
  <label>Kitap<select name="book_id" data-carton-book required><option value="">— Seçiniz —</option><?php foreach ($books as $b) echo '<option value="' . h($b['id']) . '" data-carton-size="' . h($b['koli_adedi']) . '">' . h($b['kod'] . ' - ' . $b['ad'] . ' (stok ' . book_stock((int) $b['id']) . ')') . '</option>'; ?></select></label>
  <label>Tedarikçi<input name="tedarikci" required></label>
  <label>İade Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <div class="field-row">
    <label>Adet<input type="number" name="adet" min="1" step="1" data-stock-units></label>
    <label>Koli (1 koli = <span data-carton-label>—</span> adet)<input type="number" name="koli" min="0.01" step="0.01" inputmode="decimal" data-stock-cartons></label>
  </div>
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
