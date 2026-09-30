<?php

declare(strict_types=1);

// Read-only preflight for the exact legacy files covered by the opt-in patch.
try {
    if ($argc < 2 || $argc > 3 || ($argc === 3 && $argv[2] !== '--patched')) {
        throw new RuntimeException('Usage: php dev/verify-legacy-patch.php /path/to/legacy-package [--patched]');
    }
    $root = realpath($argv[1]);
    if ($root === false || !is_dir($root)) {
        throw new RuntimeException('Legacy package directory does not exist.');
    }
    $readJson = static function (string $path): array {
        if (!is_readable($path)) {
            throw new RuntimeException('Required file is unreadable: ' . $path);
        }
        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    };
    $patchDir = dirname(__DIR__) . '/patches';
    $package = $readJson($root . '/composer.json');
    $manifests = [
        'rocketweb/module-shopping-feeds' => 'legacy-2.3.4-manifest.json',
        'rocketweb/module-shopping-feeds-google' => 'legacy-google-2.3.4-manifest.json',
    ];
    if (!isset($manifests[$package['name'] ?? ''])) {
        throw new RuntimeException('Expected the Rocket Web base or Google Shopping package.');
    }
    $manifest = $readJson($patchDir . '/' . $manifests[$package['name']]);
    if (($package['name'] ?? '') !== $manifest['package'] || ($package['version'] ?? '') !== $manifest['version']) {
        throw new RuntimeException('Expected ' . $manifest['package'] . ' version ' . $manifest['version'] . '.');
    }
    if (!hash_equals($manifest['patch_sha256'], hash_file('sha256', $patchDir . '/' . $manifest['patch']))) {
        throw new RuntimeException('Compatibility patch checksum does not match its manifest.');
    }
    $key = $argc === 3 ? 'patched_sha256' : 'original_sha256';
    foreach ($manifest['files'] as $file => $hashes) {
        if (!is_readable($root . '/' . $file) || !hash_equals($hashes[$key], hash_file('sha256', $root . '/' . $file))) {
            throw new RuntimeException('Unexpected legacy file contents: ' . $file . '. Review customizations before patching.');
        }
    }
    echo 'Verified ', $manifest['package'], ' 2.3.4 ', $argc === 3 ? 'patched' : 'original', " files and patch checksum.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
