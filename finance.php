<?php require '_init.php';

$from = get('from');
$to = get('to');
$summary = financial_summary($from, $to);
$supplier = supplier_balance_summary();
layout_head('Kâr / Zarar', 'finance');
echo '<form class="f no-print"><input type="hidden" name="p" value="finance"><label>Başlangıç<input type="date" name="from" value="' . h($from) . '"></label><label>Bitiş<input type="date" name="to" value="' . h($to) . '"></label><button>Hesapla</button></form>';
$period = ($from ? dtr($from) : 'başlangıç') . ' – ' . ($to ? dtr($to) : 'bugün');
echo '<p class="muted">Dönem: ' . h($period) . '</p>';
$data = [
    ['Satışlar', $summary['uy_sales']],
    ['Müşteri iadeleri', -$summary['returns']],
    ['Net satış', $summary['net_sales']],
    ['Satılan kitapların maliyeti', -$summary['cost']],
    ['Brüt kâr / zarar', $summary['gross_profit']],
    ['Giderler', -$summary['uy_expenses']],
    ['Bağışlar', $summary['uy_donations']],
    ['Net kâr / zarar', $summary['profit']],
];
table(['Kalem', 'Tutar'], $data, ['money' => [1]]);
echo '<div class="cards"><div class="kpi"><small>Kalan Borç</small><div>' . money($summary['debt']) . '</div><a href="uy_loans.php">Borçları görüntüle</a></div><div class="kpi"><small>Bağış</small><div>' . money($summary['uy_donations']) . '</div><a href="uy_donations.php">Bağış kaydet</a></div><div class="kpi"><small>Gider</small><div>' . money($summary['uy_expenses']) . '</div><a href="uy_expenses.php">Gider kaydet</a></div></div>';
echo '<div class="cards"><div class="kpi"><small>Tedarikçiye Kalan Borç</small><div>' . money($supplier['debt']) . '</div><a href="uy_supplier_payments.php">Ödemeleri görüntüle</a></div><div class="kpi"><small>Tedarikçiden Alacak</small><div>' . money($supplier['credit']) . '</div><a href="uy_supplier_payments.php">Alışları görüntüle</a></div></div>';
echo '<p class="muted">Kitap maliyeti satış anındaki kitap kartı alış fiyatından alınır; eski satışların maliyeti geçiş sırasında güncel alış fiyatıyla yaklaşık doldurulur. Borç ve anapara ödemeleri kâr/zarardan ayrı izlenir; tedarikçi iadeleri stoktan düşülür.</p>';
layout_foot();
