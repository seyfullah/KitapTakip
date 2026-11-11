<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    if (post('act') === 'delete') {
        try { q('DELETE FROM uy_customers WHERE id = ?', [$id]); flash('Müşteri silindi.'); }
        catch (PDOException $e) { flash('Hareketi olan müşteri silinemez.'); }
    } else {
        $tip = post('tip') === 'Kırtasiye' ? 'Kırtasiye' : 'Okul';
        $v = [$tip, post('ad'), post('il'), post('ilce'), post('yetkili'), post('telefon'), post('eposta'), post('adres')];
        if ($id) q('UPDATE uy_customers SET tip=?, ad=?, il=?, ilce=?, yetkili=?, telefon=?, eposta=?, adres=? WHERE id=?', [...$v, $id]);
        else q('INSERT INTO uy_customers (tip, ad, il, ilce, yetkili, telefon, eposta, adres) VALUES (?,?,?,?,?,?,?,?)', $v);
        flash('Müşteri kaydedildi.'); after_write();
    }
    redirect('?p=uy_customers');
}
$e = get('edit') ? row('SELECT * FROM uy_customers WHERE id = ?', [(int) get('edit')]) : null;
$e ??= ['id' => '', 'tip' => 'Okul', 'ad' => '', 'il' => '', 'ilce' => '', 'yetkili' => '', 'telefon' => '', 'eposta' => '', 'adres' => ''];
layout_head('Müşteriler', 'uy_customers'); ?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= h($e['id']) ?>">
  <label>Tip<?= select('tip', ['Okul' => 'Okul', 'Kırtasiye' => 'Kırtasiye'], $e['tip']) ?></label>
  <label>Ad<input name="ad" required value="<?= h($e['ad']) ?>"></label>
  <label>İl<input name="il" list="il" value="<?= h($e['il']) ?>"></label>
  <label>İlçe<input name="ilce" list="ilce" value="<?= h($e['ilce']) ?>"></label>
  <datalist id="il"><?php foreach (rows('SELECT DISTINCT il FROM uy_customers WHERE il<>""') as $x) echo '<option value="' . h($x['il']) . '">'; ?></datalist>
  <datalist id="ilce"><?php foreach (rows('SELECT DISTINCT ilce FROM uy_customers WHERE ilce<>""') as $x) echo '<option value="' . h($x['ilce']) . '">'; ?></datalist>
  <label>Yetkili<input name="yetkili" value="<?= h($e['yetkili']) ?>"></label>
  <label>Telefon<input name="telefon" value="<?= h($e['telefon']) ?>"></label>
  <label>E-posta<input type="email" name="eposta" value="<?= h($e['eposta']) ?>"></label>
  <label>Adres<input name="adres" size="30" value="<?= h($e['adres']) ?>"></label>
  <button>Kaydet</button><?php if ($e['id']) echo '<a class="btn alt" href="?p=uy_customers">Vazgeç</a>'; ?>
</form>
<?php
$tip = get('tip'); $fq = get('q');
echo '<form class="f no-print"><input type="hidden" name="p" value="uy_customers"><label>Tip' . select('tip', ['' => 'Tümü', 'Okul' => 'Okul', 'Kırtasiye' => 'Kırtasiye'], $tip) . '</label><label>Ara<input name="q" value="' . h($fq) . '"></label><button>Filtrele</button></form>';
$list = rows('SELECT * FROM uy_customers WHERE (tip = :t OR :t2 = "") AND (ad LIKE :q OR il LIKE :q2 OR ilce LIKE :q3) ORDER BY ad', ['t' => $tip, 't2' => $tip, 'q' => "%$fq%", 'q2' => "%$fq%", 'q3' => "%$fq%"]);
$data = [];
foreach ($list as $c) $data[] = [$c['tip'], $c['ad'], $c['il'], $c['ilce'], $c['adres'], $c['yetkili'], $c['telefon'], $c['eposta'],
    '<a href="?p=uy_customers&edit=' . $c['id'] . '">Düzenle</a> · <a href="?p=cari&customer_id=' . $c['id'] . '">Ekstre</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $c['id'] . '"><input type="hidden" name="act" value="delete"><button class="danger">Sil</button></form>'];
table(['Tip', 'Ad', 'İl', 'İlçe', 'Adres', 'Yetkili', 'Telefon', 'E-posta', ''], $data, ['raw' => [8 => true]]);
layout_foot();
