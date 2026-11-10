<?php
declare(strict_types=1);

function cfg(string $k) { static $c; $c ??= require __DIR__ . '/config.php'; return $c[$k] ?? null; }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (cfg('app_storage') === 'sqlite') {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                throw new RuntimeException('APP_STORAGE=sqlite için PHP pdo_sqlite eklentisi gerekir.');
            }
            $path = (string) cfg('app_data_file');
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new RuntimeException("Veri klasörü oluşturulamadı: $dir");
            }
            $isNewFile = !is_file($path);
            $previousUmask = umask(0007);
            try {
                $pdo = new PDO('sqlite:' . $path, null, null, $options);
            } finally {
                umask($previousUmask);
            }
            if ($isNewFile && !chmod($path, 0660)) {
                throw new RuntimeException("Veri dosyası izinleri ayarlanamadı: $path");
            }
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->sqliteCreateFunction('CONCAT', static fn(...$args) => implode('', array_map(static fn($v) => (string) ($v ?? ''), $args)));
            $pdo->sqliteCreateFunction('IF', static fn($condition, $yes, $no) => $condition ? $yes : $no, 3);
            $pdo->sqliteCreateFunction('DATE_FORMAT', static function ($date, $format) {
                $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd'];
                return date(strtr((string) $format, $map), strtotime((string) $date));
            }, 2);
        } else {
            $pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
                cfg('db_user'), cfg('db_pass'), $options);
        }
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { return q($sql, $p)->fetch() ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return number_format((float) $v, 2, ',', '.') . ' TL'; }
function num($v): string { return number_format((float) $v, 0, ',', '.'); }
function dtr(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : ''; }
function post(string $k, string $d = ''): string { return trim((string) ($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }
function dec(string $s): float { // "1.234,50" veya "1234.50"
    $s = trim($s);
    if (str_contains($s, ',')) $s = str_replace(['.', ','], ['', '.'], $s);
    return (float) $s;
}
function redirect(string $url): never { header('Location: ' . $url); exit; }

function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(400); exit('Geçersiz istek (CSRF).');
    }
}

/** Dropdown <select> üretir. */
function select(string $name, array $opts, $sel = '', string $extra = ''): string {
    $o = '';
    foreach ($opts as $k => $v) $o .= '<option value="' . h($k) . '"' . ((string) $k === (string) $sel ? ' selected' : '') . '>' . h($v) . '</option>';
    return '<select name="' . h($name) . '" ' . $extra . '>' . $o . '</select>';
}
function book_opts(bool $blank = false): array {
    $o = $blank ? ['' => '— Seçiniz —'] : [];
    foreach (rows('SELECT id, kod, ad FROM books ORDER BY ad') as $b) $o[$b['id']] = $b['kod'] . ' - ' . $b['ad'];
    return $o;
}
function customer_opts(bool $blank = false, string $tip = ''): array {
    $o = $blank ? ['' => '— Seçiniz —'] : [];
    $sql = 'SELECT id, tip, ad FROM customers' . ($tip ? ' WHERE tip = ?' : '') . ' ORDER BY ad';
    foreach (rows($sql, $tip ? [$tip] : []) as $c) $o[$c['id']] = $c['ad'] . ' (' . $c['tip'] . ')';
    return $o;
}
function stock_quantity(string $rawUnits, string $rawCartons, string $type, array $book): array {
    if ($type === '') {
        if ($rawUnits !== '' && $rawCartons === '') $type = 'adet';
        elseif ($rawCartons !== '' && $rawUnits === '') $type = 'koli';
    }

    $cartonSize = max(1, (int) $book['koli_adedi']);
    if ($type === 'adet' && $rawCartons === '' && preg_match('/^[1-9]\d*$/', $rawUnits)) {
        $units = (int) $rawUnits;
        return [$units, round($units / $cartonSize, 2)];
    }
    if ($type === 'koli' && $rawCartons !== '' && is_numeric(str_replace(',', '.', $rawCartons))) {
        $cartons = dec($rawCartons);
        $units = (int) round($cartons * $cartonSize);
        if ($cartons > 0 && $units > 0 && ($rawUnits === '' || (preg_match('/^[1-9]\d*$/', $rawUnits) && (int) $rawUnits === $units))) {
            return [$units, $cartons];
        }
    }
    throw new DomainException('Adet veya koli alanlarından yalnızca birine geçerli bir değer girin.');
}

/* ---------------- İş mantığı ---------------- */

/** Güncel stok = giriş + müşteri iadeleri - satış - çıkış - tedarikçi iadeleri. */
function stock_report(): array {
    return rows("SELECT b.id, b.kod, b.ad, b.koli_adedi, b.kritik_stok,
        COALESCE(si.t,0) AS giris, COALESCE(sa.t,0) - COALESCE(cr.t,0) AS satilan,
        COALESCE(so.t,0) + COALESCE(sr.t,0) AS diger_cikis,
        COALESCE(si.t,0) + COALESCE(cr.t,0) - COALESCE(sa.t,0) - COALESCE(so.t,0) - COALESCE(sr.t,0) AS stok,
        (COALESCE(si.t,0) + COALESCE(cr.t,0) - COALESCE(sa.t,0) - COALESCE(so.t,0) - COALESCE(sr.t,0)) * b.alis_fiyati AS stok_degeri
        FROM books b
        LEFT JOIN (SELECT book_id, SUM(adet) t FROM stock_in GROUP BY book_id) si ON si.book_id = b.id
        LEFT JOIN (SELECT book_id, SUM(adet) t FROM sale_items GROUP BY book_id) sa ON sa.book_id = b.id
        LEFT JOIN (SELECT book_id, SUM(adet) t FROM stock_out GROUP BY book_id) so ON so.book_id = b.id
        LEFT JOIN (SELECT si.book_id, SUM(cr.adet) t FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id GROUP BY si.book_id) cr ON cr.book_id = b.id
        LEFT JOIN (SELECT book_id, SUM(adet) t FROM supplier_returns GROUP BY book_id) sr ON sr.book_id = b.id
        ORDER BY b.ad");
}
function book_stock(int $bookId): int {
    $r = row('SELECT
        (SELECT COALESCE(SUM(adet),0) FROM stock_in WHERE book_id = :b)
        - (SELECT COALESCE(SUM(adet),0) FROM sale_items WHERE book_id = :b2)
        - (SELECT COALESCE(SUM(adet),0) FROM stock_out WHERE book_id = :b3)
        + (SELECT COALESCE(SUM(cr.adet),0) FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id WHERE si.book_id = :b4)
        - (SELECT COALESCE(SUM(adet),0) FROM supplier_returns WHERE book_id = :b5) s',
        ['b' => $bookId, 'b2' => $bookId, 'b3' => $bookId, 'b4' => $bookId, 'b5' => $bookId]);
    return (int) $r['s'];
}

/** Müşteri bazında satış / tahsilat / kalan. */
function customer_balances(string $from = '', string $to = ''): array {
    $sw = ''; $pw = ''; $rw = ''; $p = [];
    if ($from !== '') { $sw .= ' AND tarih >= :f1'; $pw .= ' AND tarih >= :f2'; $p['f1'] = $p['f2'] = $from; }
    if ($to !== '') { $sw .= ' AND tarih <= :t1'; $pw .= ' AND tarih <= :t2'; $p['t1'] = $p['t2'] = $to; }
    if ($from !== '') { $rw .= ' AND cr.tarih >= :f3'; $p['f3'] = $from; }
    if ($to !== '') { $rw .= ' AND cr.tarih <= :t3'; $p['t3'] = $to; }
    return rows("SELECT c.id, c.tip, c.ad, c.il, c.ilce, c.telefon, c.eposta,
        COALESCE(s.t,0) - COALESCE(r.t,0) AS satis, COALESCE(p.t,0) AS tahsilat,
        COALESCE(s.t,0) - COALESCE(r.t,0) - COALESCE(p.t,0) AS kalan
        FROM customers c
        LEFT JOIN (SELECT customer_id, SUM(toplam) t FROM sales WHERE 1=1 $sw GROUP BY customer_id) s ON s.customer_id = c.id
        LEFT JOIN (SELECT customer_id, SUM(tutar) t FROM payments WHERE 1=1 $pw GROUP BY customer_id) p ON p.customer_id = c.id
        LEFT JOIN (SELECT s.customer_id, SUM(cr.toplam) t FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id JOIN sales s ON s.id = si.sale_id WHERE 1=1 $rw GROUP BY s.customer_id) r ON r.customer_id = c.id
        ORDER BY c.ad", $p);
}

/**
 * Vadesi geçmiş alacaklar. Tahsilatlar en eski faturaya (FIFO) mahsup edilir;
 * kalan tutarı olan ve vadesi bugünden önce olan satışlar listelenir.
 */
function overdue_rows(?int $customerId = null, ?string $today = null): array {
    $today ??= date('Y-m-d');
    $pay = [];
    foreach (rows('SELECT customer_id, SUM(tutar) t FROM payments GROUP BY customer_id') as $r) $pay[$r['customer_id']] = (float) $r['t'];
    $sql = 'SELECT s.id, s.customer_id, c.ad, c.tip, c.telefon, s.tarih, s.vade_tarihi,
            s.toplam - COALESCE(r.t,0) AS toplam
            FROM sales s JOIN customers c ON c.id = s.customer_id
            LEFT JOIN (SELECT si.sale_id, SUM(cr.toplam) t FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id GROUP BY si.sale_id) r ON r.sale_id = s.id' .
            ($customerId ? ' WHERE s.customer_id = ' . (int) $customerId : '') . '
            ORDER BY s.customer_id, s.vade_tarihi, s.id';
    $out = []; $cur = null; $left = 0.0;
    foreach (rows($sql) as $s) {
        if ($cur !== $s['customer_id']) { $cur = $s['customer_id']; $left = $pay[$cur] ?? 0.0; }
        $tot = (float) $s['toplam'];
        $rem = $tot;
        if ($left >= $tot) { $left -= $tot; $rem = 0.0; } else { $rem = $tot - $left; $left = 0.0; }
        if ($rem > 0.004 && $s['vade_tarihi'] < $today) {
            $s['kalan'] = round($rem, 2);
            $s['gecikme_gun'] = (int) ((strtotime($today) - strtotime($s['vade_tarihi'])) / 86400);
            $out[] = $s;
        }
    }
    usort($out, fn($a, $b) => $b['gecikme_gun'] <=> $a['gecikme_gun']);
    return $out;
}

/** Müşteri ekstresi: borç (satış) / alacak (tahsilat) hareketleri, yürüyen bakiye. */
function statement(int $customerId, string $from = '', string $to = ''): array {
    $mv = rows("SELECT tarih, 'Satış' AS tur, CONCAT('Satış #', id, IF(aciklama='','',CONCAT(' - ', aciklama))) AS aciklama, toplam AS borc, 0 AS alacak, id AS sira FROM sales WHERE customer_id = :c1
        UNION ALL SELECT cr.tarih, 'İade', CONCAT('Satış #', s.id, IF(cr.aciklama='','',CONCAT(' - ', cr.aciklama))), 0, cr.toplam, cr.id FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id JOIN sales s ON s.id = si.sale_id WHERE s.customer_id = :c2
        UNION ALL SELECT tarih, 'Tahsilat', CONCAT(yontem, IF(aciklama='','',CONCAT(' - ', aciklama))), 0, tutar, id FROM payments WHERE customer_id = :c3
        ORDER BY tarih, tur DESC, sira", ['c1' => $customerId, 'c2' => $customerId, 'c3' => $customerId]);
    $bal = 0.0; $open = 0.0; $out = [];
    foreach ($mv as $m) {
        $bal += $m['borc'] - $m['alacak'];
        if ($from !== '' && $m['tarih'] < $from) { $open = $bal; continue; }
        if ($to !== '' && $m['tarih'] > $to) continue;
        $m['bakiye'] = $bal; $out[] = $m;
    }
    return ['opening' => $open, 'rows' => $out, 'closing' => $bal];
}

function financial_summary(string $from = '', string $to = ''): array {
    $period = static function (string $column, string $prefix) use ($from, $to): array {
        $where = ' WHERE 1=1';
        $params = [];
        if ($from !== '') { $where .= " AND $column >= :{$prefix}f"; $params["{$prefix}f"] = $from; }
        if ($to !== '') { $where .= " AND $column <= :{$prefix}t"; $params["{$prefix}t"] = $to; }
        return [$where, $params];
    };
    [$where, $params] = $period('tarih', 's');
    $sales = (float) val('SELECT COALESCE(SUM(toplam),0) FROM sales' . $where, $params);
    [$where, $params] = $period('s.tarih', 'c');
    $cost = (float) val('SELECT COALESCE(SUM(si.adet * si.maliyet_birim),0) FROM sale_items si JOIN sales s ON s.id = si.sale_id' . $where, $params);
    [$where, $params] = $period('tarih', 'r');
    $returns = (float) val('SELECT COALESCE(SUM(toplam),0) FROM customer_returns' . $where, $params);
    [$where, $params] = $period('cr.tarih', 'rc');
    $returnedCost = (float) val('SELECT COALESCE(SUM(cr.adet * cr.maliyet_birim),0) FROM customer_returns cr' . $where, $params);
    [$where, $params] = $period('tarih', 'e');
    $expenses = (float) val('SELECT COALESCE(SUM(tutar),0) FROM expenses' . $where, $params);
    [$where, $params] = $period('tarih', 'd');
    $donations = (float) val('SELECT COALESCE(SUM(tutar),0) FROM donations' . $where, $params);
    $netSales = $sales - $returns;
    $netCost = $cost - $returnedCost;
    $debt = (float) val('SELECT COALESCE((SELECT SUM(tutar) FROM loans),0) - COALESCE((SELECT SUM(tutar) FROM loan_payments),0)');
    return [
        'sales' => $sales, 'returns' => $returns, 'net_sales' => $netSales,
        'cost' => $netCost, 'gross_profit' => $netSales - $netCost,
        'expenses' => $expenses, 'donations' => $donations,
        'profit' => $netSales - $netCost - $expenses + $donations,
        'debt' => $debt,
    ];
}

function sale_lines(int $saleId): array {
    return rows('SELECT si.*, b.kod, b.ad FROM sale_items si JOIN books b ON b.id = si.book_id WHERE sale_id = ?', [$saleId]);
}

/* ---------------- Raporlar ---------------- */

function report_defs(): array {
    return [
        'kitap' => 'Kitap Bazında Satış', 'okul' => 'Okul Bazında Satış', 'kirtasiye' => 'Kırtasiye Bazında Satış',
        'ilce' => 'İl / İlçe Bazında Satış', 'musteri_detay' => 'Müşteri / Kitap Detay (Satış-Tahsilat-Kalan)',
        'cari' => 'Cari Raporu', 'stok' => 'Stok Raporu', 'vadesi' => 'Vadesi Geçmiş Alacak', 'pivot' => 'Pivot: Müşteri × Kitap (Adet)',
        'vade_musteri' => 'Müşteri Bazında Gecikmeler',
    ];
}

/** @return array{0:array,1:array} [başlıklar, satırlar] */
function report(string $key, array $f = []): array {
    $from = $f['from'] ?? ''; $to = $f['to'] ?? '';
    $w = ' WHERE 1=1'; $p = [];
    if ($from !== '') { $w .= ' AND s.tarih >= :from'; $p['from'] = $from; }
    if ($to !== '') { $w .= ' AND s.tarih <= :to'; $p['to'] = $to; }
    if (!empty($f['customer_id'])) { $w .= ' AND s.customer_id = :cid'; $p['cid'] = $f['customer_id']; }
    if (!empty($f['book_id'])) { $w .= ' AND si.book_id = :bid'; $p['bid'] = $f['book_id']; }
    $base = ' FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id';

    switch ($key) {
        case 'kitap':
            $data = rows("SELECT b.id, b.kod, b.ad, SUM(si.adet) adet, ROUND(SUM(si.koli),2) koli, SUM(si.toplam) tutar $base $w GROUP BY b.id ORDER BY tutar DESC", $p);
            $rw = ' WHERE 1=1'; $rp = [];
            if ($from !== '') { $rw .= ' AND cr.tarih >= :rf'; $rp['rf'] = $from; }
            if ($to !== '') { $rw .= ' AND cr.tarih <= :rt'; $rp['rt'] = $to; }
            if (!empty($f['book_id'])) { $rw .= ' AND si.book_id = :rb'; $rp['rb'] = $f['book_id']; }
            $returns = rows('SELECT si.book_id, SUM(cr.adet) adet, SUM(cr.koli) koli, SUM(cr.toplam) tutar
                FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id' . $rw . ' GROUP BY si.book_id', $rp);
            foreach ($returns as $return) {
                foreach ($data as &$line) {
                    if ((int) $line['id'] === (int) $return['book_id']) {
                        $line['adet'] -= $return['adet']; $line['koli'] -= $return['koli']; $line['tutar'] -= $return['tutar'];
                        break;
                    }
                }
                unset($line);
            }
            foreach ($data as &$line) unset($line['id']);
            unset($line);
            return [['Kod', 'Kitap', 'Adet', 'Koli', 'Satış Tutarı'], $data];
        case 'okul': case 'kirtasiye':
            $tip = $key === 'okul' ? 'Okul' : 'Kırtasiye';
            return [[$tip, 'İl', 'İlçe', 'Adet', 'Koli', 'Satış Tutarı'],
                rows("SELECT c.ad, c.il, c.ilce, SUM(si.adet) adet, ROUND(SUM(si.koli),2) koli, SUM(si.toplam) tutar $base $w AND c.tip = '$tip' GROUP BY c.id ORDER BY tutar DESC", $p)];
        case 'ilce':
            return [['İl', 'İlçe', 'Adet', 'Satış Tutarı'],
                rows("SELECT c.il, c.ilce, SUM(si.adet) adet, SUM(si.toplam) tutar $base $w GROUP BY c.il, c.ilce ORDER BY tutar DESC", $p)];
        case 'musteri_detay':
            // Her müşteri için kitap satırları; tahsilat ve kalan müşterinin ilk satırında gösterilir.
            $bal = []; foreach (customer_balances($from, $to) as $b) $bal[$b['id']] = $b;
            $data = rows("SELECT c.id cid, c.ad musteri, b.ad kitap, SUM(si.adet) adet, SUM(si.toplam) tutar $base $w GROUP BY c.id, b.id ORDER BY c.ad, b.ad", $p);
            $out = []; $seen = [];
            foreach ($data as $d) {
                $first = !isset($seen[$d['cid']]); $seen[$d['cid']] = true;
                $out[] = ['musteri' => $d['musteri'], 'kitap' => $d['kitap'], 'adet' => $d['adet'], 'tutar' => $d['tutar'],
                    'tahsilat' => $first ? $bal[$d['cid']]['tahsilat'] : '', 'kalan' => $first ? $bal[$d['cid']]['kalan'] : ''];
            }
            return [['Müşteri', 'Satılan Kitap', 'Adet', 'Satış Tutarı', 'Tahsilat', 'Kalan Alacak'], $out];
        case 'cari':
            $out = [];
            foreach (customer_balances($from, $to) as $b) {
                if (!empty($f['customer_id']) && $b['id'] != $f['customer_id']) continue;
                $out[] = ['ad' => $b['ad'], 'tip' => $b['tip'], 'il' => $b['il'], 'ilce' => $b['ilce'], 'satis' => $b['satis'], 'tahsilat' => $b['tahsilat'], 'kalan' => $b['kalan']];
            }
            return [['Müşteri', 'Tip', 'İl', 'İlçe', 'Toplam Satış', 'Toplam Tahsilat', 'Kalan Alacak'], $out];
        case 'stok':
            $out = [];
            foreach (stock_report() as $s) $out[] = ['kod' => $s['kod'], 'ad' => $s['ad'], 'giris' => $s['giris'], 'satilan' => $s['satilan'], 'diger_cikis' => $s['diger_cikis'],
                'stok' => $s['stok'], 'koli' => $s['koli_adedi'] > 0 ? round($s['stok'] / $s['koli_adedi'], 2) : '', 'kritik' => $s['kritik_stok'], 'durum' => $s['stok'] <= $s['kritik_stok'] ? 'KRİTİK' : 'Yeterli', 'deger' => $s['stok_degeri']];
            return [['Kod', 'Kitap', 'Toplam Giriş', 'Satılan', 'Diğer Çıkış', 'Güncel Stok', 'Koli', 'Kritik Seviye', 'Durum', 'Stok Değeri (Alış)'], $out];
        case 'vadesi':
            $out = [];
            foreach (overdue_rows(!empty($f['customer_id']) ? (int) $f['customer_id'] : null) as $o)
                $out[] = ['ad' => $o['ad'], 'tip' => $o['tip'], 'satis' => '#' . $o['id'], 'tarih' => dtr($o['tarih']), 'vade' => dtr($o['vade_tarihi']), 'gun' => $o['gecikme_gun'], 'kalan' => $o['kalan']];
            return [['Müşteri', 'Tip', 'Satış', 'Satış Tarihi', 'Vade Tarihi', 'Geciken Gün', 'Geciken Tutar'], $out];
        case 'vade_musteri':
            $agg = [];
            foreach (overdue_rows() as $o) {
                $a = &$agg[$o['customer_id']];
                $a ??= ['ad' => $o['ad'], 'tel' => $o['telefon'], 'adet' => 0, 'maks' => 0, 'tutar' => 0.0];
                $a['adet']++; $a['maks'] = max($a['maks'], $o['gecikme_gun']); $a['tutar'] += $o['kalan']; unset($a);
            }
            usort($agg, fn($x, $y) => $y['tutar'] <=> $x['tutar']);
            return [['Müşteri', 'Telefon', 'Geciken Satış Adedi', 'En Uzun Gecikme (gün)', 'Toplam Geciken Tutar'], $agg];
        case 'pivot':
            $books = rows("SELECT DISTINCT b.id, b.ad $base $w ORDER BY b.ad", $p);
            $data = rows("SELECT c.id cid, c.ad, si.book_id, SUM(si.adet) adet $base $w GROUP BY c.id, si.book_id ORDER BY c.ad", $p);
            $m = [];
            foreach ($data as $d) { $m[$d['cid']]['Müşteri'] = $d['ad']; $m[$d['cid']][$d['book_id']] = (int) $d['adet']; }
            $head = ['Müşteri']; foreach ($books as $b) $head[] = $b['ad'];
            $head[] = 'Toplam'; $out = [];
            foreach ($m as $r) {
                $line = [$r['Müşteri']]; $t = 0;
                foreach ($books as $b) { $v = $r[$b['id']] ?? 0; $t += $v; $line[] = $v ?: ''; }
                $line[] = $t; $out[] = $line;
            }
            return [$head, $out];
    }
    return [[], []];
}

/** Tüm tabloları Excel / Google Sheets için hazırlar. */
function export_sets(): array {
    $f = [];
    $books = rows('SELECT kod, ad, alis_fiyati, satis_fiyati, koli_adedi, kritik_stok FROM books ORDER BY ad');
    $cust = rows('SELECT id, tip, ad, il, ilce, yetkili, telefon, eposta, adres FROM customers ORDER BY ad');
    $sales = rows('SELECT s.id satis_no, s.tarih, s.vade_tarihi, c.ad musteri, c.tip, c.il, c.ilce, b.kod, b.ad kitap, si.adet, si.koli, si.birim_fiyat, si.toplam
        FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id ORDER BY s.tarih, s.id');
    $pay = rows('SELECT p.tarih, c.ad musteri, p.tutar, p.yontem, p.aciklama FROM payments p JOIN customers c ON c.id = p.customer_id ORDER BY p.tarih, p.id');
    $stockIn = rows('SELECT i.tarih, b.kod, b.ad kitap, i.adet, i.teslim_alan, i.aciklama FROM stock_in i JOIN books b ON b.id = i.book_id ORDER BY i.tarih, i.id');
    $stockOut = rows('SELECT o.tarih, b.kod, b.ad kitap, c.ad musteri, o.goturen_kisi, o.adet, o.koli, o.aciklama
        FROM stock_out o JOIN books b ON b.id = o.book_id JOIN customers c ON c.id = o.customer_id ORDER BY o.tarih, o.id');
    $customerReturns = rows('SELECT cr.tarih, s.id satis_no, c.ad musteri, b.kod, b.ad kitap, cr.adet, cr.toplam, cr.aciklama FROM customer_returns cr JOIN sale_items si ON si.id = cr.sale_item_id JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id ORDER BY cr.tarih, cr.id');
    $supplierReturns = rows('SELECT r.tarih, r.tedarikci, b.kod, b.ad kitap, r.adet, r.toplam, r.aciklama FROM supplier_returns r JOIN books b ON b.id = r.book_id ORDER BY r.tarih, r.id');
    $expenses = rows('SELECT tarih, kategori, tutar, yontem, aciklama FROM expenses ORDER BY tarih, id');
    $donations = rows('SELECT tarih, bagisci, tutar, yontem, aciklama FROM donations ORDER BY tarih, id');
    $loans = rows('SELECT l.tarih, l.alacakli, l.tutar, COALESCE(p.tutar,0) odenen, l.tutar - COALESCE(p.tutar,0) kalan, l.aciklama FROM loans l LEFT JOIN (SELECT loan_id, SUM(tutar) tutar FROM loan_payments GROUP BY loan_id) p ON p.loan_id = l.id ORDER BY l.tarih, l.id');
    $loanPayments = rows('SELECT p.tarih, l.alacakli, p.tutar, p.yontem, p.aciklama FROM loan_payments p JOIN loans l ON l.id = p.loan_id ORDER BY p.tarih, p.id');
    $profitLoss = financial_summary();
    return [
        'Kitaplar' => [['Kod', 'Kitap Adı', 'Alış Fiyatı', 'Satış Fiyatı', 'Koli Adedi', 'Kritik Stok'], $books],
        'Müşteriler' => [['No', 'Tip', 'Ad', 'İl', 'İlçe', 'Yetkili', 'Telefon', 'E-posta', 'Adres'], $cust],
        'Stok Girişleri' => [['Tarih', 'Kod', 'Kitap', 'Adet', 'Teslim Alan', 'Açıklama'], $stockIn],
        'Stok Çıkışları' => [['Tarih', 'Kod', 'Kitap', 'Müşteri', 'Götüren Kişi', 'Adet', 'Koli', 'Açıklama'], $stockOut],
        'Müşteri İadeleri' => [['Tarih', 'Satış No', 'Müşteri', 'Kod', 'Kitap', 'Adet', 'Tutar', 'Açıklama'], $customerReturns],
        'Tedarikçi İadeleri' => [['Tarih', 'Tedarikçi', 'Kod', 'Kitap', 'Adet', 'Tutar', 'Açıklama'], $supplierReturns],
        'Giderler' => [['Tarih', 'Kategori', 'Tutar', 'Yöntem', 'Açıklama'], $expenses],
        'Bağışlar' => [['Tarih', 'Bağışçı', 'Tutar', 'Yöntem', 'Açıklama'], $donations],
        'Alınan Borçlar' => [['Tarih', 'Alacaklı', 'Alınan Borç', 'Ödenen', 'Kalan', 'Açıklama'], $loans],
        'Borç Ödemeleri' => [['Tarih', 'Alacaklı', 'Tutar', 'Yöntem', 'Açıklama'], $loanPayments],
        'Kâr Zarar' => [['Satışlar', 'Müşteri İadeleri', 'Net Satış', 'Kitap Maliyeti', 'Giderler', 'Bağışlar', 'Net Kâr/Zarar', 'Kalan Borç'], [[
            $profitLoss['sales'], $profitLoss['returns'], $profitLoss['net_sales'], $profitLoss['cost'], $profitLoss['expenses'], $profitLoss['donations'], $profitLoss['profit'], $profitLoss['debt'],
        ]]],
        'Satışlar' => [['Satış No', 'Tarih', 'Vade', 'Müşteri', 'Tip', 'İl', 'İlçe', 'Kod', 'Kitap', 'Adet', 'Koli', 'Birim Fiyat', 'Tutar'], $sales],
        'Tahsilatlar' => [['Tarih', 'Müşteri', 'Tutar', 'Yöntem', 'Açıklama'], $pay],
        'Güncel Stok' => report('stok', $f),
        'Cari Özet' => report('cari', $f),
        'Müşteri Detay' => report('musteri_detay', $f),
        'Vadesi Geçmiş' => report('vadesi', $f),
    ];
}

/* ---------------- Excel (.xlsx) ---------------- */

function xlsx_col(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }

/** Bağımlılıksız minimal .xlsx üretir ve indirir. $sets: ad => [başlıklar, satırlar] */
function xlsx_download(string $filename, array $sets): never {
    $tmp = tempnam(sys_get_temp_dir(), 'xl');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $n = 0;
    foreach ($sets as $name => [$head, $data]) {
        $n++;
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="30" width="18" customWidth="1"/></cols><sheetData>';
        $all = array_merge([array_values($head)], array_map('array_values', $data));
        foreach ($all as $ri => $r) {
            $x .= '<row r="' . ($ri + 1) . '">';
            foreach ($r as $ci => $v) {
                $ref = xlsx_col($ci) . ($ri + 1);
                $style = $ri === 0 ? ' s="1"' : '';
                if ($ri > 0 && (is_int($v) || is_float($v) || (is_string($v) && $v !== '' && is_numeric($v) && !preg_match('/^0\d/', $v))))
                    $x .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
                else
                    $x .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . htmlspecialchars((string) $v, ENT_XML1) . '</t></is></c>';
            }
            $x .= '</row>';
        }
        $x .= '</sheetData></worksheet>';
        $zip->addFromString("xl/worksheets/sheet$n.xml", $x);
        $ct .= "<Override PartName=\"/xl/worksheets/sheet$n.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        $wb .= '<sheet name="' . htmlspecialchars(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', (string) $name), 0, 31), ENT_XML1) . "\" sheetId=\"$n\" r:id=\"rId$n\"/>";
        $rels .= "<Relationship Id=\"rId$n\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$n.xml\"/>";
    }
    $rels .= '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $zip->addFromString('[Content_Types].xml', $ct . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E1F2"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp); unlink($tmp); exit;
}

/* ---------------- Google Sheets ---------------- */

function gs_configured(): bool { return cfg('gsheets_id') && is_file((string) cfg('gsheets_key_file')); }

function gs_request(string $method, string $url, ?array $body, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string) $res, true) ?? [];
    if ($code >= 400) throw new RuntimeException('Google Sheets hatası (' . $code . '): ' . ($j['error']['message'] ?? $res));
    return $j;
}

function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function gs_token(): string {
    $key = json_decode((string) file_get_contents(cfg('gsheets_key_file')), true);
    $now = time();
    $in = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . b64url(json_encode([
        'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
    openssl_sign($in, $sig, $key['private_key'], OPENSSL_ALGO_SHA256);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $in . '.' . b64url($sig)])]);
    $j = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    if (empty($j['access_token'])) throw new RuntimeException('Google token alınamadı: ' . ($j['error_description'] ?? 'bilinmeyen hata'));
    return $j['access_token'];
}

/** Tüm veri setlerini ilgili sekmelere yazar (sekme yoksa oluşturur, içeriği değiştirir). */
function gs_sync(): int {
    if (!gs_configured()) throw new RuntimeException('Google Sheets ayarlı değil (config.php: gsheets_id ve servis hesabı anahtarı).');
    $id = cfg('gsheets_id'); $tok = gs_token();
    $api = "https://sheets.googleapis.com/v4/spreadsheets/$id";
    $meta = gs_request('GET', $api . '?fields=sheets.properties.title', null, $tok);
    $have = array_column(array_column($meta['sheets'] ?? [], 'properties'), 'title');
    $sets = export_sets();
    $add = [];
    foreach (array_keys($sets) as $name) if (!in_array($name, $have, true)) $add[] = ['addSheet' => ['properties' => ['title' => $name]]];
    if ($add) gs_request('POST', $api . ':batchUpdate', ['requests' => $add], $tok);
    $names = array_keys($sets);
    gs_request('POST', $api . '/values:batchClear', ['ranges' => array_map(fn($n) => "'$n'", $names)], $tok);
    $data = [];
    foreach ($sets as $name => [$head, $rws]) {
        $vals = [array_values($head)];
        foreach ($rws as $r) $vals[] = array_map(fn($v) => is_numeric($v) && !preg_match('/^0\d/', (string) $v) ? $v + 0 : (string) $v, array_values($r));
        $data[] = ['range' => "'$name'!A1", 'values' => $vals];
    }
    gs_request('POST', $api . '/values:batchUpdate', ['valueInputOption' => 'USER_ENTERED', 'data' => $data], $tok);
    return count($sets);
}

/** Kayıt işlemlerinden sonra çağrılır; gsheets_auto açıksa besler. */
function after_write(): void {
    if (cfg('gsheets_auto') && gs_configured()) {
        try { gs_sync(); } catch (Throwable $e) { flash('Kayıt yapıldı ancak Google Sheets güncellenemedi: ' . $e->getMessage()); }
    }
}

function reminder_channels_configured(): array {
    return [
        'email' => (bool) cfg('mail_from_email'),
        'sms' => (bool) (cfg('sms_twilio_sid') && cfg('sms_twilio_token') && cfg('sms_twilio_from')),
    ];
}

function sms_e164(string $phone): ?string {
    $trimmed = trim($phone);
    $digits = preg_replace('/\D+/', '', $trimmed);
    if (str_starts_with($trimmed, '+') && strlen($digits) >= 8 && strlen($digits) <= 15) return '+' . $digits;
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = '90' . substr($digits, 1);
    elseif (strlen($digits) === 10) $digits = '90' . $digits;
    if (strlen($digits) >= 12 && strlen($digits) <= 15) return '+' . $digits;
    return null;
}

function send_reminder_email(array $customer, float $balance): void {
    $from = (string) cfg('mail_from_email');
    if (!filter_var($customer['eposta'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Müşterinin geçerli e-posta adresi yok.');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('MAIL_FROM_EMAIL geçerli bir e-posta adresi olmalıdır.');
    $fromName = mb_encode_mimeheader(str_replace(["\r", "\n"], '', (string) cfg('mail_from_name')), 'UTF-8');
    $subject = mb_encode_mimeheader('Cari hesap bakiye bilgilendirmesi', 'UTF-8');
    $contact = trim((string) cfg('reminder_contact'));
    $body = "Sayın {$customer['ad']},\n\n"
        . 'Cari hesabınızda ' . money($balance) . " bakiye görünmektedir.\n"
        . "Ödemeniz varsa lütfen bu mesajı dikkate almayınız. Hesap mutabakatı veya ödeme bilgisi için bizimle iletişime geçebilirsiniz."
        . ($contact !== '' ? "\nİletişim: $contact" : '')
        . "\n\n" . (string) cfg('mail_from_name');
    $headers = "From: $fromName <$from>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    if (!mail($customer['eposta'], $subject, $body, $headers)) throw new RuntimeException('PHP mail() e-posta gönderimini kabul etmedi.');
}

function send_reminder_sms(array $customer, float $balance): void {
    $to = sms_e164((string) $customer['telefon']);
    if (!$to) throw new RuntimeException('Müşterinin telefonu uluslararası biçimde geçerli değil.');
    $sid = (string) cfg('sms_twilio_sid');
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
    $contact = trim((string) cfg('reminder_contact'));
    $body = (string) cfg('mail_from_name') . ': Cari hesabınızda ' . money($balance)
        . ' bakiye görünmektedir. Ödemeniz varsa bu mesajı dikkate almayınız.'
        . ($contact !== '' ? ' İletişim: ' . $contact : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $sid . ':' . (string) cfg('sms_twilio_token'),
        CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => (string) cfg('sms_twilio_from'), 'Body' => $body]),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($response === false) throw new RuntimeException('Twilio bağlantı hatası: ' . $curlError);
    $result = json_decode($response, true) ?? [];
    if ($status < 200 || $status >= 300) throw new RuntimeException('Twilio SMS hatası (' . $status . '): ' . ($result['message'] ?? 'sağlayıcı isteği reddetti'));
}

/**
 * Her müşteri ve kanal için haftada en fazla bir başarılı hatırlatma gönderir.
 * Başarısız veya iletişim bilgisi eksik kayıtlar sonraki çalıştırmada tekrar denenebilir.
 */
function send_weekly_reminders(): array {
    if (cfg('app_storage') === 'sqlite') {
        throw new RuntimeException('Yerel dosya modunda gerçek e-posta/SMS gönderimi devre dışıdır.');
    }
    if (cfg('app_storage') === 'sqlite') {
        throw new RuntimeException('Yerel dosya modunda gerçek e-posta/SMS gönderimi devre dışıdır.');
    }
    $configured = reminder_channels_configured();
    if (!$configured['email']) throw new RuntimeException('E-posta için MAIL_FROM_EMAIL yapılandırılmalıdır.');
    if (!$configured['sms']) throw new RuntimeException('SMS için Twilio hesap bilgileri yapılandırılmalıdır.');

    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
    $weekStart = $now->modify('-' . ((int) $now->format('N') - 1) . ' days')->format('Y-m-d');
    $counts = ['müşteri' => 0, 'gönderildi' => 0, 'başarısız' => 0, 'atlandı' => 0];
    $senders = [
        'E-posta' => fn($customer, $balance) => send_reminder_email($customer, $balance),
        'SMS' => fn($customer, $balance) => send_reminder_sms($customer, $balance),
    ];

    foreach (customer_balances() as $customer) {
        $balance = (float) $customer['kalan'];
        if ($balance <= 0.004) continue;
        $counts['müşteri']++;
        foreach ($senders as $channel => $send) {
            $existing = row('SELECT durum FROM notification_log WHERE customer_id = ? AND hafta_baslangici = ? AND kanal = ?',
                [$customer['id'], $weekStart, $channel]);
            if (($existing['durum'] ?? '') === 'gönderildi') continue;

            $target = $channel === 'E-posta' ? (string) $customer['eposta'] : (string) $customer['telefon'];
            try {
                if ($target === '') throw new DomainException('İletişim bilgisi eksik.');
                $send($customer, $balance);
                $status = 'gönderildi';
                $error = null;
            } catch (DomainException $e) {
                $status = 'atlandı';
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $status = 'başarısız';
                $error = $e->getMessage();
            }

            q('INSERT INTO notification_log (customer_id, hafta_baslangici, kanal, durum, hedef, hata) VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE durum=VALUES(durum), hedef=VALUES(hedef), hata=VALUES(hata)',
                [$customer['id'], $weekStart, $channel, $status, $target, $error]);
            $counts[$status]++;
        }
    }
    return $counts;
}

/* ---------------- Düzen ---------------- */

function layout_head(string $title, string $page): void {
    $menu = ['dashboard' => '📊 Panel', 'books' => '📚 Kitaplar', 'stock_in' => 'Stok Girişi', 'stock_out' => 'Stok Çıkışı',
        'stock_current' => 'Güncel Stok', 'stock_in_history' => 'Giriş Hareketleri', 'stock_out_history' => 'Çıkış Hareketleri',
        'sales' => '🚚 Satışlar', 'returns_customer' => 'Müşteri İadesi', 'returns_supplier' => 'Tedarikçi İadesi',
        'customers' => '🏫 Müşteriler', 'payments' => '💰 Tahsilat', 'cari' => '🧾 Cari / Ekstre', 'overdue' => '⏰ Vadeli Alacak',
        'expenses' => 'Giderler', 'donations' => 'Bağışlar', 'loans' => 'Alınan Borçlar', 'finance' => 'Kâr / Zarar',
        'notifications' => '🔔 Bildirimler', 'reports' => '📋 Raporlar', 'export' => '📤 Excel / Sheets'];
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#101b33"><title>' . h($title) . ' - Kitap Cari</title><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head><body><nav class="no-print"><b class="brand">📚 Kitap Cari</b><div class="nav-links">';
    foreach ($menu as $k => $v) echo '<a href="?p=' . $k . '"' . ($k === $page ? ' class="on"' : '') . '>' . $v . '</a>';
    echo '</div><a href="?p=logout" class="out">Çıkış</a></nav><main>';
    if ($m = flash()) echo '<div class="flash">' . h($m) . '</div>';
    echo '<h1>' . h($title) . '</h1>';
}
function layout_foot(): void { echo '</main><footer>Kitap Dağıtım & Cari</footer></body></html>'; }

function table(array $head, array $data, array $opts = []): void {
    $money = $opts['money'] ?? []; $ints = $opts['int'] ?? []; $totals = $opts['totals'] ?? [];
    echo '<div class="table-wrap"><table class="sortable-table"><thead><tr>';
    foreach ($head as $hh) echo '<th scope="col"><button class="sort-button" type="button"><span>' . h($hh) . '</span><span class="sort-indicator" aria-hidden="true">↕</span></button></th>';
    echo '</tr></thead><tbody>';
    $sum = [];
    foreach ($data as $r) {
        echo '<tr>';
        foreach (array_values($r) as $i => $v) {
            if (in_array($i, $totals, true) && is_numeric($v)) $sum[$i] = ($sum[$i] ?? 0) + $v;
            if (in_array($i, $money, true) && $v !== '' && $v !== null) echo '<td class="r">' . money($v) . '</td>';
            elseif (in_array($i, $ints, true) && $v !== '' && $v !== null) echo '<td class="r">' . num($v) . '</td>';
            else echo '<td>' . ($opts['raw'][$i] ?? false ? $v : h($v)) . '</td>';
        }
        echo '</tr>';
    }
    if (!$data) echo '<tr><td colspan="' . count($head) . '" class="muted">Kayıt yok.</td></tr>';
    elseif ($totals) {
        echo '<tr class="tot">';
        foreach ($head as $i => $_) echo isset($sum[$i]) ? '<td class="r">' . (in_array($i, $money, true) ? money($sum[$i]) : num($sum[$i])) . '</td>' : '<td>' . ($i === 0 ? 'TOPLAM' : '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
