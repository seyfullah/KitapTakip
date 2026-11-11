<?php require '_init.php';

$cid = (int) get('customer_id');
$all = overdue_rows($cid ?: null);
layout_head('Vadeli Alacak Takibi', 'overdue');
echo '<form class="f no-print"><input type="hidden" name="p" value="overdue"><label>Müşteri' . select('customer_id', customer_opts(true), $cid) . '</label><button>Filtrele</button><a class="btn alt" href="export.php&only=vadesi">Excel</a></form>';
echo '<div class="cards"><div class="kpi bad"><small>Toplam Geciken Tutar</small><div>' . money(array_sum(array_column($all, 'kalan'))) . '</div></div><div class="kpi"><small>Geciken Satış Adedi</small><div>' . count($all) . '</div></div></div>';
$data = [];
foreach ($all as $o) $data[] = ['<a href="cari.php&customer_id=' . $o['customer_id'] . '">' . h($o['ad']) . '</a>', $o['telefon'], '#' . $o['id'], dtr($o['tarih']), dtr($o['vade_tarihi']), $o['gecikme_gun'] . ' gün', $o['kalan']];
table(['Müşteri', 'Telefon', 'Satış', 'Satış Tarihi', 'Vade', 'Gecikme', 'Geciken Tutar'], $data, ['money' => [6], 'totals' => [6], 'raw' => [0 => true]]);
echo '<h2>Müşteri Bazında Gecikmeler</h2>';
[$h, $d] = report('vade_musteri');
table($h, $d, ['money' => [4], 'totals' => [4]]);
echo '<p class="muted">Not: Tahsilatlar en eski vadeli satışa (FIFO) mahsup edilir; kalan tutarı olan ve vadesi geçmiş satışlar gecikmiş sayılır.</p>';
layout_foot();
