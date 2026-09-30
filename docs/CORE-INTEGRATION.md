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
