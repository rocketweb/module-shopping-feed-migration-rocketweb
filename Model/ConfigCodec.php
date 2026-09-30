<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

class ConfigCodec
{
    private const PREFIX = '__mageos_shopping_feed_string__:';

    public function decode(string $value): mixed
    {
        // Never instantiate objects from legacy PHP serialization.
        if (preg_match('/^(?:[aOsibdCR]:|N;)/', $value)) {
            set_error_handler(static function (): never {
                throw new \DomainException('Invalid serialized setting.');
            });
            try {
                $decoded = unserialize($value, ['allowed_classes' => false, 'max_depth' => 64]);
                $this->assertPlain($decoded);
                json_encode($decoded, JSON_THROW_ON_ERROR); // Reject cycles and invalid strings.
                return $decoded;
            } catch (\Throwable $e) {
                throw new \DomainException('Unsupported or invalid serialized setting.');
            } finally {
                restore_error_handler();
            }
        }
        if ($value !== '' && in_array($value[0], ['[', '{'], true)) {
            try {
                $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\JsonException $e) {
                throw new \DomainException('Invalid JSON setting. Repair it in the legacy feed before importing.');
            }
        }
        return $value;
    }

    public function encode(mixed $value): string
    {
        $this->assertPlain($value);
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        if (
            is_string($value) && ($value !== '' && in_array($value[0], ['[', '{'], true)
            || str_starts_with($value, self::PREFIX))
        ) {
            return self::PREFIX . json_encode($value, JSON_THROW_ON_ERROR);
        }
        return (string)$value;
    }

    private function assertPlain(mixed $value, int $depth = 0): void
    {
        if ($depth > 64 || is_object($value) || is_resource($value)) {
            throw new \DomainException('Only scalar and array settings can be migrated.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertPlain($item, $depth + 1);
            }
        }
    }
}
