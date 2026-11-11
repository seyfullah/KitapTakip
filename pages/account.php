<?php
$returnPage = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('return') : get('return');
if (!in_array($returnPage, ['stock_in', 'stock_out'], true)) $returnPage = '';
$saleId = (int) ($_SERVER['REQUEST_METHOD'] === 'POST' ? post('sale_id') : get('sale_id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personId = (int) post('person_id');
    if (!row('SELECT id FROM uy_people WHERE id = ?', [$personId])) {
        flash('Kişi listesinden geçerli bir kayıt seçin.');
        $params = ['p' => 'account', 'return' => $returnPage, 'sale_id' => $saleId];
        redirect('?' . http_build_query($params));
    }
    q('UPDATE uy_users SET person_id = ? WHERE id = ?', [$personId, (int) $_SESSION['uid']]);
    flash('Hesabınız kişi kaydıyla eşleştirildi.');
    $params = ['p' => $returnPage ?: 'account'];
    if ($returnPage === 'stock_out' && $saleId > 0) $params['sale_id'] = $saleId;
    redirect('?' . http_build_query($params));
}

$currentPersonId = current_person_id();
layout_head('Hesabım', 'account');
?>
<p>Stok girişinde teslim alan ve satış teslimatında götüren kişi olarak varsayılan seçilecek kendi kişi kaydınızı belirleyin. İşlem sırasında seçimi değiştirebilirsiniz.</p>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="return" value="<?= h($returnPage) ?>">
  <input type="hidden" name="sale_id" value="<?= h($saleId) ?>">
  <label>Kişi<select name="person_id" class="person-select" required><option value="">— Seçiniz —</option><?php foreach (rows('SELECT id, ad FROM uy_people ORDER BY ad') as $person) echo '<option value="' . h($person['id']) . '"' . ((int) $person['id'] === $currentPersonId ? ' selected' : '') . '>' . h($person['ad']) . '</option>'; ?></select></label>
  <button>Kaydet</button>
  <a href="?p=people">Kişileri yönet</a>
</form>
<?php layout_foot(); ?>

