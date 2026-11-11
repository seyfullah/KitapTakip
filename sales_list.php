<?php require '_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'view') {
        $id = (int) post('id');
        $s = row('SELECT * FROM uy_sales WHERE id = ?', [$id]);
        if (!$s) { flash('Satış bulunamadı.'); redirect('sales_list.php'); }
        layout_head('Satış Detayı', 'sales_list');
        echo '<div class="card">';
        echo '<div class="f" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem"><label>No<strong>#' . $s['id'] . '</strong></label>';
        echo '<label>Müşteri<strong>' . h(val('SELECT ad FROM uy_customers WHERE id = ?', [$s['customer_id']])) . '</strong></label>';
        echo '<label>Tarih<strong>' . dtr($s['tarih']) . '</strong></label>';
        echo '<label>Vade Tarihi<strong>' . dtr($s['vade_tarihi']) . '</strong></label>';
        echo '<label>Durumu<strong>' . h($s['durum']) . '</strong></label>';
        echo '<label>Toplam<strong>' . fmt($s['toplam']) . ' TL</strong></label>';
        if ($s['aciklama']) echo '<label style="grid-column:1/-1">Açıklama<strong>' . h($s['aciklama']) . '</strong></label>';
        echo '</div>';
        
        echo '<div class="table-wrap"><table><thead><tr><th>Kitap</th><th>Adet</th><th>Birim Fiyat</th><th>Tutar</th></tr></thead><tbody>';
        foreach (rows('SELECT b.ad, si.adet, si.birim_fiyat, si.toplam FROM uy_sale_items si JOIN uy_books b ON b.id = si.book_id WHERE si.sale_id = ? ORDER BY si.id', [$id]) as $item) {
            echo '<tr><td>' . h($item['ad']) . '</td><td class="r">' . $item['adet'] . '</td><td class="r">' . fmt($item['birim_fiyat']) . '</td><td class="r">' . fmt($item['toplam']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p><a href="sales_list.php" class="btn">Geri</a></p>';
        echo '</div>';
        layout_foot();
        exit;
    }
}

layout_head('Satış Listesi', 'sales_list');
$default_from = date('Y-m-d', strtotime('-7 days'));
$default_to = date('Y-m-d');
$f = ['from' => get('from', $default_from), 'to' => get('to', $default_to), 'cid' => (int) get('customer_id'), 'durum' => get('durum', '')];
echo '<form class="f no-print"><input type="hidden" name="p" value="sales_list"><label>Başlangıç<input type="date" name="from" value="' . h($f['from']) . '"></label><label>Bitiş<input type="date" name="to" value="' . h($f['to']) . '"></label><label>Müşteri<select name="customer_id"><option value="">— Tümü —</option>';
foreach (rows('SELECT id, ad FROM uy_customers ORDER BY ad') as $c) {
    echo '<option value="' . $c['id'] . '"' . ($f['cid'] == $c['id'] ? ' selected' : '') . '>' . h($c['ad']) . '</option>';
}
echo '</select></label><label>Durum' . select('durum', ['' => '— Tümü —', 'Yeni' => 'Yeni', 'Teslimat Bekliyor' => 'Teslimat Bekliyor', 'Teslim Edildi' => 'Teslim Edildi', 'İade Edildi' => 'İade Edildi', 'İptal Edildi' => 'İptal Edildi'], $f['durum']) . '</label><button>Filtrele</button></form>';

$w = ' WHERE 1=1'; $p = [];
if ($f['from']) { $w .= ' AND s.tarih >= :f'; $p['f'] = $f['from']; }
if ($f['to']) { $w .= ' AND s.tarih <= :t'; $p['t'] = $f['to']; }
if ($f['cid']) { $w .= ' AND s.customer_id = :c'; $p['c'] = $f['cid']; }
if ($f['durum']) { $w .= ' AND s.durum = :d'; $p['d'] = $f['durum']; }

$data = [];
foreach (rows("SELECT s.id, s.tarih, s.vade_tarihi, s.durum, c.ad musteri, c.tip, SUM(si.adet) adet, s.toplam
    FROM uy_sales s 
    LEFT JOIN uy_sale_items si ON si.sale_id = s.id
    LEFT JOIN uy_customers c ON c.id = s.customer_id
    $w 
    GROUP BY s.id 
    ORDER BY s.tarih DESC, s.id DESC 
    LIMIT 500", $p) as $r) {
    $data[] = [
        '#' . $r['id'], 
        dtr($r['tarih']), 
        dtr($r['vade_tarihi']), 
        $r['musteri'] . ' (' . $r['tip'] . ')', 
        ($r['adet'] ?? 0), 
        h($r['durum']),
        (float) $r['toplam'],
        '<form method="post" class="inline"><input type="hidden" name="_csrf" value="' . csrf() . '"><input type="hidden" name="act" value="view"><input type="hidden" name="id" value="' . $r['id'] . '"><button>İncele</button></form>'
    ];
}
table(['No', 'Tarih', 'Vade', 'Müşteri', 'Adet', 'Durum', 'Toplam', ''], $data, ['int' => [4], 'money' => [6], 'raw' => [7 => true]]);
layout_foot();
