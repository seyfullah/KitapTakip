<?php
layout_head('Güncel Stok', 'stock_current');
$crit = array_filter(stock_report(), fn($s) => $s['stok'] <= $s['kritik_stok']);
if ($crit) echo '<div class="flash err"><b>⚠ Kritik stok uyarısı:</b> ' . h(implode(', ', array_map(fn($s) => $s['ad'] . ' (' . $s['stok'] . ')', $crit))) . '</div>';
$data = [];
foreach (stock_report() as $s) $data[] = ['<span class="' . ($s['stok'] <= $s['kritik_stok'] ? 'crit' : '') . '">' . h($s['kod']) . '</span>', $s['ad'], $s['giris'], $s['satilan'], $s['diger_cikis'], $s['stok'], $s['koli_adedi'] > 0 ? round($s['stok'] / $s['koli_adedi'], 1) : '', $s['kritik_stok'], $s['stok'] <= $s['kritik_stok'] ? '<span class="crit">KRİTİK</span>' : 'Yeterli'];
table(['Kod', 'Kitap', 'Giriş', 'Satılan', 'Diğer Çıkış', 'Stok', 'Koli', 'Kritik', 'Durum'], $data, ['int' => [2, 3, 4, 5], 'raw' => [0 => true, 8 => true], 'totals' => [2, 3, 4, 5]]);
layout_foot();
