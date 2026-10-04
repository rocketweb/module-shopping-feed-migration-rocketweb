# Separate project handoff

Work in `/Users/matt/code/module-shopping-feed-migration-rocketweb` with remote `https://github.com/rocketweb/module-shopping-feed-migration-rocketweb.git`.

Matt authorized local commits and continued acceptance work. This checkpoint is committed locally. No push, release, Packagist publication, existing-store installation, or production migration is authorized or completed.

## Completed on 2026-09-30

The optional module includes Admin review/import/activation/rollback with dedicated ACL and POST/form-key checks, CLI, original XML/default conversion, encrypted receipts, held schedules/uploads, stale-preview rejection, duplicate protection, conflict-safe rollback, and transactional database writes.

The continuation completed a disposable Mage-OS 3.5 installation and runtime acceptance. It reproduced and fixed two blockers:

- The Admin block's private `$formKey` property conflicted with Magento's protected parent property. It now uses the parent service.
- The ACL resource was under the wrong parent, duplicating the main-module resource and preventing Admin login. The catalog hierarchy is now correct.

Both fixes have regression tests observed failing before the corrections. Current local results:

- Importer PHPUnit 9.6.37: **33 tests, 85 assertions**, against both current development destination code and v1.1.0.
- Full `setup:upgrade` and DI compilation passed with both destination versions.
- Authenticated Admin preview/import/rollback/activation, restricted-user denial, GET rejection, and CSRF rejection passed.
- Synthetic Generic output matched bytes. Google Shopping/Local Inventory matched row values, with destination column reordering. Promotions differences were identified and checked.
- A loopback FTP upload after activation matched the generated file; held previews uploaded nothing.
- Six receipts survived disable, code removal, and reinstall with actual `setup:upgrade` runs, byte-identical each time.
- A database restore and configuration/generated-file restore passed; encrypted receipts remained readable.
- Syntax/schema validator: **22 PHP/template files and 7 XML documents**. Composer metadata validation passed.

After Matt's **commit and continue** instruction, the implementation was committed locally as `7c390cf` and acceptance continued:

- Composer installed the importer and destination v1.1.0 into `vendor/`, using real locked platform metadata and local path repositories. Two installs, no unrelated updates. Upgrade and DI compilation passed.
- Normal Composer remove/reinstall with schema upgrades preserved seven encrypted receipts byte-for-byte. Import/rollback and generation also passed from installed packages.
- Eight-product comparisons exercised configurable/grouped/bundle relationships, custom/full default maps, category mappings, a second store view, and a second website with independent inventory.
- The expanded comparison found material destination changes: variant stock status and Local Inventory parent-row omission. Full default legacy generation failed in `AdditionalImageLink`; imported defaults generated successfully. Do not claim output equivalence.
- Actual SFTP activation/upload passed on loopback with matching file hashes.
- Expanded database restore matched 19 tables and decrypted all fourteen receipts; 31 restored files matched. Resume from protected `after-expanded-acceptance.sql` and `after-expanded-files.tar.gz`.
- Importer suite still passed: **33 tests, 85 assertions**. Preview guidance now calls out row counts and stock status.

Credential-free results are in `docs/evidence/2026-09-30-expanded-acceptance.json`.

The next **commit and continue** pass prepared opt-in patches for the exact legacy base and Google Shopping 2.3.4 packages. Eight files address command signatures, image-mapper errors, absent shipping countries, and null URL/identifier parameters. Composer reinstall applied both patches and strict compatibility tests passed: **23 tests, 55 assertions**. Native legacy Generic and Google test generation passed.

Native destination generation exposed an importer bug that the earlier deprecation-suppressed comparison harness had missed: inherited null column parameters break the destination Generic URL mapper. The planner now normalizes only null parameters to empty strings in product/replacement maps and discloses the conversion in the preview. Three regression tests failed before the correction. Importer results: **36 tests, 92 assertions**. Fresh fixtures 208/209 imported through the fixed planner and each generated the parent plus two variants through the native CLI, retaining disabled status and held external work.

Patched legacy full-default output now permits comparison: Generic generated seven rows and 17 columns on both sides; Google Shopping generated seven rows with 21 legacy and 29 destination columns. Stock changes remain. The destination also preserves the loopback port, adds variant columns, renames the promotion column, and changes identifier output. See `docs/LEGACY-COMPATIBILITY.md` and `docs/evidence/2026-09-30-legacy-compatibility.json`.

See [the acceptance record](ACCEPTANCE-2026-09-30.md) for exact versions, evidence paths, fixture scope, and limits.

## Completed on 2026-10-01

Continued local validation found that `filters_map_replace_empty_columns` bypassed directive validation. The planner now validates both column maps, rejects malformed rows and unavailable directives, and names the affected setting. Valid static fallbacks, registered custom directives, empty replacement maps, and null-parameter normalization remain supported.

Regression tests were observed failing against the previous planner. The full importer suite passes: **59 tests, 143 assertions**. Syntax/schema validation and focused PSR-12 checks pass. Native CLI preview rejected invalid replacement rules without writes; a fresh synthetic import generated three rows with the expected static and directive fallback values, remaining disabled with schedules/uploads held. See [the continuation record](ACCEPTANCE-2026-10-01.md) and its credential-free JSON evidence.

Fixture 210 / target 17 was disposable and is not in the protected sixteen-receipt backup. Restore the September 30 backup before running `verify-replacement-maps.php` again. No existing import was rewritten. The same store, platform, recipient, and publication gates remain open.

## Completed on 2026-10-03

The importer passed a separate Magento Open Source 2.4.8 / PHP 8.4.24 check using the destination 1.2.0 candidate. Local Composer installation, schema upgrade, DI compilation, native Generic/Google Shopping/Local Inventory test generation, activation, and rollback passed. Five encrypted receipts survived disable, Composer removal, and reinstall byte-for-byte. A separate restore matched 15 migration-related tables and decrypted all five receipts. Both configuration/key archive files restored identically. See [the platform acceptance record](ACCEPTANCE-2026-10-03.md).

This continuation fixed two development-validator defects: duplicate registration when Magento already contains a Composer-installed importer, and skipping the package's own files when its root is under `vendor`. Both regressions failed before their fixes. Current importer results on Magento 2.4.8: **61 tests, 147 assertions**, using PHPUnit 10.5.65. Syntax/schema checks cover 26 PHP/template files and seven XML documents. Importer runtime behavior did not change in this pass.

The separate discovery change was refreshed onto main-module candidate `ca9030c0ad843fa8d1eea5c638071b2734b579a7`, applying cleanly. It is now committed locally as `93bcbe68c593202462462506b8b6c5ff72675fb0` in `/private/tmp/shopping-feed-migration-discovery-20261003`, branch `feat/rocketweb-migration-discovery-20261003`; its patch is `/private/tmp/shopping-feed-migration-discovery-20261003.patch`. The full main suite passed **814 tests, 1,906 assertions**. Native checks confirmed Admin ACL construction and legacy detection while the importer was absent. The primary checkout was clean and was not edited. Publication and Composer `suggest` remain deferred.

The additional disposable runtime is `/private/tmp/rocketweb-migration-magento-248-20261003`. Its protected `evidence/after-acceptance.sql` contains five receipts, and `evidence/configuration-and-key.tar.gz` contains the matching configuration/key. These are independent of the September 30 Mage-OS backups below. The retained clone uses production mode; the known original inventory XML schema defect still prevents legacy generation in developer mode. No source XML was changed. This profile did not repeat browser, transfer, or complex-product acceptance.

## Published v1.2.0 follow-up on 2026-10-03

Matt explicitly requested legacy-first installation and actual feed comparison against the latest published destination. This is now exercised on a fresh Magento Open Source 2.4.8 store. The original Rocket Web packages generated five full baseline files while destination/importer code and schemas were absent. Composer then downloaded published v1.2.0 (`4b168b8ddaca7c72572710948db9ecd320a85c98`) from Packagist and mirrored the local importer. Two installs, no dependency updates. All 675 destination files match the release archive.

This exposed and fixed a real importer defect: blanket null-parameter normalization erased the default `kg` weight unit. Only null URL parameters are normalized now; other null defaults are preserved. Both column-map regression cases failed before the fix. The original full-generation CLI also exposed `Feed::beforeSave()` passing null to `strtr()`. The opt-in legacy patch now cleans only strings, with a failing-then-passing regression and updated manifests. The source checkouts remain unchanged.

After a pre-import database restore and corrected importer reinstall, all five native migrations and full generations passed. Two additional configured samples also passed. The four-row Generic sample is byte-identical. Complex Generic/Google samples retain all expected values except verified variant-availability and Google identifier/column changes. Local Inventory reflects MSI source assignment and configurable child sources; an explicit assigned-zero-stock probe preserves `out_of_stock`, quantity 0 and price exactly. Original inherited complex-parent weight defaults remain incomplete; an explicit-kg Google sample verifies all nine rows have complete units.

Current results: **63 importer tests / 149 assertions**, **24 compatibility tests / 58 assertions**, **77 output assertions**, syntax/schema validation and DI compilation. See [the complete report and sample files](ACCEPTANCE-PUBLISHED-1.2.0.md). Runtime/probes/backups are under `/private/tmp/rocketweb-migration-release-20261003`; the new containers are removed after backup. No existing store, published destination code, push or release was changed. The older candidate/platform evidence above remains historical.

## Important compatibility finding

The original legacy packages still have the reproduced compatibility defects. Reviewable patches, checksums, a read-only preflight, Composer examples and regression tests are now included. They are opt-in and have only been applied to disposable copies. The original sibling source checkouts remain unchanged. Validate customizations, other platform versions, and actual store distributions before applying them elsewhere.

## Original separate main-module change

The discovery notice is implemented in `/private/tmp/shopping-feed-migration-discovery-20260930`, branch `feat/rocketweb-migration-discovery`, based on `55ee46717406c7e2166ed75e76d27724e9a34486`. It is committed locally as `c137c44`. Its patch is `/private/tmp/shopping-feed-migration-discovery-20260930.patch`.

The main-module suite passed with installed PHPUnit 12: **651 tests, 1,766 assertions**. XML and consolidation checks passed. Browser acceptance covered authorization, dismissal, and installed/absent migration-module states. See [core integration](CORE-INTEGRATION.md).

The primary checkout `/Users/matt/code/module-shopping-feed` was not edited by this continuation. Unrelated edits appeared there during the work in `Controller/Adminhtml/Feed/Builder.php`, `Model/Promotions/Provider/Map.php`, and their tests, plus `docs/reviews/2026-09-30-security-review.md`. Preserve that concurrent work and recheck status before later integration. Runtime acceptance used the copied baseline identified above, not those later edits.

## Next work

1. Review the importer normalization, column-map validation, and opt-in legacy patches. Review stock, row-count, URL, and Google-column differences before accepting output. The local compatibility blockers are reproduced and fixed, but existing-store acceptance remains open.
2. Repeat installation/removal on any additional intended platform. Published destination v1.2.0 discovery, installation and generation now pass on Magento Open Source 2.4.8. Importer publication/discovery remains untested. The narrowed null normalization was verified against v1.2.0; repeat runtime acceptance before claiming unchanged behavior on older destination releases.
3. Run representative store and external-recipient acceptance before cutover. Synthetic complex-product, scope, mapping, and SFTP checks are documented, with explicit limits.
4. Review/integrate the refreshed October 3 main-module notice branch. Publish its installation target before release; only then add Composer `suggest`.
5. Local commits are authorized. Obtain explicit authorization before pushing or publishing a tested release. Verify package registration/discovery independently.

The disposable runtime is `/private/tmp/rocketweb-migration-acceptance-20260930`. The final Composer-installed destination is v1.1.0. The latest recovery artifacts are `after-compatibility-acceptance.sql` (sixteen receipts) and `after-compatibility-files.tar.gz` (502 verified files, including patched code). Both have mode `0600`; their restore checks passed. Earlier backups represent earlier fixtures. Test services are stopped; the protected SQL/file backups and local evidence remain. The tmpfs database is not durable. Read the acceptance record before restarting or rerunning one-shot seed scripts.

Known boundaries remain: shared settings need manual review; legacy fetch URLs require a recipient change or a narrow web-server mapping; custom PHP is not translated. Keep legacy modules enabled during setup to protect their declarative-schema tables. Cutover disables individual source feeds.
