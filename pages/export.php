<?php
if (get('only') && isset(report_defs()[get('only')])) { $k = get('only'); xlsx_download("rapor_$k.xlsx", [report_defs()[$k] => report($k)]); }
if (get('xlsx')) xlsx_download('kitap_cari_' . date('Ymd_His') . '.xlsx', export_sets());
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('act') === 'gsync') {
    try { flash('Google Sheets güncellendi (' . gs_sync() . ' sekme).'); } catch (Throwable $e) { flash($e->getMessage()); }
    redirect('?p=export');
}
layout_head('Excel ve Google Sheets', 'export'); ?>
<div class="card"><h2>Excel</h2>
  <p>Kitaplar, müşteriler, stok girişleri, satışlar, tahsilatlar, güncel stok, cari özet, müşteri detay ve vadesi geçmiş alacaklar tek bir .xlsx dosyasında ayrı sekmeler olarak indirilir.</p>
  <a class="btn" href="?p=export&xlsx=1">⬇ Excel (.xlsx) indir</a></div>
<div class="card"><h2>Google Sheets</h2>
<?php if (gs_configured()): ?>
  <p>Tablo: <a target="_blank" rel="noopener" href="https://docs.google.com/spreadsheets/d/<?= h(cfg('gsheets_id')) ?>">aç</a> · Otomatik besleme: <?= cfg('gsheets_auto') ? '<b>açık</b>' : 'kapalı (GSHEETS_AUTO=1 ile açılır)' ?></p>
  <form method="post"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="gsync"><button>🔄 Şimdi Google Sheets'e aktar</button></form>
<?php else: ?>
  <p class="crit">Google Sheets ayarlı değil.</p>
  <ol><li>Google Cloud'da Sheets API'yi etkinleştirip bir <i>servis hesabı</i> oluşturun, JSON anahtarını <code>service-account.json</code> olarak proje klasörüne koyun.</li>
  <li>Boş bir Google Sheet oluşturun ve servis hesabının e-postasıyla "Düzenleyici" olarak paylaşın.</li>
  <li>Tablo ID'sini (URL'deki <code>/d/<b>ID</b>/edit</code>) <code>GSHEETS_ID</code> ortam değişkenine ya da <code>config.php</code>'ye yazın.</li></ol>
<?php endif; ?>
  <p class="muted">Her aktarımda ilgili sekmeler temizlenip güncel verilerle yeniden yazılır. Zamanlama için: <code>*/15 * * * * php <?= h(__DIR__) ?>/../cron_sync.php</code></p>
</div>
<?php layout_foot();
