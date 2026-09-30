<?php

declare(strict_types=1);

$root = getenv('MAGENTO_ROOT');
if (!$root || !is_file($root . '/vendor/autoload.php')) {
    throw new RuntimeException('Set MAGENTO_ROOT to a Mage-OS/Magento checkout with installed dependencies.');
}
require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'RocketWeb\\ShoppingFeedMigration\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
    $prefix = 'MageOS\\ShoppingFeed\\';
    if (str_starts_with($class, $prefix) && getenv('SHOPPING_FEED_ROOT')) {
        $file = getenv('SHOPPING_FEED_ROOT') . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);
