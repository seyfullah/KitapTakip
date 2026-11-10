<?php
require __DIR__ . '/_stock_action.php';
layout_head('Stok Çıkışı', 'stock_out');
$books = rows('SELECT id, kod, ad FROM books ORDER BY ad');
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="act" value="out">
  <label>Kitap<select name="book_id" required><option value="">— Seçiniz —</option><?php foreach ($books as $b) echo '<option value="' . h($b['id']) . '">' . h($b['kod'] . ' - ' . $b['ad']) . '</option>'; ?></select></label>
  <label>Okul / Kırtasiye<select name="customer_id" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad, tip FROM customers ORDER BY ad') as $c) echo '<option value="' . h($c['id']) . '">' . h($c['ad'] . ' (' . $c['tip'] . ')') . '</option>'; ?></select></label>
  <label>Götüren Kişi<select name="person_id" class="person-select" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad FROM people ORDER BY ad') as $person) echo '<option value="' . h($person['id']) . '">' . h($person['ad']) . '</option>'; ?></select></label>
  <a href="?p=people">Kişileri yönet</a>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>Çıkışı Kaydet</button>
</form>
<?php layout_foot(); ?>
