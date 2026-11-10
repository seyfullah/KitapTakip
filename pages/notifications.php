<?php
$configured = reminder_channels_configured();
layout_head('Haftalık Alacak Bildirimleri', 'notifications');
?>
<div class="cards">
  <div class="kpi"><small>Çalışma zamanı</small><div>Pazartesi · 08.00</div><small>Europe/Istanbul</small></div>
  <div class="kpi <?= $configured['email'] ? '' : 'bad' ?>"><small>E-posta</small><div><?= $configured['email'] ? 'Hazır' : 'Yapılandırılmadı' ?></div></div>
  <div class="kpi <?= $configured['sms'] ? '' : 'bad' ?>"><small>SMS (Twilio)</small><div><?= $configured['sms'] ? 'Hazır' : 'Yapılandırılmadı' ?></div></div>
</div>
<div class="card">
  <p>Sistemin bakiyesi pozitif olan müşterilere haftada bir kez cari bakiye hatırlatması gönderilir. Başarılı gönderimler aynı hafta yeniden gönderilmez; başarısız veya atlanan kanallar sonraki çalıştırmada tekrar denenebilir.</p>
  <p>Gönderim için e-posta sunucusu (PHP <code>mail()</code>) ve Twilio SMS ayarları gerekir. Zamanlama için sunucunun crontab'ına şu satırı ekleyin:</p>
  <pre><code>CRON_TZ=Europe/Istanbul
0 8 * * 1 /usr/bin/php <?= h(dirname(__DIR__) . '/cron_notify.php') ?></code></pre>
</div>
<h2>Son gönderim kayıtları</h2>
<?php
$data = [];
foreach (rows('SELECT l.hafta_baslangici, c.ad musteri, l.kanal, l.durum, l.hedef, l.hata, l.guncellendi
    FROM notification_log l JOIN customers c ON c.id = l.customer_id
    ORDER BY l.guncellendi DESC, l.id DESC LIMIT 200') as $log) {
    $data[] = [dtr($log['hafta_baslangici']), $log['musteri'], $log['kanal'], $log['durum'], $log['hedef'], $log['hata'], dtr(substr($log['guncellendi'], 0, 10)) . ' ' . substr($log['guncellendi'], 11, 5)];
}
table(['Hafta', 'Müşteri', 'Kanal', 'Durum', 'Hedef', 'Açıklama', 'Güncellendi'], $data);
layout_foot();
