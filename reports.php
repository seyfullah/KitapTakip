<?php require '_init.php';

$defs = report_defs();
$key = get('r', 'kitap'); if (!isset($defs[$key])) $key = 'kitap';
$f = ['from' => get('from'), 'to' => get('to'), 'customer_id' => get('customer_id'), 'book_id' => get('book_id')];
[$head, $data] = report($key, $f);
if (get('xlsx')) xlsx_download("rapor_$key.xlsx", [$defs[$key] => [$head, $data]]);

$moneyCols = [
    'kitap' => [3], 'okul' => [4], 'kirtasiye' => [4], 'ilce' => [3], 'musteri_detay' => [3, 4, 5], 'cari' => [4, 5, 6], 'stok' => [8], 'vadesi' => [6], 'vade_musteri' => [4], 'pivot' => [],
][$key];
$intCols = ['kitap' => [2], 'okul' => [3], 'kirtasiye' => [3], 'ilce' => [2], 'musteri_detay' => [2], 'stok' => [2, 3, 4, 5, 6], 'pivot' => range(1, count($head) - 1)][$key] ?? [];
$totCols = array_merge($moneyCols, in_array($key, ['pivot', 'stok'], true) ? $intCols : array_slice($intCols, 0, 1));

layout_head($defs[$key], 'reports');
echo '<form class="f no-print"><input type="hidden" name="p" value="reports"><label>Rapor' . select('r', $defs, $key) . '</label><label>Başlangıç<input type="date" name="from" value="' . h($f['from']) . '"></label><label>Bitiş<input type="date" name="to" value="' . h($f['to']) . '"></label><label>Müşteri' . select('customer_id', customer_opts(true), $f['customer_id']) . '</label><label>Kitap' . select('book_id', book_opts(true), $f['book_id']) . '</label><button>Göster</button><button type="button" onclick="print()">🖨 Yazdır</button><a class="btn alt" href="?' . h(http_build_query($_GET + ['xlsx' => 1])) . '">Excel</a></form>';
table($head, $data, ['money' => $moneyCols, 'int' => $intCols, 'totals' => $totCols]);
echo '<p class="muted no-print">Tarih, müşteri ve kitap filtreleri ilgili raporlarda uygulanır. Pivot rapor: satırlarda müşteri, sütunlarda kitap.</p>';
layout_foot();
