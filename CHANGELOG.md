# Changelog

## 1.0.0 - 2026-10-04

First stable release, accompanying Mage-OS Shopping Feed 1.2.1.

- Separately installable Rocket Web feed migration, with Admin and CLI list/preview/import/activation/rollback workflows.
- Inherited legacy defaults and supported mappings are reviewed before import; malformed maps, unavailable directives, unsafe serialized configuration, unreadable credentials, stale previews, and duplicate imports are rejected.
- Preserve non-URL null mapper defaults, including shipping-weight units; normalize only null URL parameters needed by older destination mappers.
- Imported feeds remain disabled, with schedules and uploads held in encrypted receipts until explicit activation.
- Transactional writes, migration and row locks, source/destination fingerprints, and conflict-safe rollback preserve source records and reject destructive rollback of edits.
- Dedicated Admin ACL, POST/form-key checks, isolated output subdirectories, receipt retention, and staging/cutover documentation.
- Opt-in compatibility patches for the tested legacy base and Google Shopping packages, with separate regression and reversal instructions. They are not installed automatically.
- CI checks for metadata/syntax checks on PHP 8.1 through 8.5 and importer/database checks on Magento 2.4.8-p5 and Mage-OS 3.5.0.
- Add the reproduced PHP 8.5 boolean-cast correction to the opt-in legacy base patch; update checksums and verify exact patch reversal.
- Dual data-provider metadata and mock declarations for PHPUnit 9, 10, and 12. The local database test port can be selected without changing the fixed loopback host, disposable database name, or table prefix.
