# Magento Open Source 2.4.8 migration acceptance

The importer passed a separate disposable Magento Open Source 2.4.8 check with the destination 1.2.0 candidate. This closes one additional platform check. It does not establish acceptance for an existing store or recipient.

## Exact scope

| Component | Tested value |
| --- | --- |
| Platform | Magento Open Source 2.4.8, framework 103.0.8 |
| Runtime | PHP 8.4.24, Symfony Console 6.4.47, MySQL 8.4, OpenSearch 2.19.6 |
| Importer runtime | `95691d21d4b175cf62d92fce846b0cd35a09fc5e`; runtime code unchanged by this continuation |
| Destination candidate | `93bcbe68c593202462462506b8b6c5ff72675fb0`, adding discovery to base `ca9030c0ad843fa8d1eea5c638071b2734b579a7` |
| Legacy | Base, Google Shopping, and Promotions 2.3.4; Local Inventory 2.3.2; existing opt-in base/Google patches verified |
| Mode | Production after successful schema upgrade and DI compilation |

The clone reused retained synthetic Magento dependencies and a database backup. It has its own MySQL and OpenSearch containers, loopback ports, configuration, encryption-key copy, generated code, and media. The existing Magento test stores, primary main-module checkout, original legacy checkouts, and `mageos-latest` were not changed. The destination's local path repository labels the pinned candidate 1.2.0; this is not a claim about a published package.

## Observed results

- Composer installed exactly two packages: destination and importer. Original locked dependencies were unchanged. Installation used local path repositories and retained lock metadata with networking disabled.
- Schema upgrade and DI compilation passed with all legacy modules enabled. The native Admin ACL built successfully with and without the importer.
- Importer PHPUnit 10.5.65: **60 tests, 145 assertions**. Main-module suite including discovery: **814 tests, 1,906 assertions**. Importer validation checked 26 PHP/template files and seven XML documents; main-module consolidation checked 26 XML files and eight feed types.
- Native CLI preview/import created five synthetic migrations, preserving source records and holding schedules/uploads. Default column maps for Generic, Google Shopping, and Local Inventory each generated one row in both legacy and destination test mode using a synthetic product with an image. Destination feeds remained disabled after generation. This checks successful generation, not output equivalence.
- Rollback removed its unchanged test target and retained its receipt. Activation restored a held schedule and decryptable synthetic SFTP credentials. No worker or transfer ran; the activated target was returned to an inactive state before lifecycle checks.
- The sixteen pre-existing destination feed rows remained unchanged.
- Disable, Composer removal, and reinstall, each followed by `setup:upgrade`, preserved all five receipts byte-for-byte. Every receipt remained decryptable. Legacy records remained intact. Discovery returned the legacy-module state while the importer was absent.
- A full SQL backup restored into a separate disposable schema. All 15 migration-related source/destination/receipt tables matched, and all five restored receipts decrypted. The configuration/encryption-key archive restored both files byte-for-byte. Backup artifacts have mode `0600`.

## Findings and limits

The development validator previously registered the module again after loading a Magento installation that already contained it. That caused a duplicate-registration fatal. A subprocess regression was observed failing; the validator now keeps the existing registration and validates the checkout. Both installed-copy and uninstalled-copy checks pass. No importer runtime change was needed for this Magento profile.

Legacy generation initially failed in developer mode because the inventory XML omits its own required encoding element, as documented in the original handoff. Production mode avoids that legacy schema-validation path. The importer continues using its compatibility reader; no original XML or source checkout was edited. An initial imageless catalog fixture was correctly skipped by the legacy required-image filter; the final test supplied a synthetic image and retained the default filter.

Authenticated browser workflows, recipient delivery, promotions output, complex-product comparisons, and performance were not repeated on this profile. Their earlier Mage-OS results keep their original scope. Prior stock, row-count, URL, and column differences are not resolved by these one-product generation checks. Other Magento versions and real-store customizations still need their own migration acceptance. Composer discovery from a published package and release actions remain pending.

## Evidence and recovery

Credential-free results are in [the evidence file](evidence/2026-10-03-magento-248.json). Private application files, native CLI logs, probes, package locks, and protected recovery artifacts are retained under `/private/tmp/rocketweb-migration-magento-248-20261003/`.

The recovery artifacts are `evidence/after-acceptance.sql` and `evidence/configuration-and-key.tar.gz`. The latter contains the matching encryption key. Treat both as private. `acceptance.php` seeds one-shot fixtures; restore `evidence/before-import.sql` before rerunning it. The lifecycle and restore probes use only the guarded `migration_acceptance` database at `127.0.0.1:13389` and a separate restore schema. These artifacts are separate from the September 30 Mage-OS backup with sixteen receipts.
