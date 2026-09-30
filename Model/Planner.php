<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

use Magento\Framework\Encryption\EncryptorInterface;

class Planner
{
    public function __construct(private Definitions $definitions, private ConfigCodec $codec, private EncryptorInterface $encryptor)
    {
    }

    /** Internal plan includes credentials; only report() may be rendered or logged. */
    public function build(array $source): array
    {
        [$old, $new] = $this->definitions->get((string)$source['feed']['type']);
        if (empty($old['default_feed_config']) || empty($new['default_feed_config'])) {
            throw new \DomainException('Legacy and destination feed definitions must both be installed and enabled.');
        }
        $config = $this->flatten($old['default_feed_config']);
        $allowed = array_fill_keys(array_merge(array_keys($this->flatten($new['default_feed_config'])), SupportedPaths::EDITOR), true);
        foreach ($source['config'] as $row) {
            $config[$row['path']] = $this->codec->decode((string)$row['value']);
        }
        foreach ($config as $path => $value) {
            if (!isset($allowed[$path])) {
                throw new \DomainException('Unsupported configuration path: ' . $path);
            }
        }
        if (!is_array($config['columns_product_columns'] ?? null)) {
            throw new \DomainException('The legacy column map must be an array. Repair it before importing.');
        }
        foreach ($config['columns_product_columns'] as $column) {
            if (!is_array($column) || !is_string($column['attribute'] ?? null)) {
                throw new \DomainException('The legacy column map contains an invalid column.');
            }
            $attribute = $column['attribute'] ?? '';
            if (str_starts_with($attribute, 'directive_') && !isset($new['directives'][$attribute])) {
                throw new \DomainException('A configured column uses a directive unavailable in the destination.');
            }
        }
        foreach ($source['uploads'] as $upload) {
            try {
                $plain = $this->encryptor->decrypt((string)$upload['password']);
                if ((string)$upload['password'] !== '' && $plain === '') {
                    throw new \RuntimeException();
                }
                unset($plain);
            } catch (\Throwable $e) {
                throw new \DomainException('An upload password cannot be decrypted on this installation. Re-enter it in the legacy module.');
            }
        }
        $changes = [];
        $changes[] = ['setting' => 'status', 'action' => 'Import disabled; hold schedules, uploads and microdata until reviewed.'];
        $changes[] = ['setting' => 'general_feed_dir', 'action' => 'Use an isolated directory under pub/media/mageos-shopping-feed. Existing fetch URLs require a separately reviewed web-server mapping or destination update.'];
        $config['general_feed_dir'] = 'pub/media/mageos-shopping-feed/rocketweb-' . (int)$source['feed']['id'];
        foreach (['file_feed' => 'txt|csv|tsv|xml', 'file_promotion' => 'txt|csv|tsv|xml', 'file_log' => 'log'] as $path => $extensions) {
            if ($path !== 'file_promotion' || !empty($config[$path])) {
                $value = $config[$path] ?? '';
                $resolved = is_string($value) ? str_replace('%s', (string)$source['feed']['id'], $value) : '';
                if (
                    !is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(' . $extensions . ')$/D', $resolved)
                    || substr_count($value, '%') !== substr_count($value, '%s')
                    || substr_count($value, '%s') > 1 || str_contains($value, '..')
                ) {
                    throw new \DomainException('Unsafe or unsupported filename template: ' . $path);
                }
                // Materialize the old ID so remote filenames do not unexpectedly change.
                $config[$path] = $resolved;
            }
        }
        $encoded = [];
        foreach ($config as $path => $value) {
            $encoded[$path] = $this->codec->encode($value);
        }
        ksort($encoded);
        return ['source' => $source, 'config' => $encoded, 'changes' => $changes,
            'definition_hash' => hash('sha256', json_encode([$old, $new], JSON_THROW_ON_ERROR))];
    }

    public function report(array $plan): array
    {
        $source = $plan['source'];
        return ['source_id' => (int)$source['feed']['id'], 'name' => $source['feed']['name'],
            'type' => $source['feed']['type'], 'store_id' => (int)$source['feed']['store_id'],
            'configuration_paths' => array_keys($plan['config']), 'schedule_count' => count($source['schedules']),
            'upload_count' => count($source['uploads']), 'system_settings' => array_map(static fn(array $row): array =>
                ['scope' => $row['scope'], 'scope_id' => $row['scope_id'], 'path' => $row['path']], $source['system']),
            'changes' => $plan['changes'], 'notes' => [
                'Scoped system settings are preserved in the encrypted receipt for manual review; shared configuration is not overwritten.',
                'Destination generation can change row counts, stock status, column order, product values and promotion fields. Compare the actual files before activation.',
                'Custom PHP plugins and observers require a separate compatibility review.',
                'Import copies configuration only. It does not generate files, upload, alter old feeds, or switch fetch URLs.'
            ]];
    }

    private function flatten(array $groups): array
    {
        $flat = [];
        foreach ($groups as $group => $settings) {
            foreach ($settings as $key => $value) {
                $flat[$group . '_' . $key] = $value;
            }
        }
        return $flat;
    }
}
