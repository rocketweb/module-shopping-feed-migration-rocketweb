# 1.0.0 launch preparation

**Historical preparation record.** Matt authorized committing and publishing both packages on October 4, 2026. Preparation-stage statements below retain their original scope. Current release state is recorded in [GitHub Releases](https://github.com/rocketweb/module-shopping-feed-migration-rocketweb/releases/tag/v1.0.0).

Prepared locally on October 3, 2026 to accompany Mage-OS Shopping Feed 1.2.1. Matt's implementation review comes before release. No commit, push, pull request, merge, tag, GitHub release, Composer registration, deployment, or real-store migration was performed.

## Exact candidate

The isolated worktree is `/private/tmp/shopping-feed-migration-launch`, branch `release/1.0.0`, based on `369a2cd508aa5713a00048f647039ed4e22850e8`. The source baseline was refreshed from the newer local commit, with a backup and a clean three-way merge of all candidate changes. The primary checkout was preserved. This includes the shipping-weight preservation fix and [published-v1.2.0 generated-output evidence](ACCEPTANCE-PUBLISHED-1.2.0.md).

The destination worktree is `/private/tmp/shopping-feed-1.2.1`, based on released v1.2.0 commit `4b168b8ddaca7c72572710948db9ecd320a85c98`, with uncommitted issues #9 through #11 fixes and optional discovery. The supplied review bundle identifies both uncommitted candidates through complete patches and SHA-256 file manifests.

Package `rocketweb/module-shopping-feed-migration-rocketweb` remains independently installable. Its `mage-os/module-shopping-feed:^1.1` requirement preserves prior tested paths; the coordinated launch instructions explicitly select 1.2.1. Core contains only a Composer suggestion and read-only discovery. The public companion repository exists but had no source or releases when checked during preparation. No Packagist availability is claimed.

## Prepared changes

- [README](../README.md), [draft release notes](releases/1.0.0.md), changelog, and core 1.2.1 migration documentation describe the launch and separate preview/import/activation/cutover decisions.
- Test data providers keep PHPUnit 9 annotations and add PHPUnit 10/12 attributes. Mock declarations avoid unrelated PHPUnit 12 notices.
- The database suite permits an unprivileged local test port while fixing the loopback host, disposable database name, and test table prefix.
- The optional legacy patch adds the reproduced PHP 8.5 boolean-cast correction. Its manifest and Composer example use the new checksum. Strict per-test deprecation handling replaces the obsolete PHPUnit XML setting without weakening legacy checks.
- The prepared [CI workflow](../.github/workflows/ci.yml) checks metadata/syntax on PHP 8.1 through 8.5 and importer/database fixtures on Magento 2.4.8-p5 and Mage-OS 3.5.0. Pinned actions install the current checkout into a disposable project and retain normal Composer audit behavior. Remote CI remains unrun.

## Current local results

| Check | Result |
| --- | --- |
| Importer, Mage-OS 3.5 / PHP 8.5.9 / PHPUnit 12.5.33 | 63 tests, 150 assertions |
| Importer, Magento 2.4.8 / PHP 8.4.24 / PHPUnit 10.5.65 | 63 tests, 149 assertions |
| Opt-in legacy runtime, Mage-OS / PHP 8.5 / PHPUnit 12 | 24 tests, 112 assertions |
| Opt-in legacy runtime, Magento / PHP 8.4 / PHPUnit 10 | 24 tests, 58 assertions |
| Complete legacy patch application and preflight | Zero fuzz; original and patched hashes match |
| Complete legacy patch reversal | All nine base and one Google file hashes restored |
| Package validator, both dependency roots | 26 PHP/template files, seven XML documents |
| PHP syntax, 8.1 through 8.5 | 26 PHP/template files per version |
| Composer strict metadata | Passed |

Importer checks supplied original legacy XML through `LEGACY_FEED_ROOT=/Users/matt/code/m2-shopping-feed`. They include source preservation, inherited defaults, URL normalization and weight-default preservation, malformed mapping rejection without writes, encrypted receipts, disabled imports, held schedules/uploads, stale/duplicate request rejection, activation checks, transaction failure, and conflict-safe rollback.

Database tests used only container `shopping-feed-121-migration-test`, a loopback-only MariaDB 11.4 database named `feed_migration_test`, port 13419, and `rw_migration_test_` tables. It was separate from the other acceptance stacks. Container cleanup is recorded in the supplied review bundle.

The legacy tests used disposable copies of the original base and Google packages with this candidate's explicit patches. Original legacy checkouts were not changed. The PHP 8.5 probe first failed on the deprecated cast; it passed after the one-line compatibility correction. Full legacy store generation on PHP 8.5 was not repeated.

Reproduce importer checks from the worktree:

```sh
MAGENTO_ROOT=/path/to/installed/platform \
SHOPPING_FEED_ROOT=/path/to/core/candidate \
LEGACY_FEED_ROOT=/path/to/legacy-sibling-checkouts \
MIGRATION_TEST_DB=feed_migration_test \
MIGRATION_TEST_PORT=13389 \
php /path/to/installed/platform/vendor/bin/phpunit -c phpunit.xml.dist

php dev/validate.php /path/to/installed/platform
composer validate --strict --no-check-publish
```

Use only the disposable database described in [Verification](VERIFICATION.md). The public CI omits original paid legacy source; the original-XML test skips unless supplied locally. The separate opt-in legacy suite and patch commands are documented in [Legacy compatibility](LEGACY-COMPATIBILITY.md).

## Review and publication gates

Matt should review both complete diffs, the normal/failed migration paths, and the coordinated installation docs. Earlier [Mage-OS acceptance](ACCEPTANCE-2026-09-30.md), [Magento acceptance](ACCEPTANCE-2026-10-03.md), and [published-v1.2.0 comparison](ACCEPTANCE-PUBLISHED-1.2.0.md) retain their exact baselines. Full installation, authenticated browser, CLI generation, uploads, schema lifecycle, and restore were not all repeated against destination 1.2.1. Existing-store customizations, external recipients, and real cutover remain separate acceptance gates.

Keep the legacy modules enabled during schema upgrades. Imports stay disabled with schedules/uploads held until separately reviewed activation. Backups must retain source and destination records, code/configuration/output, receipts, and the matching encryption key. Shared global settings, custom PHP, external schedulers, and public fetch URLs need explicit review. The module does not create the operator's backup or automate recipient URL changes.

After Matt's pass and exact publication approval, verify source pushes and remote CI, tag/release references, Composer registration/version/source/dist, and downloaded package contents independently. Confirm both stable packages are installable and the companion's README/installation target is live before announcing the core notice. No later publication state is implied by this preparation.
