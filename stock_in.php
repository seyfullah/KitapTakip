<?php require '_init.php';

require __DIR__ . '/_stock_action.php';
$currentPersonId = require_current_person('stock_in');
layout_head('Stok Girişi', 'stock_in');
$uy_books = rows('SELECT id, kod, ad FROM uy_books ORDER BY ad');
?>
<form method="post" class="card entry-form stock-entry">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="act" value="in">
  <label>Kitap<select name="book_id" required><option value="">— Seçiniz —</option><?php foreach ($uy_books as $b) echo '<option value="' . h($b['id']) . '">' . h($b['kod'] . ' - ' . $b['ad']) . '</option>'; ?></select></label>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Adet<input type="number" name="adet" min="1" step="1" required></label>
  <label>Toplam Alış Tutarı (TL)<input name="alis_tutari" inputmode="decimal" required></label>
  <label>Ödeme Vadesi<input type="date" name="vade_tarihi" value="<?= date('Y-m-d') ?>" required></label>
  <label>Teslim Alan Kişi<select name="person_id" class="person-select" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad FROM uy_people ORDER BY ad') as $person) echo '<option value="' . h($person['id']) . '"' . ((int) $person['id'] === $currentPersonId ? ' selected' : '') . '>' . h($person['ad']) . '</option>'; ?></select></label>
  <a href="people.php">Kişileri yönet</a>
  <label>Açıklama<input name="aciklama"></label>
  <button>Stok Ekle</button>
</form>
<?php layout_foot(); ?>
