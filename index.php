<?php
// Backward compatibility: eski ?p= URL'lerden yenilerine yönlendir
if (!empty($_GET['p'])) {
    $page = $_GET['p'];
    unset($_GET['p']);
    $pageMap = ['stock' => 'stock_current'];
    $page = $pageMap[$page] ?? $page;
    $newUrl = $page . '.php';
    if (!empty($_GET)) {
        $newUrl .= '?' . http_build_query($_GET);
    }
    header('Location: ' . $newUrl);
    exit;
}

// Default: dashboard'a yönlendir
header('Location: dashboard.php');
exit;
