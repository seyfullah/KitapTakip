<?php
// Ortam değişkeniyle ya da doğrudan burada ayarlayın.
return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'kitapcari',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    // Google Sheets: servis hesabı JSON anahtarı ve tablo ID'si (tabloyu servis hesabı e-postasıyla "Düzenleyici" olarak paylaşın)
    'gsheets_key_file' => getenv('GSHEETS_KEY_FILE') ?: __DIR__ . '/service-account.json',
    'gsheets_id' => getenv('GSHEETS_ID') ?: '',
    // true ise her kayıt işleminden sonra Google Sheets otomatik güncellenir
    'gsheets_auto' => (bool) getenv('GSHEETS_AUTO'),
];
