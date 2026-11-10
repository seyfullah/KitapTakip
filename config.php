<?php
// Ortam değişkeniyle ya da doğrudan burada ayarlayın.
return [
    // sqlite modu, MySQL sunucusu olmadan verileri tek bir yerel dosyada saklar.
    'app_storage' => getenv('APP_STORAGE') ?: 'mysql',
    'app_data_file' => getenv('APP_DATA_FILE') ?: dirname(__DIR__) . '/kitaptakip-data/kitaptakip.sqlite',
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'kitapcari',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    // Google Sheets: servis hesabı JSON anahtarı ve tablo ID'si (tabloyu servis hesabı e-postasıyla "Düzenleyici" olarak paylaşın)
    'gsheets_key_file' => getenv('GSHEETS_KEY_FILE') ?: __DIR__ . '/service-account.json',
    'gsheets_id' => getenv('GSHEETS_ID') ?: '',
    // true ise her kayıt işleminden sonra Google Sheets otomatik güncellenir
    'gsheets_auto' => (bool) getenv('GSHEETS_AUTO'),
    // Haftalık alacak hatırlatmaları; e-posta yerel MTA (mail()) üzerinden gönderilir.
    'mail_from_email' => getenv('MAIL_FROM_EMAIL') ?: '',
    'mail_from_name' => getenv('MAIL_FROM_NAME') ?: 'Kitap Dağıtım',
    'sms_twilio_sid' => getenv('SMS_TWILIO_ACCOUNT_SID') ?: '',
    'sms_twilio_token' => getenv('SMS_TWILIO_AUTH_TOKEN') ?: '',
    'sms_twilio_from' => getenv('SMS_TWILIO_FROM') ?: '',
    'reminder_contact' => getenv('REMINDER_CONTACT') ?: '',
];
