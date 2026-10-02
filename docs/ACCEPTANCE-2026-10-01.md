# Replacement-map validation acceptance

Local continuation of the [September 30 acceptance](ACCEPTANCE-2026-09-30.md). The environment remains disposable Mage-OS 3.5.0, PHP 8.4.24, and destination v1.1.0. No existing store or external recipient was changed.

## Defect and correction

The planner checked destination directives only in `columns_product_columns`. It accepted unavailable directives and malformed rows in `filters_map_replace_empty_columns`. The destination evaluates those rules when an output value is empty, so an import could pass preview and fail during generation. Missing column names were also accepted in the primary map.

Both maps now validate row structure and directive availability before import. Errors name the affected setting. Empty replacement maps, static fallbacks without an attribute, registered directives, rule order, and original source records are preserved. Existing imports are not rewritten.

## Verification

- The previous planner produced 14 failures with the new regression checks: ten missing rejection failures and four missing setting-name diagnostics. The fixed full suite passed **59 tests, 143 assertions**, using PHPUnit 9.6.37 and the prefixed disposable MariaDB integration tables.
- `php dev/validate.php /Users/matt/code/mageos-latest` validated 25 PHP/template files and seven XML documents. Strict Composer metadata validation passed. Focused PSR-12 checks on the three changed PHP files passed with warnings excluded.
- Native migration CLI preview rejected an unavailable replacement directive and a malformed row with exit code 1. Each error identified the replacement setting. Source records were unchanged; neither request created a receipt or destination feed.
- A valid synthetic Generic import, source 210 / target 17, retained its replacement rules and original source records. Native destination test generation for `migration-configurable` exited 0 and emitted the parent plus two variants. All three rows contained the expected static brand fallback and registered-directive description fallback. The target remained disabled with no schedules or upload rows.

Credential-free results and the tested planner hash are in [the evidence file](evidence/2026-10-01-replacement-maps.json). Local detailed evidence is under `/private/tmp/rocketweb-migration-acceptance-20260930`: `map-validation-before.log`, `map-validation-after.log`, `replacement-map-result.json`, `replacement-map-acceptance.log`, and `replacement-native-generation.log`. The guarded one-shot runtime probe is `verify-replacement-maps.php`.

The new fixture is ephemeral. The protected September 30 backup remains the recovery checkpoint with sixteen receipts; it does not include source 210 or target 17. Restore that backup before rerunning the probe. Stop the disposable database after verification.

Existing-store acceptance, other supported platforms, recipient cutover, and published Composer discovery remain unverified. The stock, row-count, URL, and Google-column differences documented in the earlier record still require review.
