# DetIt Free 2.0 — WordPress Plugin Check remediation

**Source:** Plugin Check output uploaded from Area 51 on 2026-11-06 (185 findings: 115 ERROR, 70 WARNING). This document describes changes on `feature/v2-free-release-preparation`, not an approved WordPress.org release.

## Why we must run Plugin Check again

PHP syntax and successful ZIP installation show that a plugin runs. They do not prove it follows all WordPress.org review guidelines. This remediation is not considered accepted until the **freshly rebuilt ZIP** is installed and Plugin Check is rerun on the updated code.

## Groups of reported findings and remediation

1. **Translation format and localization:** Use a literal plugin text domain instead of `self::TD` or `self::TEXT_DOMAIN` in translation calls; replace the concatenated translatable sentence with one literal; add `translators:` comments for numbered placeholders. Remove manual `load_plugin_textdomain()`, because directory-hosted translations are loaded by WordPress.
2. **SQL and dynamic table names:** Bind custom table names with the WPDB `%i` identifier placeholder (supported by WordPress 6.2+). Use `%d` / `%s` for product IDs, search terms, and pagination. Dashboard filters and sort SQL are still built from hard-coded allowlists, never raw request fragments. Read-only live auditing is intentionally queried without caching, as product data can change outside DetIt.
3. **Security:** Keep permission and nonce checks on all state-changing audit actions; sanitize incoming POST values before validation. Dashboard GET filter/search/sort/page parameters remain read-only, and don't need a nonce. Narrow PHPCS comments explain already-verified state or read-only operations.
4. **Output and prompts:** Escape formatted dynamic diagnostic values, correctly tag chained exceptions (not presented to visitors), and avoid heredoc syntax rejected by Plugin Check. Replace PHP's discouraged `strip_tags()` with `wp_strip_all_tags()`.
5. **Plugin metadata:** Add a `Tested up to` readme header, remove a nonexistent language-folder declaration, and prefix variables declared in the global uninstall scope. Explicit uninstall DB removal touches only fixed DetIt-owned table names.
6. **Development logging:** Logs run only behind `WP_DEBUG`. Narrow PHPCS comments explain these calls, rather than hiding every diagnostic or exposing them in the browser.

**Warnings may still be reported** where a direct database operation is intrinsic to a local custom audit table or uninstall. Such warnings require review, not automatic suppression, and any new security ERROR is a publication blocker.

## User retest instructions

From the original DetIt developer site's WordPress `app/public` folder:

```powershell
git -C wp-content\plugins\detit pull --ff-only origin feature/v2-free-release-preparation
wp eval-file wp-content/plugins/detit/tools/release-plugin-check-fixes-smoke.php
wp eval-file wp-content/plugins/detit/tools/stages62-63-smoke.php
wp eval-file wp-content/plugins/detit/tools/stage62-search-sort-smoke.php
wp eval-file wp-content/plugins/detit/tools/stage62-selected-scan-smoke.php
```

These smoke tests must report `RESULT: PASS` or equivalent. The source check is read-only and never uses AI.

Build the new ZIP from the development plugin folder:

```powershell
powershell -ExecutionPolicy Bypass -File .\wp-content\plugins\detit\tools\build-release.ps1
```

Confirm `RESULT: PASS` and note the `RELEASE_ZIP` path.

**Important:** Existing Area 51 has a prior DetIt installation. Back it up, then use the WordPress plugin upgrade/replacement flow to install the newly rebuilt ZIP over the **same plugin folder**. Do not activate two copies of DetIt and do not uninstall if you wish to retain Brand Profile, audit index or history (uninstall deletes them). If WordPress refuses replacement, stop and inspect the folder rather than manually deleting plugin data.

After installation, run **Tools → Plugin Check** on Area 51 again. Export the latest report and compare it to the 185-finding baseline.

## November 9 follow-up — repeated 24 findings on test-site

The user uploaded a new report from `test-site` generated 2026-11-09 22:36:24. It is **identical** to the prior 2026-11-06 02:20 report: 2 errors and 22 warnings. Notably, the reported concatenated ability translation at `ProductSummaryAbility.php:73` no longer exists in the branch, which strongly suggests the tested ZIP was an earlier build. Rebuilding from the latest branch and replacing the test-site plugin is required to verify this.

To remove the database analyzer error **at its source** rather than solely annotate it, `ContentHealthDashboardRepository::browse()` now uses **static SQL literals directly inside `$wpdb->prepare()`** for both count and paginated listing. Filter and search terms, numeric IDs and the sort selection are bound as data using `%s`/`%d`; table names use `%i`. The five sorting behaviors are retained using SQL `CASE WHEN` rather than interpolated SQL fragments. It is essential to rerun `stages62-63-smoke.php`, `stage62-search-sort-smoke.php`, and `stage62-selected-scan-smoke.php` locally to confirm real MySQL behavior after this query rewrite. Syntax-only CI is not sufficient.

The 22 remaining warnings are predominantly direct SQL access and no-caching notices for DetIt-owned tables, plus one intentional uninstall schema change and a read-only product editor GET. Warnings require individual review; don't introduce caching without valid invalidation or remove intentional uninstall cleanup solely to make the count smaller.

## November 9, 22:53 Plugin Check — zero errors

The official Plugin Check report from `test-site` generated **2026-11-09 22:53:25** lists **0 ERROR and 17 WARNING**. This is a reduction from 185 original findings, including clearing all 115 original errors. The remaining 17 warnings consist of:
- 2 `WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` notices at `ContentHealthDashboardRepository.php:124` and `:165`. Both queries were already safe at runtime because `$wpdb->prepare()` accepts a single argument array. The static analyzer counted each array as just one placeholder replacement, hence its misleading result. **Code corrected** by passing 11 and 20 replacement values explicitly and in order, matching the literal placeholders exactly.
- 14 `WordPress.DB.DirectDatabaseQuery.{DirectQuery,NoCaching}` warnings in custom DetIt history/audit repository reads. These are read-only, use prepared table identifiers and value placeholders, and deliberately read live tables. Caching would require explicit invalidation after every corresponding write and must not be introduced merely to silence static analysis.
- 1 `WordPress.DB.DirectDatabaseQuery.SchemaChange` warning in `uninstall.php` for `DROP TABLE IF EXISTS` on the four **DetIt-owned** audit/history tables after explicit WordPress uninstall. This is intentional; no WooCommerce product content is removed.

**Important:** The explicit placeholder rewrite has not yet been validated by a fresh official Plugin Check. The user's next steps are to pull, run both the release static smoke and the three dashboard regression suites, rebuild the ZIP, replace the test-site plugin, and rerun Plugin Check. We expect only the 15 reviewed database/uninstall warnings, but **do not claim this until the official scan confirms it**.

## November 11 — clean Plugin Check ERROR result

The latest WordPress Plugin Check export from the separate LocalWP `test-site`, generated **2026-11-11 10:55:44**, shows **0 ERROR and 15 WARNING**. This is down from the original 115 errors and 70 warnings (185 findings). Both `ReplacementsWrongNumber` warnings are now gone after changing array replacement values to explicit individual arguments in WPDB prepared statements.

**Remaining warnings — reviewed, not unconditionally waived:**

- **14** custom SQL read warnings, consisting of seven `WordPress.DB.DirectDatabaseQuery.DirectQuery` and seven `WordPress.DB.DirectDatabaseQuery.NoCaching` notices. All are in `GenerationRepository`, `ProductIndexRepository`, `RunItemRepository`, and `RunRepository`. These are DetIt-owned database tables (not the core posts or WooCommerce orders tables), and the SQL reads use WPDB prepared queries with `%i` identifiers and `%d` IDs. A caching implementation would require comprehensive invalidation on save/update/undo; an incorrect cache risks stale history and inaccurate audit state. Keep these as documented warnings for now. Revisit caching if profiling shows a real performance problem.
- **1** intentional `SchemaChange` warning in `uninstall.php`, which drops only DetIt's four owned audit/history tables after WordPress invokes plugin uninstall. This is not an activation or ordinary deactivation path. Merchant product content is untouched. Confirm the end-user uninstall/deletion disclosure is prominently clear.

**Release status:** Official Plugin Check currently shows zero errors, but this does not equal WordPress.org approval. The WordPress.org Plugin Check project explains that its checks do not replace manual review and that the Plugin repo category must normally pass; some additional warnings can remain after review. Source: https://wordpress.org/plugins/plugin-check/

**Still to verify before tagging/releasing:** development-side release smoke plus the 3 dashboard SQL regression suites after the last query change; Generate → Preview → Apply → Undo on latest built ZIP; settings persistence on replacing an existing installation; readme validator/compatibility and directory submission details. Keep PR #7 draft until acceptance.

## November 11 — upgrade persistence and WordPress 7.1.4 acceptance

The user confirms that the latest release ZIP was **replaced over the existing DetIt installation** on separate LocalWP `test-site`, **without uninstalling**, and that the Content Health data remained unchanged afterward. The screenshot after replacement shows product **Merlins Girdle (#19)** at **75/100**, audit status **Current**, four existing issues, and **last scan 2026-11-11 11:56**; all remained unchanged after ZIP replacement. A previous screenshot also confirmed the saved Brand Profile is populated and the user reports those values persisted. Therefore **same-version replacement/audit/Brand Profile persistence PASS**. This does not yet prove migrations between different plugin/database versions, nor a generation-history database record comparison.

The user separately reports **Generate → Preview → selective Apply → Undo PASS** through the latest test installation, and the four development LocalWP smoke suites **PASS**. WordPress version on `test-site` is confirmed **7.1.4**, appropriately covered by `Tested up to: 7.1`.

**Submission preparation outstanding:** Confirm the author's exact **WordPress.org username** and add a `Contributors:` readme header. Run the WordPress.org readme validator, perform a final ZIP/package/file review, verify `WP_DEBUG` behavior as appropriate, and maintain the PR as draft until explicit release approval. WordPress.org readme guideline: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/ ; submission guidance: https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

## Existing WordPress.org plugin — V1 to V2 upgrade is the release path

**Corrected release plan (verified Nov 11, 2026):** This is **not** a new plugin submission. The public listing is already live at https://wordpress.org/plugins/detit-product-content-generator-for-woocommerce/ and currently publishes **version 1.0**. The verified WordPress.org owner profile is **Franklyn Okafor / `emeka002`** (https://profiles.wordpress.org/emeka002/). The V2 `readme.txt` now identifies `Contributors: emeka002`, with `Stable tag: 2.0.0` and `Tested up to: 7.1`.

Because this is an *existing WordPress.org plugin*, the release will eventually require updating its assigned **SVN** repository, with finished release files directly inside `trunk/`, creating `tags/2.0.0/`, and aligning `trunk/readme.txt` stable tag. Do **not** submit a new plugin, send a ZIP through the new-plugin application, merge, tag or push SVN until final owner approval. References:
- https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

**Important untested path:** All successful data-retention tests so far replaced a **V2.0 ZIP over an existing V2.0 installation**, which does **not** prove a live V1.0 site can upgrade cleanly to V2.0. V1.0 used its own Google Gemini API-key settings whereas V2.0 uses the WordPress AI Client and a separately configured provider. An actual V1.0 → V2.0 upgrade may change AI-provider setup and old data/option behavior. Treat this as a major compatibility gate:

1. On a *new disposable LocalWP site*, install the current public **V1.0** from WordPress.org and activate WooCommerce.
2. Save sample V1 store settings, note AI connection configuration (never paste or upload API secrets), and record a sample WooCommerce product plus any existing DetIt data.
3. Back up the site and database; replace V1.0 with the latest V2.0 release ZIP **using the exact same canonical plugin slug/directory**, without uninstalling.
4. Confirm no fatal errors, only one active DetIt, correct version shown, product contents unchanged, Brand Profile and Content Health operational, V2 AI provider can be configured and generation works.
5. Explicitly document any V1 settings or history that are no longer used or cannot migrate. Provide an upgrade notice and safe reconfiguration steps if needed; do not claim automatic migration unless verified.
6. Validate the complete V2 readme at https://wordpress.org/plugins/developers/readme-validator/, and verify all release files/ZIP under WordPress.org packaging limits.

**Status:** The *V2 same-version replacement* and WordPress 7.1.4 acceptance are PASS; **V1→V2 compatibility is still UNTESTED**. The release PR remains draft/unmerged.

## November 11 — V1.0 to V2.0 post-upgrade verification confirmed

The owner additionally confirms that all post-upgrade checks on the disposable `detit-v1-upgrade-test` LocalWP site **passed**, following the earlier WordPress screenshot showing that its installed **DetIt V1.0** was successfully replaced by **V2.0.0** using the canonical same-slug WordPress update flow. Confirmation covers one active V2 install, normal WordPress/admin operation, existing WooCommerce product content, Brand Profile, Content Health dashboard, and a test audit. This closes the **V1→V2 basic upgrade compatibility gate**.

V1's direct Gemini API-key setting is *not* the V2 WordPress AI Client configuration, so reconfiguring a compatible provider is an intentional change documented in V2 `readme.txt`. V2 generation separately passed Generate → Preview → selective Apply → Undo in test-site. This does **not** assert that V1 legacy settings/API-key records migrate to V2.

**Remaining before publication:** run the official WordPress.org readme validator on the final readme; build and inspect the exact final ZIP; check WordPress.org SVN trunk/tag content locally before committing. SVN publishing, merging the draft PR, or tagging the public release require explicit owner authorization.

## Final regression gates

- [x] Updated ZIP installs/replaces the existing plugin without fatal errors (user-reported same-version replacement on test-site).
- [x] Brand Profile settings remain unchanged on same-version ZIP replacement (user confirmation).
- [x] Health dashboard loads, with totals, search, sort and scan regression suites PASS; actual scanned audit retained across ZIP replacement.
- [x] Stale source detection still works (Stages 62–63 smoke PASS).
- [x] Generate → Preview → selective Apply → Refresh → Undo works on a disposable product (user-reported UI PASS).
- [x] No unaddressed Plugin Check **ERROR** remains (Nov 11, 0 ERROR).
- [x] Any remaining warnings are reviewed individually for security and directory policy (15 documented).
- [x] Confirm actual WordPress version on final test-site is 7.1.4, supporting readme `Tested up to: 7.1`.
- [ ] PR #7 remains draft and unpublished until owner signs off.
