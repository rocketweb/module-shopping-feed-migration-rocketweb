# Rocket Web Shopping Feed Migration

An optional Magento module that copies Rocket Web Shopping Feeds configuration into `MageOS_ShoppingFeed`. Install it only on stores that are migrating. The main shopping feed module does not depend on this package.

This is an unreleased development implementation. Disposable Mage-OS 3.5 checks cover Composer installation/removal, DI compilation, authenticated Admin import/activation/rollback, ACL and CSRF rejection, complex products, store and website scopes, loopback FTP/SFTP uploads, receipt retention, and backup restoration. Opt-in legacy compatibility patches passed local acceptance. Tests also found material differences in stock values, row counts, URLs and Google columns. A representative store migration and recipient acceptance remain release requirements. See the [acceptance record](docs/ACCEPTANCE-2026-09-30.md). No Packagist availability is implied.

## What it does

- Imports one feed at a time with its store, type, saved settings, and inherited defaults.
- Preserves column maps, filters, category mappings, Google inventory settings, and promotion configuration when supported by the destination.
- Converts null column parameters to empty strings for destination compatibility and lists this conversion in the preview. Source records remain unchanged.
- Creates a disabled destination feed with microdata off. Schedules and encrypted upload credentials are held in an encrypted database receipt until activation.
- Keeps the original feed and database records intact.
- Requires a current preview token and an operator-supplied backup reference before import. The reference records the operator's completed backup; the module does not create or verify that external backup.
- Rejects repeated imports, unsupported settings/directives, unreadable upload credentials, and stale previews.
- Uses a transaction for each import, activation, and rollback, plus a migration lock and row locks.
- Rolls back an unchanged import. It refuses automated rollback after edits or activation.

The package identity is `rocketweb/module-shopping-feed-migration-rocketweb`; the Magento module is `RocketWeb_ShoppingFeedMigration`.

## Compatibility and boundaries

The initial source baseline is Rocket Web Shopping Feeds 2.3.4, including its Google Shopping, Google Local Inventory, and Google Promotions add-ons. The destination dependency is `mage-os/module-shopping-feed:^1.1`. Other legacy versions and customized installations require staging validation. Both modules must be on the same Magento installation, with the same encryption key. Cross-store database imports are not supported.

Unmodified Rocket Web 2.3.4 has runtime failures on the tested Mage-OS 3.5 / Symfony 7.4 stack: missing command return types, image-mapper errors, and unhandled null parameters. [Opt-in compatibility patches](docs/LEGACY-COMPATIBILITY.md) address the reproduced failures in the base and Google Shopping packages. Their regression tests, Composer application and native CLI generation passed in the disposable environment. Installing this migration module does not apply them automatically. Validate the exact legacy packages and existing customizations on staging before installation.

Legacy module code and feed definitions must remain installed and enabled during import. Remaining legacy tables alone are insufficient to recover inherited defaults. The reader merges `shoppingfeeds.xml` from enabled modules, then checks selected configuration paths and directives against the destination. The original inventory XML omits an encoding element required by its own XSD, so this module uses a compatibility envelope schema and validates the selected settings separately.

Shared `shoppingfeeds/*` database settings are preserved in the encrypted receipt and listed for manual review. They are not applied globally: cron, logging, microdata, and remarketing settings can affect other feeds or storefront behavior. Deployment-file and environment overrides, custom PHP plugins/observers, external scheduler entries, and recipient accounts need separate review. Automatic migration of those surfaces is not implemented.

Generated files, logs, queue entries, processing checkpoints, and shipping cache entries are not copied. They are runtime data, not feed configuration.

## Installation

Test on staging first. Back up the database, generated feeds, code, configuration, and encryption key using the store's normal secure backup procedure. Keep secrets out of command output and tickets.

**Keep the legacy Magento module enabled during `setup:upgrade`.** Disabling a module that owns declarative schema can cause its tables to be dropped. Stop scheduled generation through the legacy feed settings or scheduler while making the cutover; disabling a feed is different from disabling its module. See [Adobe's declarative schema documentation](https://developer.adobe.com/commerce/php/development/components/declarative-schema/configuration).

Before this package has a published release, install its checkout as a local Composer path repository in a disposable or staging project. Use an explicit path and a development constraint:

```sh
composer config repositories.rocketweb-feed-migration path /absolute/path/module-shopping-feed-migration-rocketweb
composer require rocketweb/module-shopping-feed-migration-rocketweb:@dev
bin/magento module:enable RocketWeb_ShoppingFeedMigration
bin/magento setup:upgrade
```

Follow the store's normal build and deployment process, including DI compilation and static content deployment where required. Composer installation does not run a migration. This module contains no automatic data patches and no Composer installer executed from Admin.

Grant the dedicated **Migrate Rocket Web feeds** ACL permission to the operator. Open **Mage-OS Shopping Feed > Import Rocket Web Feeds** for the review/import/activation workflow.

## CLI workflow

All operations except those explicitly using `--apply` are previews. Feed IDs below are legacy feed IDs. The generated destination ID is recorded in the receipt and may differ.

```sh
bin/magento shopping-feed:migrate:rocketweb list
bin/magento shopping-feed:migrate:rocketweb preview --feed=7
bin/magento shopping-feed:migrate:rocketweb import --feed=7 --apply \
  --token='<token-from-preview>' --backup-reference='staging-backup-reference'
```

Generate a test preview using the destination module's test mode. Check column order, product IDs/counts, prices, stock, category mappings, variants, promotion output, and encoding. During this stage the imported feed has no active schedules or upload rows.

Copied configuration does not guarantee identical output. Expanded tests found configurable variants changing from `out_of_stock` to `in_stock`, and Local Inventory omitting grouped/bundle parent rows that had no source items. The destination preserved a nonstandard URL port, added Google variant columns, used `promotion_id` instead of `promotions_id`, and changed `identifier_exists` output. Promotions output used lowercase enum values and added `shopping_ads` and `free_listings` destination columns. Compare row counts, stock values, URLs, headers, actual files, and recipient requirements before activation.

Before activation, disable the original **feed**, drain its queued/pending work, and stop any external commands that can restart it. Review custom code, shared settings, and the recipient's fetch URL. The migration module does not control external cron or another administrator who re-enables the original feed.

```sh
bin/magento shopping-feed:migrate:rocketweb activate --feed=7
bin/magento shopping-feed:migrate:rocketweb activate --feed=7 --apply \
  --token='<token-from-activation-review>'
```

Activation restores the held schedules/upload destinations and enables the replacement feed. Microdata remains off for explicit per-store review. Source configuration changes since import block activation. Destination changes invalidate the activation review token; review again after edits.

## Feed URLs and filenames

The destination confines output to `pub/media/mageos-shopping-feed`. Imports use a separate `rocketweb-<legacy-id>` subdirectory. Old filename templates are resolved using the legacy ID, preserving recipient filenames even when the destination feed receives a new ID.

Existing public fetch URLs do not switch automatically. Either update the recipient URL after validating output or configure a narrowly scoped web-server mapping from the exact old feed URL to the verified new artifact. Include separate promotion files where applicable. Never redirect the entire media directory. Test the exact public URL before retiring the old generation schedule.

## Rollback and removal

Before activation, an unchanged import can be removed:

```sh
bin/magento shopping-feed:migrate:rocketweb rollback --feed=7
bin/magento shopping-feed:migrate:rocketweb rollback --feed=7 --apply \
  --token='<token-from-rollback-review>'
```

Rollback deletes only the unchanged destination feed and its dependent configuration. It retains the migration receipt and leaves the source untouched. A retained receipt prevents accidental re-import. There is no force-delete or automatic reset command.

For changed or activated feeds, use the recorded backup and a reviewed cutover reversal. Do not delete edits to force an automatic rollback. Generated preview files are not removed automatically.

The receipt table is `rw_feed_migration_receipt` (with the installation's table prefix). It has no foreign key to either feed table and no deletion whitelist. On the tested Mage-OS 3.5 installation, six receipts survived the copied-code lifecycle, and seven remained byte-identical through normal Composer removal and reinstallation, including `setup:upgrade`. Repeat that lifecycle check on the store's exact platform version. Preserve the table and matching encryption key in your backup. Removing the package removes access to held schedules/credentials; finish activation or rollback first. Review and remove the package through the usual deployment process after the rollback period. Encryption-key rotation invalidates existing preview/destination fingerprints; treat rotation during a migration as a manual review event.

## Development checks

See [verification](docs/VERIFICATION.md) for commands and remaining acceptance work, and [core discovery integration](docs/CORE-INTEGRATION.md) for the small optional notice that belongs in the main module.
