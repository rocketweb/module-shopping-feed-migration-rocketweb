# Disposable runtime acceptance, 2026-09-30

The local implementation passed the checks below after fixing two Admin blockers. This is synthetic acceptance on one platform, with a documented adjustment to the legacy fixture. It is not approval to migrate a store or publish a release.

## Environment and source

- Disposable install: `/private/tmp/rocketweb-migration-acceptance-20260930`.
- Platform: Mage-OS 3.5.0, PHP 8.4.24, Symfony Console 7.4.17, MariaDB 11.4, OpenSearch 3.8.0.
- Legacy: original Rocket Web 2.3.4 source plus Google Shopping, Local Inventory, and Promotions add-ons, copied from `/Users/matt/code/m2-shopping-feed`.
- Destination: development commit `55ee46717406c7e2166ed75e76d27724e9a34486`, followed by v1.1.0, commit `4db58bb009f12cb84ab2fee2c2bac20f91493481`.
- Importer: this local checkout, including the Admin block and ACL corrections.
- Database containers and all HTTP/FTP/search ports were bound to loopback. No production database, seller, recipient account, or existing store was used. No cron runner was started.
- Initial checks registered copied `app/code` modules. The continuation installed the destination and importer through Composer and exercised normal removal/reinstallation, as detailed below.

The unmodified legacy commands failed during `setup:install`: `GenerateCommand::execute()` and `ScheduleCommand::execute()` lacked the `int` return type required by Symfony. The disposable copies received only that signature adjustment. The original legacy source was not edited. `install-unmodified-legacy.log` records the failure. An approved compatibility path for a real installation remains a release requirement.

## Bugs reproduced and fixed

1. The migration Admin block promoted a private `$formKey` property over Magento Backend Template's protected property. PHP refused to load it, and DI compilation failed. The block now uses the parent's form-key service. `AdminBlockTest` failed with the inheritance fatal before the change and passed afterward.
2. The importer ACL put `MageOS_ShoppingFeed::mageos_shopping_feed` directly beneath the Admin root instead of beneath `Magento_Catalog::catalog`. Merging the real ACL created a duplicate resource and prevented Admin login. The hierarchy is corrected. `AclTest` reproduced the duplicate before the change and passed afterward.

## Observed results

| Check | Result |
| --- | --- |
| Fresh `setup:install`, then `setup:upgrade` | Passed with the documented legacy fixture adjustment |
| Full `setup:di:compile` | Passed for the development destination and v1.1.0 |
| Importer PHPUnit 9.6.37 suite | 33 tests, 85 assertions; passed against both destination versions |
| Syntax/metadata/XSD validation | 22 PHP/template files and 7 XML documents passed |
| `composer validate --strict --no-check-publish` | Passed |
| PSR-12 scan of PHP and templates | Zero errors; 75 long-line warnings remain |
| Authenticated Admin | Preview, explicit import, unchanged rollback, and activation passed |
| ACL | Restricted user saw the permission-denied review page; direct apply returned HTTP 403; discovery link was hidden |
| CSRF/method restriction | Missing-form-key POST returned `Invalid Form Key. Please refresh the page.` and wrote no receipt; GET apply returned HTTP 404 and wrote no receipt |
| Import containment | Source records preserved; destination initially disabled with no schedules/uploads |
| Rollback | Destination feed and cascading configuration removed; source and encrypted receipt retained |
| Activation | Held schedule/upload restored; original synthetic feed remained disabled |
| Upload | Pre-activation generation uploaded nothing; post-activation FTP upload to `127.0.0.1:18890` matched the generated file's SHA-256 |
| Minimum destination v1.1.0 | Real previews and generation passed for all three feed types plus Promotions; a new source imported and rolled back successfully |

Browser evidence is in `migration-receipts.png`, `acl-denied.png`, and `discovery-dismissed.png` under the disposable install. The saved screenshots were inspected.

### Generated output comparison

The catalog contained one synthetic simple product, priced at USD 29.95 with 50 units in stock. The three feed fixtures explicitly mapped ID, title, price, and availability. The Promotions fixture used one synthetic cart rule.

- Generic: one product on each side, byte-identical output.
- Google Shopping and Local Inventory: one product on each side, identical parsed row values. The destination moved `id` from first to last column. Raw bytes therefore differed.
- Promotions: one promotion on each side. The nine shared fields matched after accounting for destination enum casing. The destination emitted lowercase `all_products`, `online`, and `no_code`, and added two `promotion_destination` columns containing `shopping_ads` and `free_listings`.

`comparison.json` and `promotions-comparison.json` record the comparison. The preview now warns that destination formatting and validation can change generated output. Configuration migration must not be presented as a promise of byte-identical files.

### Composer installation and removal continuation

After local importer commit `7c390cf`, Composer 2.10.2 resolved and installed `mage-os/module-shopping-feed:1.1.0` and the importer at `dev-main 7c390cf` from mirrored local path repositories. The disposable root retained its real Mage-OS dependencies. A local Composer repository contained unaltered package metadata from its original lock file, with networking disabled. The solver installed exactly two packages and updated no unrelated dependencies. Mage-OS Framework 3.5.0's declared replacement for `magento/framework:103.0.9` satisfied the importer's framework requirement.

Both modules registered from `vendor/`. `setup:upgrade`, DI compilation, preview/generation for the four original feed fixtures, and a fresh import/rollback passed. After disabling the importer, actual `composer remove`, `setup:upgrade`, `composer require`, module enable, and another `setup:upgrade` preserved all seven receipt rows byte-for-byte. DI compilation also passed after reinstallation. This did not test Packagist discovery, a published distribution archive, Magento Open Source, or other dependency versions.

Evidence is under `composer-acceptance/`: dry-run, install, remove, reinstall, upgrade and compilation logs, original Composer files, unaltered locked package metadata, and the seven-row receipt baseline. The runtime's Composer dependencies remain installed under `vendor/`; the source path repositories must remain available to repeat installation.

### Expanded catalog and scope comparison

The continuation used eight products: the original simple, two configurable variants, a configurable parent, two standalone components, a grouped parent, and a dynamic bundle. All three complex-product modes were set to include parents and associated products. It added a category mapping, a second store view with an overridden product title, and a second website with its own stock/source assignment. The import preserved saved settings, inherited column maps, source records, and store IDs. Uploads and schedules remained held.

| Source | Fixture | Legacy rows | Destination rows | Result |
| --- | --- | --- | --- | --- |
| 201 | Generic, custom five-column map | 8 | 8 | Same values except two configurable variants changed stock status |
| 202 | Google Shopping, custom map, second store view | 8 | 8 | Same stock differences; scoped title and category mapping preserved; columns reordered |
| 203 | Local Inventory, full inherited seven-column map | 7 | 5 | Destination omitted grouped/bundle parents without source items; all five retained rows matched |
| 204 | Generic, full inherited map | Generation failed | 7 | Column map preserved; destination generated 17 columns |
| 205 | Google Shopping, full inherited map, second store view | Generation failed | 7 | Column map preserved; destination generated 29 columns with category ID `212` |
| 207 | Local Inventory, second website and explicit source-code map | 1 | 1 | Quantity `9`, price and availability preserved; destination applied `migration_warehouse=STORE-207` |

The two variants changed from legacy `out_of_stock` to destination `in_stock`. The destination's configurable-associated availability mapper uses the parent's `isSalable()` result; legacy uses its stock-item fallback, which reports out of stock for this parent without a source item. The Local Inventory processor now returns the rows from linked, enabled sources even when that set is empty; legacy falls back to previously generated parent rows. Current database records confirmed that the configurable, grouped, and bundle parents had no source items. These are destination behavior differences, not evidence of a failed configuration copy. They still require review before cutover.

The original `AdditionalImageLink` mapper accesses an undefined `$feed` property and calls `getConfig()` on null. Both full-default legacy runs reproduced this failure. That mapper was not patched, so output equivalence for those two fixtures remains unverified. Their destination maps generated seven rows; this is a generation result, not a claim that every catalog product passed default filters.

The source-code map in fixture 207 was an explicitly seeded supported destination setting. Legacy ignored it; the importer retained it and the destination applied it. This does not establish an original legacy Admin UI for that setting. The website's unassigned products and default inventory source were excluded from its output.

The comparison normalizes UTF-8 BOM placement and column order and retains duplicate column occurrences. Runtime evidence: `expanded-fixture.json`, `expanded-comparison.json`, `expanded-comparison.log`, `website-fixture.json`, and `website-comparison.json`. A compact, credential-free result is committed in [expanded acceptance evidence](evidence/2026-09-30-expanded-acceptance.json). Original implementation probes remain in the disposable directory; fixture scripts are one-shot, and direct generator runs update feed status.

### SFTP continuation

Fixture 206 held a synthetic encrypted SFTP credential during import. Its upload directory remained empty. After disabling the original feed and explicitly activating the replacement, the destination restored the SFTP settings and successfully uploaded three product rows through Magento's actual SFTP uploader to a Paramiko 5.0.0 server bound to `127.0.0.1:18891`. Generated and uploaded SHA-256 hashes matched. Evidence: `sftp-acceptance.log` and `sftp-comparison.json`. External recipient authentication, host policy, and URL cutover remain untested.

### Receipt lifecycle and restore

Six receipts, including imported, activated, and rolled-back states, were hashed before the lifecycle rehearsal. They remained byte-identical after each of:

1. `module:disable RocketWeb_ShoppingFeedMigration` and `setup:upgrade`.
2. Moving the disabled module's code outside `app/code`, then `setup:upgrade`.
3. Restoring the code, enabling the module, and running `setup:upgrade`.

The receipt table deliberately has no deletion whitelist. Do not add it to a deletion whitelist without revisiting the retention requirement. This result covers Mage-OS 3.5's declarative-schema lifecycle with an unprefixed installation. The later normal Composer remove/reinstall also passed. Other platform versions and `module:uninstall` are outside this result.

Before the lifecycle changes, `mariadb-dump --single-transaction` backed up the synthetic database. It was restored into a separate `migration_restore` database. Original/imported feed and configuration tables, destination schedules/uploads, catalog product records, and all receipts matched. All restored receipt payloads decrypted successfully. A separate archive restored 16 configuration/generated files; the saved encryption-key file and generated artifacts matched their originals. The original source/dependency copies remained available throughout the rehearsal.

Evidence files include `before-lifecycle.sql`, `before-lifecycle-files.tar.gz`, `receipt-baseline.json`, `retention-disabled.log`, `retention-removed.log`, `retention-reinstalled.log`, and the restored files directory. Database/configuration archives contain test credentials and encryption material; keep their restrictive permissions and do not publish them.

The continuation backed up the expanded catalog and fourteen receipts to `after-expanded-acceptance.sql`, restored it to `migration_restore_expanded`, and compared 19 feed, catalog, store, and inventory tables. All matched; all fourteen receipt payloads decrypted. `after-expanded-files.tar.gz` restored 31 configuration, Composer, media, and generated/uploaded files with matching bytes. Both backups have mode `0600`. Evidence: `expanded-restore.log`, `expanded-restore-result.json`, and `after-expanded-restored/`. Use this later backup to resume expanded acceptance; the earlier lifecycle backup contains only the original fixtures. The seven-row Composer baseline is preserved separately in `composer-acceptance/receipt-baseline.json`.

## Separate discovery change

The main-module notice is in `/private/tmp/shopping-feed-migration-discovery-20260930`, on `feat/rocketweb-migration-discovery`. It is based on `55ee46717406c7e2166ed75e76d27724e9a34486` and is committed locally as `c137c44`. A reviewable patch is `/private/tmp/shopping-feed-migration-discovery-20260930.patch`.

- Installed/authorized, installed/unauthorized, module-absent/legacy-present, and dismissal states passed in the browser.
- Registered-module, prefixed-table-only, clean-install, and ACL cases are covered by five focused tests.
- Installed PHPUnit 12.5.33: 651 tests, 1,766 assertions passed. Mixing the PHPUnit 9 PHAR with the full main-module suite's PHPUnit 12 mocking implementation failed; using the installed runner resolved that tool mismatch. The five focused discovery tests independently passed on PHPUnit 9.
- Magento XML validation passed for 24 files. Consolidation validation passed for eight feed types. Its legacy-identifier guard permits only exact discovery literals in the notice/test files. A negative probe confirmed legacy PHP class references still fail that guard.
- `composer suggest` awaits package publication. The installation link must resolve to the published README before the notice is released.

## Compatibility continuation

The following compatibility continuation supersedes the earlier unpatched-generation limitation; it does not establish real-store acceptance.

### Legacy patches and native CLI continuation

Two opt-in patches now cover the exact base and Google Shopping 2.3.4 packages. The base patch fixes command return types, image mapper feed access and `strip()`, the shipping-country type check, and null query parameters in the simple/configurable/grouped URL mappers. The Google patch handles a null identifier parameter. Eight source files are covered by original/patched SHA-256 manifests and a read-only preflight. Neither original source checkout was edited. No patching plugin or automatic legacy modification was added to the migration module.

The patches were generated with `symplify/vendor-patches:12.2.0`, combined by package, and applied by `cweagans/composer-patches:2.0.0` during actual Composer reinstalls of mirrored path packages. All patched hashes matched. The preflight rejected customized files and a different version for both packages. Reversing each patch in a separate copy restored the original hashes. See [the opt-in installation and reversal instructions](LEGACY-COMPATIBILITY.md).

The strict compatibility suite passed **23 tests, 55 assertions**. Each failure class was reproduced before correction, including deprecations that Magento turns into exceptions. The complete patches passed `setup:upgrade`, DI compilation, and native legacy CLI test generation for Generic and Google Shopping. Each native test listed the configurable parent and two variants. Schedule-command help also loaded successfully; the scheduler was not run.

Full-default legacy generation now succeeds. Generic produces seven rows and 17 columns on both sides. Google Shopping produces seven rows with 21 legacy columns and 29 destination columns. Additional observed differences:

- All generated product URLs preserve the nonstandard loopback port in the destination; legacy drops it. Configurable variant links in the second view also add `___store=migration_second` in the destination.
- Google uses `promotion_id` instead of `promotions_id` and adds `age_group`, `color`, `gender`, `item_group_title`, `material`, `pattern`, `size`, and `variant_option`.
- The fixture's Google `identifier_exists` changes from `FALSE` to empty. The prior variant-stock and Local Inventory parent-row differences remain.

These are measured output differences requiring recipient review, not importer equivalence claims. The runtime comparison and detailed per-field differences are in `compatibility-comparison.json` and [committed compatibility evidence](evidence/2026-09-30-legacy-compatibility.json).

Native destination CLI testing then reproduced an importer defect: legacy inherited column maps contain null parameters, which break the destination Generic URL mapper under normal PHP error reporting. The prior direct-generation harness suppressed deprecations and missed this. The planner now converts only null parameters to empty strings in product and replacement maps, lists the conversion in preview, and preserves source records, missing keys, zero, false and configured parameters. Three regression tests failed before the fix. The importer suite now passes **36 tests, 92 assertions**.

Fresh sources 208/209 imported through the fixed planner. Native destination CLI generation listed three rows for each with exit code zero. Both imports remained disabled, with no schedules/uploads, and source records were preserved. Previously imported historical fixtures were not rewritten. Evidence: `normalized-import-result.json`, `normalized-destination-208.log`, `normalized-destination-209.log` and `normalized-import.log`.

The final compatibility backup restored 19 tables and decrypted all sixteen receipts in `migration_restore_compat`. A separate archive restored 502 files, including patched legacy code and importer source, with matching bytes. Both `after-compatibility-acceptance.sql` and `after-compatibility-files.tar.gz` have mode `0600`. Use these artifacts to resume the latest checkpoint. Evidence: `compatibility-restore.log`, `compatibility-restore-result.json`, and `after-compatibility-restored/`.

## Remaining release gates

- Validate the opt-in legacy patches on the intended platform and with existing customizations. Local Composer path-package application passed; actual store distributions and other platform versions remain untested.
- Repeat acceptance with representative store data and custom integrations. Review the observed variant-stock and Local Inventory row-count changes. Synthetic coverage now includes configurable/grouped/bundle products, default/custom maps, categories, source-code mapping, and website/store scopes; it does not establish acceptance for a real store.
- Test the actual recipient and URL cutover. FTP and SFTP passed only on loopback.
- Repeat Composer/deployment checks on the exact supported platform. Local path installation passed; published package discovery/distribution remains unverified. Validate Magento Open Source separately from Mage-OS 3.5.
- Local commits were subsequently authorized. Obtain explicit authorization for push, publication, package registration, installation on an existing store, and migration. These states are independent and remain incomplete.

## Resuming the disposable test

The final Composer-installed destination is v1.1.0; the importer contains the null-parameter fix. The disposable base and Google legacy copies have the opt-in patches applied. The development destination and notice remain in `destination-development`. Test services are stopped after validation. MariaDB used tmpfs, so its stopped container is removed; protected SQL backups are the recovery artifacts.

The directory contains the install script and guarded runtime probes (`runtime-bootstrap.php`, `seed.php`, `compare.php`, `promotions.php`, `check-import-and-preview.php`, `check-upload.php`, `lifecycle-check.php`, and `verify-v1.1.php`). Seed/migration probes are one-shot fixtures, not commands to rerun against an existing database. `runtime-bootstrap.php` rejects databases other than `migration_acceptance` at `127.0.0.1:13389`. Recreate only disposable containers and restore the protected backup before another run.
