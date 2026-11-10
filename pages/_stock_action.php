<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $act = post('act');
    $target = match ($act) {
        'in', 'delete_in' => $act === 'in' ? 'stock_in' : 'stock_in_history',
        'out' => 'stock_out',
        default => 'stock_current',
    };

    if ($act === 'delete_in') {
        $id = (int) post('id');
        $r = row('SELECT book_id FROM stock_in WHERE id = ?', [$id]);
        if ($r && book_stock((int) $r['book_id']) - (int) val('SELECT adet FROM stock_in WHERE id = ?', [$id]) < 0) {
            flash('Silinirse stok eksiye düşer; silinemedi.');
        } elseif ($r) {
            q('DELETE FROM stock_in WHERE id = ?', [$id]);
            flash('Stok girişi silindi.');
            after_write();
        }
    } elseif ($act === 'in' || $act === 'out') {
        $bid = (int) post('book_id');
        $book = row('SELECT koli_adedi FROM books WHERE id = ?', [$bid]);
        try {
            if (!$book) throw new DomainException('Kitap seçiniz.');
            [$adet, $koli] = stock_quantity(post('adet'), post('koli'), post('miktar_tipi'), $book);

            if ($act === 'in') {
                if (post('teslim_alan') === '') throw new DomainException('Teslim alan kişi zorunludur.');
                q('INSERT INTO stock_in (book_id, tarih, adet, teslim_alan, aciklama) VALUES (?,?,?,?,?)',
                    [$bid, post('tarih', date('Y-m-d')), $adet, post('teslim_alan'), post('aciklama')]);
                flash("Stok girişi yapıldı ($adet adet).");
                after_write();
            } else {
                $cid = (int) post('customer_id');
                if (!$cid || post('goturen_kisi') === '') throw new DomainException('Müşteri ve götüren kişi zorunludur.');
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $lock = cfg('app_storage') === 'sqlite' ? '' : ' FOR UPDATE';
                    if (!row('SELECT id FROM books WHERE id = ?' . $lock, [$bid])) throw new DomainException('Kitap bulunamadı.');
                    $available = book_stock($bid);
                    if ($adet > $available) throw new DomainException("Stok yetersiz (mevcut: $available adet).");
                    q('INSERT INTO stock_out (book_id, customer_id, tarih, adet, koli, goturen_kisi, aciklama) VALUES (?,?,?,?,?,?,?)',
                        [$bid, $cid, post('tarih', date('Y-m-d')), $adet, $koli, post('goturen_kisi'), post('aciklama')]);
                    $pdo->commit();
                    flash("Stok çıkışı kaydedildi ($adet adet).");
                    after_write();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (DomainException $e) {
            flash($e->getMessage());
        }
    }
    redirect('?p=' . $target);
}
