<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

use MageOS\ShoppingFeed\Model\FeedTypes\Config;
use MageOS\ShoppingFeed\Model\FeedTypes\Config\Reader;

class Definitions
{
    public function __construct(private Reader $legacyReader, private Config $targetConfig)
    {
    }

    public function get(string $type): array
    {
        $legacy = $this->legacyReader->read();
        return [$legacy['feed'][$type] ?? [], $this->targetConfig->getFeed($type)];
    }
}
