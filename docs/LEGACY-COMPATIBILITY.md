# Rocket Web 2.3.4 runtime compatibility

Opt-in patches are available for the original `rocketweb/module-shopping-feeds:2.3.4` and `rocketweb/module-shopping-feeds-google:2.3.4` packages. The September version was tested with Mage-OS 3.5.0, PHP 8.4.24, and Symfony Console 7.4.17. The current version additionally handles feed saves and passed full generation on Magento Open Source 2.4.8 / PHP 8.4.24 / Symfony Console 6.4.47. Installing this migration module does not apply these patches or install a patching plugin.

The release-candidate base patch changes nine legacy files; the Google patch changes one add-on file:

| File | Correction |
| --- | --- |
| `Console/Command/GenerateCommand.php` | Adds Symfony's required `int` return type |
| `Console/Command/ScheduleCommand.php` | Adds Symfony's required `int` return type |
| `Model/Product/Adapter/AdapterAbstract.php` | Uses `(bool)` to avoid PHP 8.5's deprecated `(boolean)` cast |
| `Model/Feed.php` | Cleans strings without passing null to `strtr()` during native full generation; preserves null mapper defaults and numeric values |
| `Model/Product/Mapper/Generic/Simple/AdditionalImageLink.php` | Gets the feed through the adapter and replaces nonexistent `strip()` with `trim()` |
| `Model/Product/Mapper/Generic/Simple/Shipping.php` | Checks country configuration is an array before filtering it; unset shipping produces an empty field |
| `Model/Product/Mapper/Generic/{Simple,Configurable/Associated,Grouped/Associated}/Url.php` | Treats null query parameters as empty strings while preserving configured query strings and variant links |
| Google add-on: `Model/Product/Mapper/Google/Simple/IdentifierExists.php` | Uses default identifier behavior for a null parameter without passing null to `explode()` |

These patches address runtime failures. They do not change product selection, stock calculations, URL destinations, or Google column conversion to force output equivalence.

## Files and exact baseline

- [Base compatibility patch](../patches/rocketweb-shopping-feeds-2.3.4-runtime-compatibility.patch)
- [Base original and patched SHA-256 manifest](../patches/legacy-2.3.4-manifest.json)
- [Google add-on compatibility patch](../patches/rocketweb-shopping-feeds-google-2.3.4-runtime-compatibility.patch)
- [Google original and patched SHA-256 manifest](../patches/legacy-google-2.3.4-manifest.json)
- [Composer Patches 2 example](../patches/composer-patches.example.json)
- [Read-only preflight](../dev/verify-legacy-patch.php)

Run the preflight against the unpatched legacy package before staging a change:

```sh
php /path/to/migration/dev/verify-legacy-patch.php vendor/rocketweb/module-shopping-feeds
php /path/to/migration/dev/verify-legacy-patch.php vendor/rocketweb/module-shopping-feeds-google
```

It checks package identity/version, the patch checksum, and the covered original file hashes. It exits nonzero if a file is missing, customized, or already patched. Stop and review any mismatch. Do not overwrite a customized package to make the check pass. The guard covers the listed files; it does not establish that the rest of the legacy package is unmodified.

## Composer installation on a reviewed staging copy

Back up the store's code, Composer files, database, generated feeds, configuration and encryption key first. Preserve existing patches and any custom code. Composer reinstall replaces the entire legacy package directory, so customizations must already be represented in reproducible packages or patches.

Use the project's normal process to install and allow `cweagans/composer-patches` 2.x. Version 2.0.0 was tested. Copy the required patches to the project's `patches/` directory and merge the supplied example into its patch definitions. Retain the supplied `sha256` and `depth: 1` fields. Omit the Google entry when that add-on is not installed.

For a project using a dedicated `patches/rocketweb-legacy.json` file, merge this configuration with the existing root `composer.json`:

```json
{
    "require": {
        "cweagans/composer-patches": "^2.0"
    },
    "config": {
        "allow-plugins": {
            "cweagans/composer-patches": true
        }
    },
    "extra": {
        "composer-patches": {
            "patches-file": "patches/rocketweb-legacy.json"
        }
    }
}
```

Composer Patches 2 reads this setting under `extra.composer-patches`. The older `extra.patches-file` location was not honored by the tested version. If the project already uses a patches file, merge the new package entry into that file instead of replacing its configuration. Patch URLs in the example are relative to the project root.

After the plugin is installed and root Composer metadata is current:

```sh
composer patches-relock
composer reinstall rocketweb/module-shopping-feeds rocketweb/module-shopping-feeds-google --no-interaction
php /path/to/migration/dev/verify-legacy-patch.php vendor/rocketweb/module-shopping-feeds --patched
php /path/to/migration/dev/verify-legacy-patch.php vendor/rocketweb/module-shopping-feeds-google --patched
```

Review `composer.lock` and `patches.lock.json`, and retain them in the store's normal deployment process. Keep the legacy module enabled during schema upgrades. Run `setup:upgrade`, DI compilation, and the store's own generation checks before approving deployment. The local test used a mirrored path package; a store's published/private distribution and any existing patch combinations need their own validation.

For an `app/code` installation, apply the reviewed diff through that installation's source/deployment workflow, then use the same preflight with `--patched`. Composer cannot patch a module it does not manage.

## Validation and reversal

The October 3 published-release acceptance reproduced the feed-save failure before adding its regression test and patch. The current compatibility suite passes **24 tests, 58 assertions** on Magento Open Source 2.4.8. See [the release acceptance record](ACCEPTANCE-PUBLISHED-1.2.0.md). The historical September Composer result below used the earlier eight-file combined patch; current checksums are in the linked manifests.

The separate compatibility suite exercises actual legacy classes against installed Magento/Symfony dependencies:

```sh
MAGENTO_ROOT=/path/to/magento \
LEGACY_PATCH_ROOT=/path/to/patched/legacy-package \
LEGACY_GOOGLE_PATCH_ROOT=/path/to/patched/google-package \
php /path/to/phpunit-9.phar -c phpunit-legacy.xml.dist
```

Twenty-three tests and 55 assertions passed after Composer reinstalled and patched both packages. The command tests failed on both original signatures. Image tests first failed on the undefined property, then on `strip()`. Shipping tests failed on null, string and boolean settings before the type check. Strict deprecation checks also reproduced null parameters in all three URL mappers and the Google identifier mapper. Positive cases cover image delimiters, disabled/base-image exclusion, empty galleries, empty country lists, a cached shipping rate, configured URL queries, variant links, and identifier columns.

The preflight rejected a synthetic customization and another package version. Reversing the patch in an isolated copy restored every original hash. To remove it from a Composer project, remove only its definition, relock patches, and rebuild/reinstall the legacy package through the reviewed deployment process. Reversal restores the original runtime defects; it is not a working Mage-OS 3.5 configuration. Use the backed-up compatible code/platform when reversing a deployment.

The September patches passed Mage-OS `setup:upgrade`, DI compilation, CLI test-product generation, and expanded feed generation. See [compatibility evidence](evidence/2026-09-30-legacy-compatibility.json) and the [acceptance record](ACCEPTANCE-2026-09-30.md). The additional feed-save correction is covered by the October Magento acceptance above. No existing store or external recipient was changed.

## 1.0.0 release-candidate continuation

Strict legacy checks also reproduced a PHP 8.5 deprecation when loading the original adapter. The opt-in base patch now changes that cast to `(bool)`. The complete nine-file base patch and one-file Google patch apply with zero fuzz; patched preflight checks pass, and reversal restores every original file hash. Their Composer example and manifests carry the updated checksum.

The separate legacy suite now passes **24 tests, 58 assertions** on Magento 2.4.8/PHP 8.4/PHPUnit 10 and **24 tests, 112 assertions** on Mage-OS 3.5/PHP 8.5/PHPUnit 12. A per-test deprecation handler preserves strict checking without PHPUnit 9's obsolete XML setting. This PHP 8.5 result covers the standalone legacy regression suite; full store installation and generation on PHP 8.5 have not been repeated. See [release preparation](RELEASE-1.0.0-PREPARATION.md).
