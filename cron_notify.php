<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/lib.php';
date_default_timezone_set('Europe/Istanbul');

try {
    if (cfg('app_storage') === 'sqlite') {
        throw new RuntimeException('Yerel dosya modunda gerçek e-posta/SMS gönderimi devre dışıdır.');
    }
    $result = send_weekly_reminders();
    echo sprintf(
        "%s: %d müşteri, %d gönderildi, %d atlandı, %d başarısız\n",
        date('Y-m-d H:i:s'),
        $result['müşteri'],
        $result['gönderildi'],
        $result['atlandı'],
        $result['başarısız']
    );
    if ($result['başarısız'] > 0) exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
