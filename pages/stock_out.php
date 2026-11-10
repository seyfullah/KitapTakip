<?php
$saleId = (int) ($_SERVER['REQUEST_METHOD'] === 'POST' ? post('sale_id') : get('sale_id'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date = valid_date(post('tarih'));
        $recipient = post('teslim_edilen');
        if ($recipient === '' || mb_strlen($recipient) > 200) throw new DomainException('Teslim edilen kişi zorunludur (en fazla 200 karakter).');
        $person = row('SELECT ad FROM people WHERE id = ?', [(int) post('person_id')]);
        if (!$person) throw new DomainException('Listeden götüren kişiyi seçin.');
        $units = deliver_sale($saleId, $date, $person['ad'], $recipient);
        flash("Teslimat kaydedildi ($units adet). Stok satış sırasında düşülmüştür.");
        after_write();
    } catch (DomainException $e) {
        flash($e->getMessage());
    }
    redirect('?p=stock_out' . ($saleId > 0 ? '&sale_id=' . $saleId : ''));
}
$itemQuery = ' FROM sale_items si
    LEFT JOIN (SELECT sale_item_id, SUM(adet) adet FROM sale_deliveries GROUP BY sale_item_id) d ON d.sale_item_id = si.id
    LEFT JOIN legacy_sale_returns l ON l.sale_item_id = si.id';

if (!$saleId) {
    layout_head('Teslimat Bekleyen Satışlar', 'stock_out');
    $sales = rows('SELECT s.id, s.tarih, s.vade_tarihi, c.ad musteri, c.tip, s.toplam,
        SUM(si.adet - COALESCE(d.adet,0) - COALESCE(l.adet,0)) kalan
        FROM sales s JOIN customers c ON c.id = s.customer_id JOIN sale_items si ON si.sale_id = s.id
        LEFT JOIN (SELECT sale_item_id, SUM(adet) adet FROM sale_deliveries GROUP BY sale_item_id) d ON d.sale_item_id = si.id
        LEFT JOIN legacy_sale_returns l ON l.sale_item_id = si.id
        GROUP BY s.id, s.tarih, s.vade_tarihi, c.ad, c.tip, s.toplam
        HAVING SUM(si.adet - COALESCE(d.adet,0) - COALESCE(l.adet,0)) > 0 ORDER BY s.tarih DESC, s.id DESC');
    $data = [];
    foreach ($sales as $sale) {
        $data[] = ['#' . $sale['id'], dtr($sale['tarih']), $sale['musteri'] . ' (' . $sale['tip'] . ')',
            dtr($sale['vade_tarihi']), $sale['toplam'], $sale['kalan'],
            '<a href="?p=stock_out&amp;sale_id=' . h($sale['id']) . '">Siparişi görüntüle</a>'];
    }
    table(['Satış', 'Tarih', 'Müşteri', 'Vade', 'Satış Tutarı', 'Bekleyen Adet', ''], $data,
        ['money' => [4], 'int' => [5], 'raw' => [6 => true]]);
    echo '<p class="muted">Satışta stok ayrılır; teslimat kaydı stoğu tekrar düşürmez.</p>';
    layout_foot();
    return;
}

$sale = row('SELECT s.*, c.ad musteri, c.tip FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$saleId]);
if (!$sale) {
    http_response_code(404);
    layout_head('Satış Bulunamadı', 'stock_out');
    echo '<p>Satış kaydı bulunamadı.</p><a href="?p=stock_out">Teslimat bekleyen satışlara dön</a>';
    layout_foot();
    return;
}
$items = rows('SELECT si.id, si.adet, si.birim_fiyat, si.toplam, b.kod, b.ad kitap,
        COALESCE(d.adet,0) delivered, COALESCE(l.adet,0) legacy' . $itemQuery .
    ' JOIN books b ON b.id = si.book_id WHERE si.sale_id = ? ORDER BY si.id', [$saleId]);
$currentPersonId = current_person_id();
layout_head('Satış #' . $saleId . ' · Teslimat', 'stock_out');
echo '<p><a href="?p=stock_out">← Teslimat bekleyen satışlara dön</a></p>';
echo '<div class="card"><strong>Müşteri:</strong> ' . h($sale['musteri'] . ' (' . $sale['tip'] . ')') .
    '<br><strong>Satış tarihi:</strong> ' . h(dtr($sale['tarih'])) .
    ' · <strong>Vade:</strong> ' . h(dtr($sale['vade_tarihi'])) .
    '<br><strong>Satış tutarı:</strong> ' . money($sale['toplam']) .
    '<br><strong>Açıklama:</strong> ' . h($sale['aciklama']) . '</div>';
$data = [];
$pending = [];
foreach ($items as $item) {
    $remaining = (int) $item['adet'] - (int) $item['delivered'] - (int) $item['legacy'];
    if ($remaining > 0) $pending[] = $item;
    $data[] = [$item['kod'], $item['kitap'], $item['adet'], $item['birim_fiyat'], $item['toplam'],
        $item['delivered'], $item['legacy'], $remaining];
}
echo '<h2>Sipariş İçeriği</h2>';
table(['Kod', 'Kitap', 'Satılan Adet', 'Birim Fiyat', 'Tutar', 'Teslim Edilen', 'Eski İade', 'Bekleyen'], $data,
    ['int' => [2, 5, 6, 7], 'money' => [3, 4], 'totals' => [2, 4, 5, 6, 7]]);
if (!$pending) {
    echo '<p>Bu satışın teslimat bekleyen kalemi yok.</p>';
    layout_foot();
    return;
}
if ($currentPersonId === null) {
    echo '<p>Teslimat kaydı için önce <a href="?p=account&amp;return=stock_out&amp;sale_id=' . h($saleId) . '">hesabınızı kişi kaydınızla eşleştirin</a>.</p>';
    layout_foot();
    return;
}
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="sale_id" value="<?= h($saleId) ?>">
  <h2>Teslimat Kaydı</h2>
  <label>Teslim Tarihi<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Götüren Kişi<select name="person_id" class="person-select" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad FROM people ORDER BY ad') as $person) echo '<option value="' . h($person['id']) . '"' . ((int) $person['id'] === $currentPersonId ? ' selected' : '') . '>' . h($person['ad']) . '</option>'; ?></select></label>
  <a href="?p=people">Kişileri yönet</a>
  <label>Teslim Edilen Kişi<input name="teslim_edilen" maxlength="200" required></label>
  <button>Teslimatı Kaydet</button>
</form>
<p class="muted">Kayıt, siparişin teslimat bekleyen tüm kitaplarını teslim eder. Önceki kısmi teslimatlar korunur; eski satışlar teslim edilmemiş kabul edilir.</p>
<?php layout_foot(); ?>
