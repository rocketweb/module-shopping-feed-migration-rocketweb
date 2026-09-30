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

See [the acceptance record](ACCEPTANCE-2026-09-30.md) for exact versions, evidence paths, fixture scope, and limits.

## Important compatibility finding

Unmodified Rocket Web 2.3.4 does not load on the tested Symfony 7.4 stack: both legacy CLI `execute()` methods lack the required `int` return type. Only the disposable legacy copies were adjusted so acceptance could continue. Full default generation also fails because legacy `AdditionalImageLink` accesses an undefined `$feed`; that mapper was not patched. This module distributes neither fix. Approve a real compatibility path before claiming production readiness.

## Separate main-module change

The discovery notice is implemented in `/private/tmp/shopping-feed-migration-discovery-20260930`, branch `feat/rocketweb-migration-discovery`, based on `55ee46717406c7e2166ed75e76d27724e9a34486`. It is committed locally as `c137c44`. Its patch is `/private/tmp/shopping-feed-migration-discovery-20260930.patch`.

The main-module suite passed with installed PHPUnit 12: **651 tests, 1,766 assertions**. XML and consolidation checks passed. Browser acceptance covered authorization, dismissal, and installed/absent migration-module states. See [core integration](CORE-INTEGRATION.md).

The primary checkout `/Users/matt/code/module-shopping-feed` was not edited by this continuation. Unrelated edits appeared there during the work in `Controller/Adminhtml/Feed/Builder.php`, `Model/Promotions/Provider/Map.php`, and their tests, plus `docs/reviews/2026-09-30-security-review.md`. Preserve that concurrent work and recheck status before later integration. Runtime acceptance used the copied baseline identified above, not those later edits.

## Next work

1. Review the importer fixes and resolve the two legacy compatibility defects. Review the variant-stock and Local Inventory row-count changes before accepting output.
2. Repeat installation/removal on the intended platform, including Magento Open Source if supported. Mage-OS 3.5 with local Composer path repositories passed; published package discovery remains untested.
3. Run representative store and external-recipient acceptance before cutover. Synthetic complex-product, scope, mapping, and SFTP checks are documented, with explicit limits.
4. Review/integrate the separate main-module notice. Publish its installation target before release; only then add Composer `suggest`.
5. Local commits are authorized. Obtain explicit authorization before pushing or publishing a tested release. Verify package registration/discovery independently.

The disposable runtime is `/private/tmp/rocketweb-migration-acceptance-20260930`. The final Composer-installed destination is v1.1.0. Test services are stopped; the protected SQL/file backups and local evidence remain. The tmpfs database is not durable. Read the acceptance record before restarting or rerunning one-shot seed scripts.

Known boundaries remain: shared settings need manual review; legacy fetch URLs require a recipient change or a narrow web-server mapping; custom PHP is not translated. Keep legacy modules enabled during setup to protect their declarative-schema tables. Cutover disables individual source feeds.
