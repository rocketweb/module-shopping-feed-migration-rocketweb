# Optional discovery notice in the main module

This repository contains the migration engine and the installed migration UI. It cannot display a notice before its own code is installed.

A small, separate change in `mage-os/module-shopping-feed` should provide discovery:

1. On the feed management screen only, check whether `RocketWeb_ShoppingFeeds` is registered, or whether the prefixed `rw_shoppingfeeds_feed` table exists. Use Magento's table-name resolver; do not load legacy PHP classes.
2. If the migration module is absent and legacy data is detected, show a dismissible notice linking to these installation instructions. Distinguish remaining tables from a complete installed legacy module.
3. If this module is installed, link authorized users to `rocketweb_feed_migration/manage/index`.
4. Once published, add this package to Composer `suggest`, not `require` or `replace`.

Do not run Composer or download executable PHP from an Admin request. A store's normal developer/deployment process installs the optional package. The installed migration UI then handles preview and explicit Apply.

The main module requires no importer classes, migration tables, serialized-value parser, or credential handling.

## Prepared local change

The notice is implemented separately in `/private/tmp/shopping-feed-migration-discovery-20260930`, branch `feat/rocketweb-migration-discovery`, based on main-module commit `55ee46717406c7e2166ed75e76d27724e9a34486`. The primary checkout was not changed. The isolated change is committed locally as `c137c44`.

It adds a block, template, dismiss handler, feed-grid layout entry, and five focused tests. It checks registration without loading legacy classes, resolves prefixed table names, distinguishes remaining tables, and only links to the installed migration tool when the user has its ACL. Browser checks covered the installed/authorized, installed/unauthorized, module-absent/legacy-present, and dismiss states. Remaining-table and clean-install states are unit-tested. The consolidation validator permits only the exact discovery literals in these two PHP files; a negative probe verified it still rejects legacy PHP class references.

The full main-module suite passed with the installed PHPUnit 12 runner: 651 tests, 1,766 assertions. Five focused tests also passed under PHPUnit 9: 27 assertions. `composer suggest` remains pending publication, as specified above. The installation link points to this repository's README and must be available before this notice is released.

## Refreshed on 2026-10-03

The same six-file discovery change is now prepared on the main module's 1.2.0 candidate `ca9030c0ad843fa8d1eea5c638071b2734b579a7`. It applied cleanly in `/private/tmp/shopping-feed-migration-discovery-20261003`, branch `feat/rocketweb-migration-discovery-20261003`, and is committed locally as `93bcbe68c593202462462506b8b6c5ff72675fb0`. The reviewable patch is `/private/tmp/shopping-feed-migration-discovery-20261003.patch`. The primary checkout remains untouched.

On the Magento Open Source 2.4.8 framework, the full main-module suite passed **814 tests, 1,906 assertions**. Consolidation validation passed for 26 XML files and eight feed types. The installed candidate compiled with the importer and legacy modules. Native checks built the Admin ACL and detected the legacy module while the importer was disabled and Composer-removed. The earlier browser checks remain evidence for their September 30 baseline; they were not repeated for this candidate. See [the platform acceptance record](ACCEPTANCE-2026-10-03.md).

This is still a separate local integration branch. The notice's installation target must be published before release, and Composer `suggest` remains deferred until then.

## Included in the local 1.2.1 candidate

The existing six-file notice is now integrated into the isolated main-module `release/1.2.1` worktree, based on released v1.2.0 (`4b168b8ddaca7c72572710948db9ecd320a85c98`). The candidate includes the companion in Composer `suggest`, with validation requiring that it remain optional. The older prepared branches above remain historical evidence.

The main-module release draft and wiki describe companion 1.0.0, staging review, explicit import/activation, and recipient URL cutover. The installation target and companion Composer registration must be published and verified before the coordinated launch is announced. No source, wiki, release, or package publication occurred as part of this preparation.
