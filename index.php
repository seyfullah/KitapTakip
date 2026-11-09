<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/lib.php';
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

$p = get('p', 'dashboard');

if ($p === 'logout') { session_destroy(); redirect('?p=login'); }

if ($p === 'login') {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $u = row('SELECT * FROM users WHERE kullanici = ?', [post('kullanici')]);
        if ($u && password_verify($_POST['sifre'] ?? '', $u['sifre_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            redirect('?p=dashboard');
        }
        $err = 'Kullanıcı adı veya şifre hatalı.';
    }
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Giriş</title><link rel="stylesheet" href="assets/style.css"></head><body class="login"><form method="post" class="card"><h1>Kitap Dağıtım & Cari</h1>';
    if ($err) echo '<div class="flash err">' . h($err) . '</div>';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '"><label>Kullanıcı<input name="kullanici" autofocus required></label><label>Şifre<input type="password" name="sifre" required></label><button>Giriş</button></form></body></html>';
    exit;
}

if (empty($_SESSION['uid'])) redirect('?p=login');
csrf_check();

$pages = ['dashboard', 'books', 'stock', 'customers', 'sales', 'payments', 'cari', 'overdue', 'reports', 'export'];
if (!in_array($p, $pages, true)) { http_response_code(404); $p = 'dashboard'; }
require __DIR__ . '/pages/' . $p . '.php';
