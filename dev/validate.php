<?php

declare(strict_types=1);

$magento = $argv[1] ?? '';
if (!is_file($magento . '/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php dev/validate.php /path/to/magento\n");
    exit(1);
}
require $magento . '/vendor/autoload.php';
$root = dirname(__DIR__);
$registrar = new Magento\Framework\Component\ComponentRegistrar();
if (!$registrar->getPath($registrar::MODULE, 'RocketWeb_ShoppingFeedMigration')) {
    require $root . '/registration.php';
}
$resolver = new Magento\Framework\Config\Dom\UrnResolver();
libxml_set_external_entity_loader([$resolver, 'registerEntityLoader']);
$count = 0;
$phpCount = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (str_contains($file->getPathname(), '/.git/') || str_contains($file->getPathname(), '/vendor/')) {
        continue;
    }
    if ($file->getExtension() === 'xml' && $file->getFilename() !== 'phpunit.xml.dist') {
        $doc = new DOMDocument();
        if (!$doc->load($file->getPathname(), LIBXML_NONET)) {
            throw new RuntimeException('Invalid XML: ' . $file->getPathname());
        }
        $urn = $doc->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'noNamespaceSchemaLocation');
        if ($urn !== '' && !$doc->schemaValidate($resolver->getRealPath($urn))) {
            throw new RuntimeException('Invalid schema: ' . $file->getPathname());
        }
        $count++;
    }
    if (in_array($file->getExtension(), ['php', 'phtml'], true)) {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('PHP syntax failure: ' . $file->getPathname());
        }
        $phpCount++;
    }
}
$composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
if ($composer['name'] !== 'rocketweb/module-shopping-feed-migration-rocketweb') {
    throw new RuntimeException('Unexpected package identity.');
}
echo "Validated $phpCount PHP/template files, $count XML documents, and package identity.\n";
