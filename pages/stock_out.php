<?php
require __DIR__ . '/_stock_action.php';
layout_head('Stok Çıkışı', 'stock_out');
$books = rows('SELECT id, kod, ad, koli_adedi FROM books ORDER BY ad');
?>
<form method="post" class="card entry-form stock-entry" data-stock-quantity>
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="act" value="out">
  <input type="hidden" name="miktar_tipi" value="">
  <label>Kitap<select name="book_id" data-carton-book required><option value="">— Seçiniz —</option><?php foreach ($books as $b) echo '<option value="' . h($b['id']) . '" data-carton-size="' . h($b['koli_adedi']) . '">' . h($b['kod'] . ' - ' . $b['ad']) . '</option>'; ?></select></label>
  <label>Okul / Kırtasiye<select name="customer_id" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad, tip FROM customers ORDER BY ad') as $c) echo '<option value="' . h($c['id']) . '">' . h($c['ad'] . ' (' . $c['tip'] . ')') . '</option>'; ?></select></label>
  <label>Götüren Kişi<input name="goturen_kisi" required></label>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <div class="field-row">
    <label>Adet<input type="number" name="adet" min="1" step="1" data-stock-units></label>
    <label>Koli (1 koli = <span data-carton-label>—</span> adet)<input type="number" name="koli" min="0.01" step="0.01" inputmode="decimal" data-stock-cartons></label>
  </div>
  <label>Açıklama<input name="aciklama"></label>
  <button>Çıkışı Kaydet</button>
</form>
<?php layout_foot(); ?>
