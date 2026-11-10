<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    if (post('act') === 'delete') {
        if ((int) val('SELECT COUNT(*) FROM user_people WHERE person_id = ?', [$id])) {
            flash('Bir kullanıcı hesabıyla eşleşen kişi silinemez.');
        } else {
            q('DELETE FROM people WHERE id = ?', [$id]);
            flash('Kişi listeden silindi; geçmiş hareketlerdeki adı korundu.');
        }
    } else {
        $name = post('ad');
        if ($name === '' || mb_strlen($name) > 120) {
            flash('Kişi adı zorunludur ve 120 karakteri aşamaz.');
        } elseif (val('SELECT id FROM people WHERE ad = ? AND id <> ?', [$name, $id])) {
            flash('Bu kişi zaten listede bulunuyor.');
        } elseif ($id && !val('SELECT id FROM people WHERE id = ?', [$id])) {
            flash('Kişi bulunamadı.');
        } else {
            if ($id) q('UPDATE people SET ad = ? WHERE id = ?', [$name, $id]);
            else q('INSERT INTO people (ad) VALUES (?)', [$name]);
            flash('Kişi kaydedildi. Geçmiş hareketlerdeki adlar değiştirilmez.');
        }
    }
    redirect('?p=people');
}
$person = get('edit') ? row('SELECT id, ad FROM people WHERE id = ?', [(int) get('edit')]) : null;
$person ??= ['id' => '', 'ad' => ''];
layout_head('Kişiler', 'people');
?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="id" value="<?= h($person['id']) ?>">
  <label>Ad Soyad<input name="ad" maxlength="120" value="<?= h($person['ad']) ?>" required></label>
  <button>Kaydet</button><?php if ($person['id']) echo '<a class="btn alt" href="?p=people">Vazgeç</a>'; ?>
</form>
<p class="muted">Kişi adı değiştirilse veya listeden silinse de geçmiş hareketlerde kayıt anındaki ad korunur.</p>
<?php
$data = [];
foreach (rows('SELECT id, ad FROM people ORDER BY ad') as $person) {
    $data[] = [$person['ad'], '<a href="?p=people&edit=' . h($person['id']) . '">Düzenle</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Kişi listeden silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . h($person['id']) . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
}
table(['Ad Soyad', ''], $data, ['raw' => [1 => true]]);
layout_foot();
