<?php
$cid = (int) get('customer_id'); $from = get('from'); $to = get('to');
if (!$cid) {
    layout_head('Cari Hesap Özeti', 'cari');
    echo '<form class="f no-print"><input type="hidden" name="p" value="cari"><label>Başlangıç<input type="date" name="from" value="' . h($from) . '"></label><label>Bitiş<input type="date" name="to" value="' . h($to) . '"></label><button>Filtrele</button></form>';
    $data = [];
    foreach (customer_balances($from, $to) as $b) $data[] = ['<a href="?p=cari&customer_id=' . $b['id'] . '">' . h($b['ad']) . '</a>', $b['tip'], $b['il'] . ' / ' . $b['ilce'], $b['satis'], $b['tahsilat'], $b['kalan']];
    table(['Müşteri', 'Tip', 'İl / İlçe', 'Toplam Satış', 'Toplam Tahsilat', 'Kalan Alacak'], $data, ['money' => [3, 4, 5], 'totals' => [3, 4, 5], 'raw' => [0 => true]]);
    layout_foot(); exit;
}
$c = row('SELECT * FROM customers WHERE id = ?', [$cid]) or redirect('?p=cari');
$st = statement($cid, $from, $to);
$data = [];
if ($from !== '') $data[] = [dtr($from), 'Devir', 'Önceki dönem bakiyesi', '', '', $st['opening']];
foreach ($st['rows'] as $r) $data[] = [dtr($r['tarih']), $r['tur'], $r['aciklama'], $r['borc'] ?: '', $r['alacak'] ?: '', $r['bakiye']];
$head = ['Tarih', 'Tür', 'Açıklama', 'Borç (Satış)', 'Alacak (Tahsilat / İade)', 'Bakiye'];
if (get('xlsx')) {
    $items = report('musteri_detay', ['customer_id' => $cid, 'from' => $from, 'to' => $to]);
    xlsx_download('ekstre_' . $cid . '.xlsx', ['Ekstre' => [$head, $data], 'Kitap Detay' => $items]);
}
layout_head('Müşteri Ekstresi', 'cari');
echo '<form class="f no-print"><input type="hidden" name="p" value="cari"><label>Müşteri' . select('customer_id', customer_opts(true), $cid) . '</label><label>Başlangıç<input type="date" name="from" value="' . h($from) . '"></label><label>Bitiş<input type="date" name="to" value="' . h($to) . '"></label><button>Göster</button><button type="button" onclick="print()">🖨 Yazdır</button><a class="btn alt" href="?' . h(http_build_query($_GET + ['xlsx' => 1])) . '">Excel</a></form>';
echo '<div class="card"><b>' . h($c['ad']) . '</b> (' . h($c['tip']) . ')<br>' . h($c['il'] . ' / ' . $c['ilce']) . ' · ' . h($c['telefon']) . ' · ' . h($c['yetkili']) . '<br>' . h($c['adres']) . '<br>Dönem: ' . ($from ? dtr($from) : 'başlangıç') . ' – ' . ($to ? dtr($to) : dtr(date('Y-m-d'))) . '</div>';
table($head, $data, ['money' => [3, 4, 5], 'totals' => [3, 4]]);
echo '<div class="kpi"><small>Güncel Bakiye (Kalan Alacak)</small><div>' . money($st['closing']) . '</div></div>';
echo '<h2>Müşteri / Kitap Özeti</h2>';
[$h2, $d2] = report('musteri_detay', ['customer_id' => $cid, 'from' => $from, 'to' => $to]);
table($h2, $d2, ['int' => [2], 'money' => [3, 4, 5]]);
layout_foot();
