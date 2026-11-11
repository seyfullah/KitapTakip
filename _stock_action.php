<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $act = post('act');
    $target = match ($act) {
        'in', 'delete_in' => $act === 'in' ? 'uy_stock_in' : 'stock_in_history',
        default => 'stock_current',
    };

    if ($act === 'delete_in') {
        $id = (int) post('id');
        try {
            $initial = row('SELECT book_id FROM uy_stock_in WHERE id = ?', [$id]);
            if (!$initial) throw new DomainException('Stok girişi bulunamadı.');
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
                q('SELECT id FROM uy_books WHERE id = ?' . $lock, [$initial['book_id']]);
                $r = row('SELECT book_id, adet FROM uy_stock_in WHERE id = ?' . $lock, [$id]);
                if (!$r) throw new DomainException('Stok girişi bulunamadı.');
                if ((int) val('SELECT COUNT(*) FROM uy_supplier_payments WHERE stock_in_id = ?', [$id]) || (int) val('SELECT COUNT(*) FROM uy_supplier_return_links WHERE stock_in_id = ?', [$id])) throw new DomainException('Ödemesi veya iadesi olan stok girişi silinemez.');
                if (book_stock((int) $r['book_id']) - (int) $r['adet'] < 0) throw new DomainException('Silinirse stok eksiye düşer; silinemedi.');
                q('DELETE FROM uy_supplier_purchases WHERE stock_in_id = ?', [$id]);
                q('DELETE FROM uy_stock_in WHERE id = ?', [$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            flash('Stok girişi silindi.');
            after_write();
        } catch (DomainException $e) {
            flash($e->getMessage());
        }
    } elseif ($act === 'in') {
        $bid = (int) post('book_id');
        $book = row('SELECT id FROM uy_books WHERE id = ?', [$bid]);
        try {
            if (!$book) throw new DomainException('Kitap seçiniz.');
            $adet = stock_quantity(post('adet'));
            $person = row('SELECT ad FROM uy_people WHERE id = ?', [(int) post('person_id')]);
            if (!$person) throw new DomainException('Listeden bir kişi seçiniz.');

            if ($act === 'in') {
                $date = valid_date(post('tarih'));
                $due = valid_date(post('vade_tarihi'));
                $amount = amount_cents(post('alis_tutari'));
                if ($due < $date) throw new DomainException('Vade tarihi stok girişinden önce olamaz.');
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    q('INSERT INTO uy_stock_in (book_id, tarih, adet, teslim_alan, aciklama) VALUES (?,?,?,?,?)',
                        [$bid, $date, $adet, $person['ad'], post('aciklama')]);
                    q('INSERT INTO uy_supplier_purchases (stock_in_id, total_cents, due_date) VALUES (?,?,?)',
                        [(int) $pdo->lastInsertId(), $amount, $due]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                flash("Stok girişi ve vadeli alış kaydedildi ($adet adet).");
                after_write();
            }
        } catch (DomainException $e) {
            flash($e->getMessage());
        }
    }
    redirect('?p=' . $target);
}
