<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

use MageOS\ShoppingFeed\Model\FeedTypes\Config\SchemaLocator;

class LegacySchemaLocator extends SchemaLocator
{
    public function __construct()
    {
    }

    public function getSchema()
    {
        // Legacy 2.3.4 inventory XML omits encoding although its own XSD requires it.
        // Validate the envelope here; Planner validates selected settings against the destination.
        return dirname(__DIR__) . '/etc/legacy_feed.xsd';
    }
}
