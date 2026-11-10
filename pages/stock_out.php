<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $itemId = (int) post('sale_item_id');
        $units = stock_quantity(post('adet'));
        $date = valid_date(post('tarih'));
        $recipient = post('teslim_edilen');
        if ($recipient === '' || mb_strlen($recipient) > 200) throw new DomainException('Teslim edilen kişi zorunludur (en fazla 200 karakter).');
        $person = row('SELECT ad FROM people WHERE id = ?', [(int) post('person_id')]);
        if (!$person) throw new DomainException('Listeden götüren kişiyi seçin.');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
            $item = row('SELECT si.id, si.adet, s.tarih FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.id = ?' . $lock, [$itemId]);
            if (!$item) throw new DomainException('Satış kalemini seçin.');
            if ($date < $item['tarih']) throw new DomainException('Teslim tarihi satış tarihinden önce olamaz.');
            $legacy = (int) val('SELECT COALESCE(SUM(adet),0) FROM legacy_sale_returns WHERE sale_item_id = ?', [$itemId]);
            $delivered = (int) val('SELECT COALESCE(SUM(adet),0) FROM sale_deliveries WHERE sale_item_id = ?', [$itemId]);
            $remaining = (int) $item['adet'] - $legacy - $delivered;
            if ($units > $remaining) throw new DomainException("Teslim adedi kalan miktarı aşamaz ($remaining adet).");
            q('INSERT INTO sale_deliveries (sale_item_id, tarih, adet, goturen_kisi, teslim_edilen) VALUES (?,?,?,?,?)', [$itemId, $date, $units, $person['ad'], $recipient]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash("Teslimat kaydedildi ($units adet). Stok satış sırasında düşülmüştür.");
        after_write();
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=stock_out');
}
layout_head('Satış Teslimatı', 'stock_out');
$items = rows('SELECT si.id, si.adet, s.id satis_no, s.tarih, c.ad musteri, b.kod, b.ad kitap,
        COALESCE(d.adet,0) delivered, COALESCE(l.adet,0) legacy
    FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id
    LEFT JOIN (SELECT sale_item_id, SUM(adet) adet FROM sale_deliveries GROUP BY sale_item_id) d ON d.sale_item_id = si.id
    LEFT JOIN legacy_sale_returns l ON l.sale_item_id = si.id
    WHERE si.adet > COALESCE(d.adet,0) + COALESCE(l.adet,0) ORDER BY s.tarih DESC, s.id DESC');
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <label>Satış / Kitap<select name="sale_item_id" required><option value="">— Seçiniz —</option><?php foreach ($items as $item) echo '<option value="' . h($item['id']) . '">' . h('#' . $item['satis_no'] . ' · ' . $item['musteri'] . ' · ' . $item['kod'] . ' - ' . $item['kitap'] . ' · kalan ' . ((int) $item['adet'] - (int) $item['delivered'] - (int) $item['legacy']) . ' adet') . '</option>'; ?></select></label>
  <label>Teslim Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Götüren Kişi<select name="person_id" class="person-select" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad FROM people ORDER BY ad') as $person) echo '<option value="' . h($person['id']) . '">' . h($person['ad']) . '</option>'; ?></select></label>
  <a href="?p=people">Kişileri yönet</a>
  <label>Teslim Edilen Kişi<input name="teslim_edilen" maxlength="200" required></label>
  <button>Teslimatı Kaydet</button>
</form>
<p class="muted">Satışta stok ayrılır. Teslimat kaydı stoğu tekrar düşürmez; eski satışlar teslim edilmemiş kabul edilir.</p>
<?php layout_foot(); ?>
