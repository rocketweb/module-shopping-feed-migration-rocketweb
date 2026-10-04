# Legacy-first migration to published Mage-OS Shopping Feed v1.2.0

Completed locally on October 3, 2026. The requested sequence was exercised on a fresh Magento Open Source 2.4.8 database: install the original Rocket Web modules, generate complete legacy feeds, then install the migration extension and the latest published destination, import, and generate complete destination feeds. Existing stores and the original source checkouts were untouched.

The simple-product Generic sample matches byte-for-byte. The complex samples preserve prices, sale prices, titles, descriptions, links, images, categories and shipping weights after the importer correction below. Every remaining difference is enumerated and checked against the fixture and released implementation. Local Inventory row selection and Google's schema/identifier behavior differ; exact legacy equivalence is not claimed for those feeds.

## Exact installation

| Component | Verified version |
| --- | --- |
| Magento Open Source | 2.4.8, fresh `setup:install` with no destination or importer code/schema |
| PHP / Symfony Console | 8.4.24 / 6.4.47 |
| MySQL / OpenSearch | 8.4 / 2.19.6, separate loopback-only disposable containers |
| Rocket Web base / Google Shopping / Promotions | 2.3.4 / 2.3.4 / 2.3.4 |
| Rocket Web Local Inventory | 2.3.2 |
| Destination | Published `mage-os/module-shopping-feed:v1.2.0`, commit `4b168b8ddaca7c72572710948db9ecd320a85c98` |
| Importer | This checkout, Composer path package mirrored into `vendor/`; corrected planner included |

Live Packagist metadata and [GitHub's latest release](https://github.com/mage-os-lab/module-shopping-feed/releases/tag/v1.2.0) agreed on v1.2.0. Composer downloaded its archive from the public package repository. Installation made **two installs, zero updates and zero removals**; the existing platform dependencies remained locked. All **675 installed destination files** match the published archive. No destination source changes or discovery-branch changes were applied. The importer is still a local package, not a published release.

The original legacy CLI failed during full generation because `Feed::beforeSave()` passed null to `strtr()`. The existing opt-in compatibility patches were applied only to the disposable copies, with an additional tested string-only cleanup in `Model/Feed.php`. The baseline therefore uses the documented compatibility-patched original code. The original inventory XML remains unchanged; generation ran in production mode because that legacy XML does not pass developer-mode schema validation.

## Sequence and fixture

The first five complete legacy files were generated and retained while both destination and importer classes and tables were absent. A protected `before-destination-install.sql` backup records that stage. The catalog contains nine products: a sale-priced simple item with a Unicode/quoted title, an out-of-stock simple item, two configurable children and their parent, two component products, a grouped parent and a bundle parent. Products have synthetic images and category mappings.

Composer then installed both packages. `setup:upgrade` and `setup:di:compile` passed. Each of the first five feeds went through native CLI preview and token-confirmed import. Every source snapshot remained unchanged by import and destination generation. Imported targets started disabled, with no schedules or uploads. Full generation was explicitly invoked by feed ID, without the CLI test-SKU argument. Completion status and actual file existence were checked, so a zero CLI exit alone could not count as success.

The first comparison exposed lost shipping-weight units. After fixing the importer, the disposable database was restored from `before-import.sql`, the corrected importer was reinstalled through Composer, and all five native imports and full generations were repeated. No imported record was silently rewritten. Two additional source samples were then generated before their own imports: a Google feed with explicit `kg` parameters and a simple-products-only Generic feed. These output fixtures have no configured schedules or uploads; activation and transfer acceptance were outside this run. All seven final encrypted receipts were readable, and both modules' queues, schedules and upload tables were empty.

## Actual output

| Source ID / sample | Legacy rows / columns | Destination rows / columns | Result |
| --- | --- | --- | --- |
| 801: Generic defaults, complex catalog | 9 / 17 | 9 / 17 | All values match except two corrected variant availability values |
| 802: Google Shopping defaults | 9 / 21 | 9 / 29 | Same products; reviewed variant availability, identifier and column changes |
| 803: Local Inventory, initial assignment state | 8 / 7 | 6 / 7 | Common row values match; source-assignment and parent handling differ |
| 804: Generic custom map | 9 / 7 | 9 / 7 | All values match except two corrected variant availability values |
| 805: Google custom map | 9 / 7 | 9 / 7 | Same field values except variant availability; ID column moves |
| 806: Google with explicit weight units | 9 / 21 | 9 / 29 | Every row has a valid numeric `kg` weight; reviewed Google changes remain |
| 807: Simple-product Generic sample | 4 / 17 | 4 / 17 | **Byte-identical** |

Download the [original Generic sample](evidence/2026-10-03-published-release/legacy-807.tsv), [migrated Generic sample](evidence/2026-10-03-published-release/destination-807.tsv), [original configured Google sample](evidence/2026-10-03-published-release/legacy-806.tsv), and [migrated configured Google sample](evidence/2026-10-03-published-release/destination-806.tsv).

The [complete comparison](evidence/2026-10-03-published-release/comparison.json) includes every changed field, added/missing row, header and file hash. It matches rows by product ID and source code and preserves header order separately. Price expectations also come from the seeded catalog, so matching two erroneous generators would not pass. The verifier checks sale price `19.95 USD`, regular and complex-product prices, exact Unicode/quoted title, stock status, row widths and explicit weight units.

### Explained differences

- **Variant availability:** two children change from `out_of_stock` to `in_stock`. Their MSI source records contain quantity 25/status 1, and Magento's own `IsProductSalableInterface` returns true for both children and the configurable parent. The destination result matches the catalog's salability.
- **Local Inventory:** the initial out-of-stock fixture, grouped parent and bundle parent have no MSI source item. The destination omits those rows. It adds the configurable parent using its children's source data because the saved mode explicitly requests parents and children. The five common rows match in all seven fields. A separate probe assigned the out-of-stock product to the default source with status 0/quantity 0: both feeds then contain exactly the same `out_of_stock`, `0`, `15.00 USD` row. Counts become eight legacy and seven destination rows. These probe files are retained separately.
- **Google identifiers:** `identifier_exists` changes from `FALSE` to blank on the nine fixture rows. The released mapper requires explicit `no_identifiers` configuration and no supplied identifiers before outputting `FALSE`; missing catalog values alone no longer imply it. The fixture maps SKU into `mpn`.
- **Google columns:** `promotions_id` becomes `promotion_id`; eight variant columns are added when `item_group_id` exists: `item_group_title`, `variant_option`, `color`, `size`, `material`, `pattern`, `gender`, `age_group`. Header order changes. The custom Google map does not contain `item_group_id`, so no variant columns are added.
- **Inherited source defaults:** null weight parameters on legacy complex parents already produce a numeric weight without a unit. The corrected importer preserves that existing result. Sample 806 explicitly configures `kg` in the source and confirms that every migrated row retains a complete unit. Migration does not repair every pre-existing feed configuration problem.

## Fixes and verification

1. **Importer:** blanket null-to-empty conversion erased the simple mapper's default `kg` unit. Normalization is now restricted to the URL directive for compatibility with older destination URL mappers. Other null values preserve their meaning. Two regression cases, covering the product and replacement maps, failed before the correction; repeated native output confirms the units are restored.
2. **Legacy compatibility patch:** string cleanup during feed saves now touches only strings. A strict deprecation regression failed on the original `strtr(null, ...)` call before the patch and passes with null and numeric values preserved. The combined patch applies to a fresh original copy, passes checksum verification and reverses to the original hashes.

Results:

- Full importer suite: **63 tests, 149 assertions**, no skips, using PHPUnit 10.5.65 and a separate disposable integration database.
- Legacy compatibility suite: **24 tests, 58 assertions**. PHPUnit 10 reports one configuration deprecation because the compatibility config retains PHPUnit 9's deprecation-conversion option; the new null-save regression uses an explicit strict error handler.
- Syntax/schema validation: **26 PHP/template files, seven XML documents**.
- Output verification: **77 assertions**, zero unexplained differences.
- Source and destination generation used the native `rocketshoppingfeed:generate <id>` and `mage-os:shopping-feed:generate <id>` commands.

Recheck the retained output without Magento:

```sh
python3 docs/evidence/2026-10-03-published-release/verify-output.py
```

## Retained environment and limits

The disposable application, runtime probes, logs and protected backups are under `/private/tmp/rocketweb-migration-release-20261003`. SQL backups and the matching configuration/encryption-key archive are private local artifacts, not committed evidence. The two containers created for this acceptance are stopped and removed after backup. Existing Docker services were left running.

This establishes local migration and generated-file behavior on Magento Open Source 2.4.8 with the actual v1.2.0 release. The original first five baseline files remain unchanged; the assigned-zero-stock probe and two later configured samples are identified separately. Browser workflows, live recipient ingestion, cron cutover, transfers, promotions content and other platform versions were not repeated in this run. Prices, catalog records, images and URLs are synthetic. No push, publication, deployment or existing-store migration was performed.
