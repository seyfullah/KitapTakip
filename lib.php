<?php
declare(strict_types=1);

function cfg(string $k) { static $c; $c ??= require __DIR__ . '/config.php'; return $c[$k] ?? null; }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
            cfg('db_user'), cfg('db_pass'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
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

/* ---------------- İş mantığı ---------------- */

/** Güncel stok = toplam giriş - toplam satış (otomatik düşüm). */
function stock_report(): array {
    return rows("SELECT b.id, b.kod, b.ad, b.yayinevi, b.sinif, b.koli_adedi, b.kritik_stok,
        COALESCE(si.t,0) AS giris, COALESCE(sa.t,0) AS satilan,
        COALESCE(si.t,0) - COALESCE(sa.t,0) AS stok,
        (COALESCE(si.t,0) - COALESCE(sa.t,0)) * b.alis_fiyati AS stok_degeri
        FROM books b
        LEFT JOIN (SELECT book_id, SUM(adet) t FROM stock_in GROUP BY book_id) si ON si.book_id = b.id
        LEFT JOIN (SELECT book_id, SUM(adet) t FROM sale_items GROUP BY book_id) sa ON sa.book_id = b.id
        ORDER BY b.ad");
}
function book_stock(int $bookId): int {
    $r = row('SELECT (SELECT COALESCE(SUM(adet),0) FROM stock_in WHERE book_id = :b) - (SELECT COALESCE(SUM(adet),0) FROM sale_items WHERE book_id = :b2) s', ['b' => $bookId, 'b2' => $bookId]);
    return (int) $r['s'];
}

/** Müşteri bazında satış / tahsilat / kalan. */
function customer_balances(string $from = '', string $to = ''): array {
    $sw = ''; $pw = ''; $p = [];
    if ($from !== '') { $sw .= ' AND tarih >= :f1'; $pw .= ' AND tarih >= :f2'; $p['f1'] = $p['f2'] = $from; }
    if ($to !== '') { $sw .= ' AND tarih <= :t1'; $pw .= ' AND tarih <= :t2'; $p['t1'] = $p['t2'] = $to; }
    return rows("SELECT c.id, c.tip, c.ad, c.il, c.ilce, c.telefon,
        COALESCE(s.t,0) AS satis, COALESCE(p.t,0) AS tahsilat, COALESCE(s.t,0) - COALESCE(p.t,0) AS kalan
        FROM customers c
        LEFT JOIN (SELECT customer_id, SUM(toplam) t FROM sales WHERE 1=1 $sw GROUP BY customer_id) s ON s.customer_id = c.id
        LEFT JOIN (SELECT customer_id, SUM(tutar) t FROM payments WHERE 1=1 $pw GROUP BY customer_id) p ON p.customer_id = c.id
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
    $sql = 'SELECT s.id, s.customer_id, c.ad, c.tip, c.telefon, s.tarih, s.vade_tarihi, s.toplam
            FROM sales s JOIN customers c ON c.id = s.customer_id' . ($customerId ? ' WHERE s.customer_id = ' . (int) $customerId : '') . '
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
        UNION ALL SELECT tarih, 'Tahsilat', CONCAT(yontem, IF(aciklama='','',CONCAT(' - ', aciklama))), 0, tutar, id FROM payments WHERE customer_id = :c2
        ORDER BY tarih, tur DESC, sira", ['c1' => $customerId, 'c2' => $customerId]);
    $bal = 0.0; $open = 0.0; $out = [];
    foreach ($mv as $m) {
        $bal += $m['borc'] - $m['alacak'];
        if ($from !== '' && $m['tarih'] < $from) { $open = $bal; continue; }
        if ($to !== '' && $m['tarih'] > $to) continue;
        $m['bakiye'] = $bal; $out[] = $m;
    }
    return ['opening' => $open, 'rows' => $out, 'closing' => $bal];
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
            return [['Kod', 'Kitap', 'Yayınevi', 'Sınıf', 'Adet', 'Koli', 'Satış Tutarı'],
                rows("SELECT b.kod, b.ad, b.yayinevi, b.sinif, SUM(si.adet) adet, ROUND(SUM(si.koli),2) koli, SUM(si.toplam) tutar $base $w GROUP BY b.id ORDER BY tutar DESC", $p)];
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
            foreach (stock_report() as $s) $out[] = ['kod' => $s['kod'], 'ad' => $s['ad'], 'yayinevi' => $s['yayinevi'], 'sinif' => $s['sinif'], 'giris' => $s['giris'], 'satilan' => $s['satilan'],
                'stok' => $s['stok'], 'koli' => $s['koli_adedi'] > 0 ? round($s['stok'] / $s['koli_adedi'], 2) : '', 'kritik' => $s['kritik_stok'], 'durum' => $s['stok'] <= $s['kritik_stok'] ? 'KRİTİK' : 'Yeterli', 'deger' => $s['stok_degeri']];
            return [['Kod', 'Kitap', 'Yayınevi', 'Sınıf', 'Toplam Giriş', 'Satılan', 'Güncel Stok', 'Koli', 'Kritik Seviye', 'Durum', 'Stok Değeri (Alış)'], $out];
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
    $books = rows('SELECT kod, ad, yayinevi, sinif, alis_fiyati, satis_fiyati, koli_adedi, kritik_stok FROM books ORDER BY ad');
    $cust = rows('SELECT id, tip, ad, il, ilce, yetkili, telefon, eposta, adres FROM customers ORDER BY ad');
    $sales = rows('SELECT s.id satis_no, s.tarih, s.vade_tarihi, c.ad musteri, c.tip, c.il, c.ilce, b.kod, b.ad kitap, si.adet, si.koli, si.birim_fiyat, si.toplam
        FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customers c ON c.id = s.customer_id JOIN books b ON b.id = si.book_id ORDER BY s.tarih, s.id');
    $pay = rows('SELECT p.tarih, c.ad musteri, p.tutar, p.yontem, p.aciklama FROM payments p JOIN customers c ON c.id = p.customer_id ORDER BY p.tarih, p.id');
    $stockIn = rows('SELECT i.tarih, b.kod, b.ad kitap, i.adet, i.aciklama FROM stock_in i JOIN books b ON b.id = i.book_id ORDER BY i.tarih, i.id');
    return [
        'Kitaplar' => [['Kod', 'Kitap Adı', 'Yayınevi', 'Sınıf', 'Alış Fiyatı', 'Satış Fiyatı', 'Koli Adedi', 'Kritik Stok'], $books],
        'Müşteriler' => [['No', 'Tip', 'Ad', 'İl', 'İlçe', 'Yetkili', 'Telefon', 'E-posta', 'Adres'], $cust],
        'Stok Girişleri' => [['Tarih', 'Kod', 'Kitap', 'Adet', 'Açıklama'], $stockIn],
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

/* ---------------- Düzen ---------------- */

function layout_head(string $title, string $page): void {
    $menu = ['dashboard' => '📊 Panel', 'books' => '📚 Kitaplar', 'stock' => '📦 Stok', 'customers' => '🏫 Müşteriler', 'sales' => '🚚 Satışlar',
        'payments' => '💰 Tahsilat', 'cari' => '🧾 Cari / Ekstre', 'overdue' => '⏰ Vadeli Alacak', 'reports' => '📋 Raporlar', 'export' => '📤 Excel / Sheets'];
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' - Kitap Cari</title><link rel="stylesheet" href="assets/style.css"></head><body><nav class="no-print"><b>Kitap Dağıtım & Cari</b>';
    foreach ($menu as $k => $v) echo '<a href="?p=' . $k . '"' . ($k === $page ? ' class="on"' : '') . '>' . $v . '</a>';
    echo '<a href="?p=logout" class="out">Çıkış</a></nav><main>';
    if ($m = flash()) echo '<div class="flash">' . h($m) . '</div>';
    echo '<h1>' . h($title) . '</h1>';
}
function layout_foot(): void { echo '</main></body></html>'; }

function table(array $head, array $data, array $opts = []): void {
    $money = $opts['money'] ?? []; $ints = $opts['int'] ?? []; $totals = $opts['totals'] ?? [];
    echo '<table><thead><tr>';
    foreach ($head as $hh) echo '<th>' . h($hh) . '</th>';
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
    echo '</tbody></table>';
}
