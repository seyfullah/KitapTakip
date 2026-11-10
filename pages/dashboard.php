<?php
$stock = stock_report();
$totStock = array_sum(array_column($stock, 'stok'));
$finance = financial_summary();
$sale = $finance['net_sales'];
$paid = (float) val('SELECT COALESCE(SUM(tutar),0) FROM payments');
$od = overdue_rows();
$crit = array_filter($stock, fn($s) => $s['stok'] <= $s['kritik_stok']);

$topBooks = rows('SELECT b.ad, SUM(si.adet) adet, SUM(si.toplam) tutar FROM sale_items si JOIN books b ON b.id = si.book_id GROUP BY b.id ORDER BY tutar DESC LIMIT 10');
$topOf = fn($tip) => rows('SELECT c.ad, SUM(s.toplam) tutar FROM sales s JOIN customers c ON c.id = s.customer_id WHERE c.tip = ? GROUP BY c.id ORDER BY tutar DESC LIMIT 10', [$tip]);
$schools = $topOf('Okul'); $shops = $topOf('Kırtasiye');
$topCust = rows('SELECT c.ad, SUM(s.toplam) tutar FROM sales s JOIN customers c ON c.id = s.customer_id GROUP BY c.id ORDER BY tutar DESC LIMIT 10');
$ilce = rows('SELECT CONCAT(c.il, " / ", c.ilce) ad, SUM(s.toplam) tutar FROM sales s JOIN customers c ON c.id = s.customer_id GROUP BY c.il, c.ilce ORDER BY tutar DESC LIMIT 12');
$monthPay = rows('SELECT DATE_FORMAT(tarih, "%Y-%m") ad, SUM(tutar) tutar FROM payments GROUP BY ad ORDER BY ad DESC LIMIT 12');
$balances = customer_balances();
$soldUnits = [];
foreach (rows('SELECT s.customer_id, SUM(si.adet) adet FROM sale_items si JOIN sales s ON s.id = si.sale_id GROUP BY s.customer_id') as $u) $soldUnits[$u['customer_id']] = (int) $u['adet'];
$customerRanking = array_map(fn($b) => $b + ['adet' => $soldUnits[$b['id']] ?? 0], $balances);
usort($customerRanking, fn($a, $b) => $b['satis'] <=> $a['satis']);
$topSalesCustomers = array_slice($customerRanking, 0, 10);
$recv = array_values(array_filter($balances, fn($b) => $b['kalan'] > 0));
usort($recv, fn($a, $b) => $b['kalan'] <=> $a['kalan']);
$recv = array_slice($recv, 0, 10);

layout_head('Yönetici Paneli', 'dashboard');
echo '<div class="cards">';
foreach ([['Toplam Stok', num($totStock) . ' adet', ''], ['Net Satış', money($sale), ''], ['Toplam Tahsilat', money($paid), ''], ['Toplam Alacak', money($sale - $paid), ''],
    ['Kâr / Zarar', money($finance['profit']), $finance['profit'] < 0 ? 'bad' : ''], ['Kalan Borç', money($finance['debt']), ''],
    ['Vadesi Geçmiş Alacak', money(array_sum(array_column($od, 'kalan'))), 'bad'], ['Kritik Stoklu Kitap', count($crit), $crit ? 'bad' : '']] as [$l, $v, $c])
    echo '<div class="kpi ' . $c . '"><small>' . $l . '</small><div>' . $v . '</div></div>';
echo '</div>';
?>
<div class="grid2">
  <div class="chart"><b>Kitap Bazında Satış (TL)</b><canvas id="c1"></canvas></div>
  <div class="chart"><b>Müşteri Bazında Satış (TL)</b><canvas id="c2"></canvas></div>
  <div class="chart"><b>Aylık Tahsilat (TL)</b><canvas id="c3"></canvas></div>
  <div class="chart"><b>Müşteri Bazında Alacak (TL)</b><canvas id="c4"></canvas></div>
  <div class="chart"><b>İl / İlçe Bazında Satış (TL)</b><canvas id="c5"></canvas></div>
</div>
<div class="grid2">
  <div><h2>En Çok Satan Kitaplar</h2><?php table(['Kitap', 'Adet', 'Tutar'], array_map(fn($r) => [$r['ad'], $r['adet'], $r['tutar']], $topBooks), ['int' => [1], 'money' => [2]]); ?></div>
  <div><h2>En Çok Satış Yapılan Okullar</h2><?php table(['Okul', 'Tutar'], array_map('array_values', $schools), ['money' => [1]]); ?></div>
  <div><h2>En Çok Satış Yapılan Kırtasiyeler</h2><?php table(['Kırtasiye', 'Tutar'], array_map('array_values', $shops), ['money' => [1]]); ?></div>
  <div><h2>En Çok Satış Yapılan Müşteriler</h2><?php table(['Müşteri', 'Tip', 'Satılan Adet', 'Satış', 'Tahsilat', 'Kalan'], array_map(fn($r) => [$r['ad'], $r['tip'], $r['adet'], $r['satis'], $r['tahsilat'], $r['kalan']], $topSalesCustomers), ['int' => [2], 'money' => [3, 4, 5], 'totals' => [2, 3, 4, 5]]); ?></div>
  <div><h2>En Yüksek Alacağı Olan Müşteriler</h2><?php table(['Müşteri', 'Tip', 'Kalan Alacak'], array_map(fn($r) => [$r['ad'], $r['tip'], $r['kalan']], $recv), ['money' => [2], 'totals' => [2]]); ?></div>
  <?php if ($crit): ?><div><h2>Kritik Stok Uyarıları</h2><?php table(['Kitap', 'Stok', 'Kritik'], array_map(fn($s) => [$s['ad'], $s['stok'], $s['kritik_stok']], $crit), ['int' => [1, 2]]); ?></div><?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const mk = (id, rows, type = 'bar', color = '#2f5c93') => new Chart(document.getElementById(id), {
  type, data: {labels: rows.map(r => r.ad), datasets: [{data: rows.map(r => +r.tutar), backgroundColor: type === 'bar' ? color : undefined}]},
  options: {plugins: {legend: {display: type !== 'bar'}}, indexAxis: type === 'bar' ? 'y' : 'x'}});
mk('c1', <?= json_encode($topBooks, JSON_UNESCAPED_UNICODE) ?>);
mk('c2', <?= json_encode($topCust, JSON_UNESCAPED_UNICODE) ?>, 'bar', '#3b8f5c');
mk('c3', <?= json_encode(array_reverse($monthPay), JSON_UNESCAPED_UNICODE) ?>, 'line');
mk('c4', <?= json_encode(array_map(fn($r) => ['ad' => $r['ad'], 'tutar' => $r['kalan']], $recv), JSON_UNESCAPED_UNICODE) ?>, 'bar', '#b3382c');
mk('c5', <?= json_encode($ilce, JSON_UNESCAPED_UNICODE) ?>, 'bar', '#d08a1f');
</script>
<?php layout_foot();
