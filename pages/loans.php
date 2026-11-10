<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('act');
    if ($act === 'delete_payment') {
        q('DELETE FROM loan_payments WHERE id = ?', [(int) post('id')]);
        flash('Borç ödemesi silindi.');
    } elseif ($act === 'delete_loan') {
        $id = (int) post('id');
        if ((int) val('SELECT COUNT(*) FROM loan_payments WHERE loan_id = ?', [$id])) flash('Ödemesi bulunan borç kaydı silinemez.');
        else { q('DELETE FROM loans WHERE id = ?', [$id]); flash('Borç kaydı silindi.'); }
    } elseif ($act === 'repay') {
        $loanId = (int) post('loan_id');
        $amount = dec(post('tutar'));
        $loan = row('SELECT id, tutar FROM loans WHERE id = ?', [$loanId]);
        if (!$loan || $amount <= 0) flash('Borç ve pozitif ödeme tutarı zorunludur.');
        else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
                $loan = row('SELECT id, tutar FROM loans WHERE id = ?' . $lock, [$loanId]);
                $paid = (float) val('SELECT COALESCE(SUM(tutar),0) FROM loan_payments WHERE loan_id = ?', [$loanId]);
                $remaining = round((float) $loan['tutar'] - $paid, 2);
                if ($amount > $remaining + 0.001) throw new DomainException('Ödeme kalan borç tutarını aşamaz: ' . money($remaining));
                q('INSERT INTO loan_payments (loan_id, tarih, tutar, yontem, aciklama) VALUES (?,?,?,?,?)',
                    [$loanId, post('tarih', date('Y-m-d')), $amount, post('yontem'), post('aciklama')]);
                $pdo->commit();
                flash('Borç ödemesi kaydedildi.');
            } catch (DomainException $e) {
                $pdo->rollBack();
                flash($e->getMessage());
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    } else {
        $amount = dec(post('tutar'));
        if (post('alacakli') === '' || $amount <= 0) flash('Borç alınan kişi/kurum ve pozitif tutar zorunludur.');
        else {
            q('INSERT INTO loans (tarih, alacakli, tutar, aciklama) VALUES (?,?,?,?)',
                [post('tarih', date('Y-m-d')), post('alacakli'), $amount, post('aciklama')]);
            flash('Borç kaydedildi.');
        }
    }
    after_write();
    redirect('?p=loans');
}

layout_head('Alınan Borçlar', 'loans');
$openLoans = rows('SELECT l.id, l.alacakli, l.tutar, l.tutar - COALESCE(p.odenen,0) kalan FROM loans l LEFT JOIN (SELECT loan_id, SUM(tutar) odenen FROM loan_payments GROUP BY loan_id) p ON p.loan_id = l.id WHERE l.tutar > COALESCE(p.odenen,0) ORDER BY l.tarih, l.id');
?>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <h2>Yeni Borç</h2>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Borç Alınan Kişi / Kurum<input name="alacakli" required></label>
  <label>Tutar (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Açıklama (ör. kitap alımı)<input name="aciklama"></label>
  <button>Borcu Kaydet</button>
</form>
<form method="post" class="f no-print">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="act" value="repay">
  <h2>Borç Ödemesi</h2>
  <label>Borç<select name="loan_id" required><option value="">— Seçiniz —</option><?php foreach ($openLoans as $l) echo '<option value="' . h($l['id']) . '">' . h($l['alacakli'] . ' · kalan ' . money($l['kalan'])) . '</option>'; ?></select></label>
  <label>Tarih<input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required></label>
  <label>Tutar (TL)<input name="tutar" inputmode="decimal" required></label>
  <label>Yöntem<?= select('yontem', array_combine(['Nakit', 'Havale/EFT', 'Diğer'], ['Nakit', 'Havale/EFT', 'Diğer'])) ?></label>
  <label>Açıklama<input name="aciklama"></label>
  <button>Ödemeyi Kaydet</button>
</form>
<?php
$loans = rows('SELECT l.*, COALESCE(p.odenen,0) odenen, l.tutar - COALESCE(p.odenen,0) kalan FROM loans l LEFT JOIN (SELECT loan_id, SUM(tutar) odenen FROM loan_payments GROUP BY loan_id) p ON p.loan_id = l.id ORDER BY l.tarih DESC, l.id DESC');
$data = [];
foreach ($loans as $l) $data[] = [dtr($l['tarih']), $l['alacakli'], $l['tutar'], $l['odenen'], $l['kalan'], $l['aciklama'],
    '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $l['id'] . '"><input type="hidden" name="act" value="delete_loan"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Alacaklı', 'Alınan Borç', 'Ödenen', 'Kalan', 'Açıklama', ''], $data, ['money' => [2, 3, 4], 'totals' => [2, 3, 4], 'raw' => [6 => true]]);
echo '<h2>Ödeme Geçmişi</h2>';
$data = [];
foreach (rows('SELECT p.*, l.alacakli FROM loan_payments p JOIN loans l ON l.id = p.loan_id ORDER BY p.tarih DESC, p.id DESC LIMIT 300') as $p)
    $data[] = [dtr($p['tarih']), $p['alacakli'], $p['tutar'], $p['yontem'], $p['aciklama'],
        '<form method="post" onsubmit="return confirm(\'Silinsin mi?\')"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="id" value="' . $p['id'] . '"><input type="hidden" name="act" value="delete_payment"><button class="danger">Sil</button></form>'];
table(['Tarih', 'Alacaklı', 'Ödeme', 'Yöntem', 'Açıklama', ''], $data, ['money' => [2], 'totals' => [2], 'raw' => [5 => true]]);
echo '<p class="muted">Borç alınması ve anapara geri ödemeleri kâr/zarar hesabından ayrı tutulur.</p>';
layout_foot();
