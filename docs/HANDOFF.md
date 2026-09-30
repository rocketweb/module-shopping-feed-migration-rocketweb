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

See [the acceptance record](ACCEPTANCE-2026-09-30.md) for exact versions, evidence paths, fixture scope, and limits.

## Important compatibility finding

Unmodified Rocket Web 2.3.4 does not load on the tested Symfony 7.4 stack: both legacy CLI `execute()` methods lack the required `int` return type. Only the disposable legacy copies were adjusted so acceptance could continue. This module does not distribute that adjustment. Approve a real compatibility path before claiming production readiness.

## Separate main-module change

The discovery notice is implemented in `/private/tmp/shopping-feed-migration-discovery-20260930`, branch `feat/rocketweb-migration-discovery`, based on `55ee46717406c7e2166ed75e76d27724e9a34486`. It is committed locally as `c137c44`. Its patch is `/private/tmp/shopping-feed-migration-discovery-20260930.patch`.

The main-module suite passed with installed PHPUnit 12: **651 tests, 1,766 assertions**. XML and consolidation checks passed. Browser acceptance covered authorization, dismissal, and installed/absent migration-module states. See [core integration](CORE-INTEGRATION.md).

The primary checkout `/Users/matt/code/module-shopping-feed` was not edited by this continuation. Unrelated edits appeared there during the work in `Controller/Adminhtml/Feed/Builder.php`, `Model/Promotions/Provider/Map.php`, and their tests, plus `docs/reviews/2026-09-30-security-review.md`. Preserve that concurrent work and recheck status before later integration. Runtime acceptance used the copied baseline identified above, not those later edits.

## Next work

1. Review the two fixes and the legacy compatibility decision.
2. Test Composer installation/removal and the exact supported platform. The runtime tests used copied `app/code` modules, not Composer installation.
3. Expand synthetic acceptance to complex products, full/default maps, multiple store scopes, category and inventory-source mappings, and SFTP. Run representative store acceptance before cutover.
4. Review/integrate the separate main-module notice. Publish its installation target before release; only then add Composer `suggest`.
5. Local commits are authorized. Obtain explicit authorization before pushing or publishing a tested release. Verify package registration/discovery independently.

The disposable runtime is `/private/tmp/rocketweb-migration-acceptance-20260930`. The final copied destination is v1.1.0. Test services are stopped; the protected SQL/file backups and local evidence remain. The tmpfs database is not durable. Read the acceptance record before restarting or rerunning one-shot seed scripts.

Known boundaries remain: shared settings need manual review; legacy fetch URLs require a recipient change or a narrow web-server mapping; custom PHP is not translated. Keep legacy modules enabled during setup to protect their declarative-schema tables. Cutover disables individual source feeds.
