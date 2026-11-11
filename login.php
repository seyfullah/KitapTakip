<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/lib.php';
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

// Login işlemi
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = row('SELECT * FROM uy_users WHERE kullanici = ?', [post('kullanici')]);
    if ($u && password_verify($_POST['sifre'] ?? '', $u['sifre_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        redirect('dashboard.php');
    }
    $err = 'Kullanıcı adı veya şifre hatalı.';
}

// Zaten oturum açmışsa yönlendir
if (!empty($_SESSION['uid'])) {
    redirect('dashboard.php');
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Giriş</title><link rel="stylesheet" href="assets/style.css"></head><body class="login"><form method="post" class="card"><h1>Kitap Dağıtım & Cari</h1><?php if ($err) echo '<div class="flash err">' . h($err) . '</div>'; ?><input type="hidden" name="_csrf" value="<?php echo csrf(); ?>"><label>Kullanıcı<input name="kullanici" autofocus required></label><label>Şifre<input type="password" name="sifre" required></label><button>Giriş</button></form></body></html>
