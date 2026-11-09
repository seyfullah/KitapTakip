<?php
// Cron ile Google Sheets'i besler:  */15 * * * * php /yol/cron_sync.php
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/lib.php';
try { echo gs_sync() . " sekme güncellendi\n"; } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
