<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
$legacy = getenv('LEGACY_PATCH_ROOT');
if (!$legacy || !is_file($legacy . '/composer.json')) {
    throw new RuntimeException('Set LEGACY_PATCH_ROOT to the isolated legacy package being tested.');
}
spl_autoload_register(static function (string $class) use ($legacy): void {
    $prefix = 'RocketWeb\\ShoppingFeeds\\';
    if (str_starts_with($class, $prefix)) {
        $file = $legacy . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);
$google = getenv('LEGACY_GOOGLE_PATCH_ROOT');
if ($google) {
    spl_autoload_register(static function (string $class) use ($google): void {
        $prefix = 'RocketWeb\\ShoppingFeedsGoogle\\';
        if (str_starts_with($class, $prefix)) {
            $file = $google . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }, true, true);
}
