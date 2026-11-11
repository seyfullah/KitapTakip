<?php
// Tüm sayfa dosyalarının başında require et
// Başlangıç ve authentication
declare(strict_types=1);
session_start();
require __DIR__ . '/lib.php';
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

// Logout işlemi
if (basename($_SERVER['SCRIPT_FILENAME'], '.php') === 'logout') {
    session_destroy();
    redirect('login.php');
}

// Diğer sayfalar için: login gerekli
if (empty($_SESSION['uid'])) redirect('login.php');
csrf_check();
