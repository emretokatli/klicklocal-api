<?php

/**
 * https://gastrocycle.com/public/uat-check.php  (IONOS root = /klicklocal)
 * If this returns 500, rename public/.htaccess to .htaccess.bak and try ping.php first.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');
echo "step 1: PHP runs\n";

$root = dirname(__DIR__);

echo "=== Klicklocal UAT check ===\n\n";
echo 'PHP version: '.PHP_VERSION.(version_compare(PHP_VERSION, '8.2.0', '>=') ? " OK\n" : " FAIL (need 8.2+)\n");
echo 'Server: '.($_SERVER['SERVER_SOFTWARE'] ?? 'unknown')."\n\n";

$checks = [
    'vendor/autoload.php' => 'Composer vendor (run composer install)',
    '.env' => 'Environment file in project root',
    'storage' => 'storage/ directory',
    'bootstrap/cache' => 'bootstrap/cache/ directory',
];

foreach ($checks as $path => $label) {
    $full = $root.'/'.$path;
    $exists = file_exists($full);
    $writable = $exists && is_dir($full) ? is_writable($full) : null;
    echo ($exists ? '[OK]' : '[MISSING]').' '.$label.' ('.$path.')';
    if ($writable !== null) {
        echo $writable ? " writable\n" : " NOT writable\n";
    } else {
        echo "\n";
    }
}

if (is_file($root.'/.env')) {
    echo "\n.env DB_HOST: ";
    $env = file_get_contents($root.'/.env');
    echo preg_match('/^DB_HOST=(.+)$/m', $env, $m) ? trim($m[1])."\n" : "not set\n";
}

if (is_file($root.'/vendor/autoload.php')) {
    try {
        require $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        echo "\n[Laravel] Bootstrap loaded OK\n";
    } catch (Throwable $e) {
        echo "\n[Laravel] Bootstrap FAILED:\n".$e->getMessage()."\n";
    }
}

echo "\nDone. Remove uat-check.php when finished.\n";
