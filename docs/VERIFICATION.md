# Verification

The suite loads installed Magento framework dependencies without bootstrapping a store or reading its database credentials. The integration suite explicitly requires `MIGRATION_TEST_DB=feed_migration_test` and connects only to the disposable loopback database `127.0.0.1:13389`. It drops only tables beginning with `rw_migration_test_` in that database.

Use a disposable MariaDB 11.4 container with synthetic fixtures:

```sh
docker run --detach --rm --name shopping-feed-migration-test \
  --publish 127.0.0.1:13389:3306 \
  --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
  --env MARIADB_DATABASE=feed_migration_test \
  --tmpfs /var/lib/mysql mariadb:11.4

MAGENTO_ROOT=/path/to/magento \
SHOPPING_FEED_ROOT=/path/to/module-shopping-feed \
LEGACY_FEED_ROOT=/path/to/legacy-sibling-checkouts \
MIGRATION_TEST_DB=feed_migration_test \
php /path/to/phpunit-9.phar -c phpunit.xml.dist

docker stop shopping-feed-migration-test
```

The optional original-XML compatibility test expects `m2-shopping-feed`, `m2-shopping-feed-google`, `m2-shopping-feed-google-inventory`, and `m2-shopping-feed-google-promotions` beneath `LEGACY_FEED_ROOT`. Original legacy source is not redistributed in this repository.

Checks cover configuration decoding, inherited defaults, supported paths/directives, filename isolation, unreadable credentials, original-data preservation, inactive imports, encrypted receipts, stale/duplicate requests, rollback conflicts, activation prerequisites, and transaction failures during import and activation.

The Admin regression checks also load the block against Magento's real parent class and merge the importer ACL with the destination ACL. They catch inheritance fatals and duplicate ACL resources that otherwise prevent Admin login.

The importer suite now includes inherited and saved null-column-parameter checks. Null parameters become empty strings in destination product/replacement maps; missing parameters, zero, false, configured values, and original source records are preserved. The preview discloses normalization.

The separate [legacy compatibility suite](LEGACY-COMPATIBILITY.md#validation-and-reversal) requires isolated base and Google add-on packages. It enables strict deprecation exceptions and tests actual legacy classes. It does not run as part of the importer suite or require patching installed legacy modules merely to test the importer.

Run syntax, metadata, and schema checks separately:

```sh
php dev/validate.php /path/to/magento
composer validate --strict --no-check-publish
```

The [2026-09-30 acceptance record](ACCEPTANCE-2026-09-30.md) records a disposable full install, normal Composer install/remove/reinstall, DI compilation, authenticated Admin checks, complex-product and scope comparisons, isolated FTP/SFTP uploads, receipt retention, and database/file restore. Its continuation validates the opt-in legacy patches and native destination generation after parameter normalization. Material output differences remain documented. Before a production release, repeat acceptance with the supported destination release and the store's real configuration, catalog types, scopes, extensions, and recipient. Synthetic acceptance does not establish those store-specific results.
