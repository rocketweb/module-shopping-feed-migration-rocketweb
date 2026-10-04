# Verification

The suite loads installed Magento framework dependencies without bootstrapping a store or reading its database credentials. The integration suite explicitly requires `MIGRATION_TEST_DB=feed_migration_test` and connects only to a disposable loopback database, defaulting to `127.0.0.1:13389`. Set `MIGRATION_TEST_PORT` to another unprivileged local port when the default belongs to a different test stack. The database name and loopback host remain fixed. It drops only tables beginning with `rw_migration_test_` in that database.

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

The importer suite includes inherited and saved null-parameter checks. Null URL parameters become empty strings for older destination URL mappers; other null parameters retain their mapper defaults, including shipping weight units. Missing parameters, zero, false, configured values, and original source records are preserved. The preview discloses URL normalization. The earlier broad normalization was narrowed during the [published v1.2.0 acceptance](ACCEPTANCE-PUBLISHED-1.2.0.md).

Both product and replacement maps reject malformed rows and unavailable directives before import. Empty replacement maps and supported static fallbacks remain valid. The database regression checks preview and apply rejection without writes. The [2026-10-01 continuation](ACCEPTANCE-2026-10-01.md) records 59 passing tests / 143 assertions and native CLI generation with static and registered-directive fallback values.

The separate [legacy compatibility suite](LEGACY-COMPATIBILITY.md#validation-and-reversal) requires isolated base and Google add-on packages. It enables strict deprecation exceptions and tests actual legacy classes. It does not run as part of the importer suite or require patching installed legacy modules merely to test the importer.

Run syntax, metadata, and schema checks separately:

```sh
php dev/validate.php /path/to/magento
composer validate --strict --no-check-publish
```

The validator also works when the selected Magento installation already contains a Composer-installed copy of this module. It validates the current checkout without registering the module a second time. Dependency exclusions apply inside the package, so running the validator from an installed `vendor/rocketweb/...` path still checks its files. Subprocess regressions reproduce the former duplicate-registration fatal and zero-file validation result.

The [October 3 Magento Open Source continuation](ACCEPTANCE-2026-10-03.md) uses the installed PHPUnit 10.5.65 runner on Magento 2.4.8: **61 tests, 147 assertions**. It separately checks native CLI generation, schema upgrades, receipt retention, and restoration with the destination 1.2.0 candidate. This profile does not repeat the earlier authenticated browser or transfer tests.

The [2026-09-30 acceptance record](ACCEPTANCE-2026-09-30.md) records a disposable full install, normal Composer install/remove/reinstall, DI compilation, authenticated Admin checks, complex-product and scope comparisons, isolated FTP/SFTP uploads, receipt retention, and database/file restore. Its continuation validates the opt-in legacy patches and native destination generation after parameter normalization. Material output differences remain documented. Before a production release, repeat acceptance with the supported destination release and the store's real configuration, catalog types, scopes, extensions, and recipient. Synthetic acceptance does not establish those store-specific results.

## Coordinated 1.0.0 / 1.2.1 candidate

The current release worktree is based on importer commit `369a2cd508aa5713a00048f647039ed4e22850e8`, including the shipping-weight correction and published-v1.2.0 output evidence. Its full unit and disposable database suite passes against the isolated destination 1.2.1 candidate: **63 tests, 150 assertions** on Mage-OS 3.5/PHP 8.5/PHPUnit 12 and **63 tests, 149 assertions** on Magento Open Source 2.4.8/PHP 8.4/PHPUnit 10. Original legacy XML compatibility runs with a supplied local legacy checkout; the public CI workflow omits that licensed input.

Data providers retain PHPUnit 9 annotations alongside PHPUnit 10/12 attributes. Mock declarations avoid unrelated PHPUnit 12 notices. These are test-tooling changes; the importer runtime remains the current reviewed baseline. The [release preparation record](RELEASE-1.0.0-PREPARATION.md) records exact commands and the remaining review/publication gates.

The current opt-in legacy suite also passes on both native runners with strict runtime-deprecation handling. Its PHP 8.5 cast correction, patch application, checksums, and exact reversal are recorded in [legacy compatibility](LEGACY-COMPATIBILITY.md#100-release-candidate-continuation). Full legacy store generation on PHP 8.5 remains outside that standalone test result.
