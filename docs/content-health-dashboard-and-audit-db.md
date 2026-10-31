# Stages 62–63: Content Health dashboard and audit index finalization

## Stage 62: WooCommerce admin dashboard

Open **WooCommerce → DetIt Content Health**.

The dashboard displays WooCommerce parent products in pages of 20 with:
- Total, scanned, not-scanned and below-70 counts
- Score 0–100, human-readable audit issues and last scan time
- **Not scanned**, **Current** and **Needs rescan** status
- Filters for all/scanned/unscanned/below-70 products
- A nonce-protected **Rescan** button on each visible product
- **Scan first 10 products** and a cursor-based **Continue: next 10**
  button (after the previous request completes)

**Opening the page never changes the database.** Clicking a scan button
writes only DetIt's product index audit fields, not merchant products. The
page uses WordPress-native admin HTML and escapes all dynamic content.

The dashboard is available to logged-in users with `manage_woocommerce`.
Each visible row also checks `edit_post` permissions. POST scan operations
require a valid WordPress nonce and check permissions again.

## Scanning selected products (merchant-selected bulk audit)

The dashboard now offers **Scan selected products**, separate from automatic
catalog scanning:

1. Search or filter the product table to find products to audit.
2. Tick the checkbox beside each product you want.
3. Use the header checkbox **Select all products on this page** when useful.
4. Click **Scan selected products** to audit **only those selected IDs**.

Selections apply to the current page (up to 20 visible products), not every
product matching a search across other pages. Change pages or search terms
before selecting to avoid losing the current page's selection. The underlying
`BatchCatalogAuditor::scanIds()` enforces a server-side maximum of 25 and
checks product-level editing rights, with individual failures reported
without aborting other selected scans.

The selected-scan action requires a valid WordPress admin nonce and
`manage_woocommerce` capability. It rejects empty, malformed, negative
and excessive selections before any index writes. It **only updates DetIt's
audit index**, never product content. Successful submissions display
completed/failed counts after redirect.

The existing **Auto-scan: first 10 by ID** and **Auto-scan: next 10 by ID**
buttons remain for merchants who want to walk the catalog in product-ID
order. These automatic buttons do not use the selected checkboxes, table
search or sorting.

Run the read-only UI/input smoke test:

```
wp eval-file wp-content/plugins/detit/tools/stage62-selected-scan-smoke.php
```

Then manually try selecting two visible products, scanning them, and
verifying that the success notice reports two audited. No AI calls occur.

## Dashboard product search and sorting

The dashboard includes a **Search products** field and a **Sort by** menu:

- Search by product name (partial matches) or exact numeric product ID.
- Sort by score **low to high** or **high to low**; products without an
  audit appear after scored products in both directions.
- Sort alphabetically **A to Z** or **Z to A**, or use original product-ID order.
- Search and sorting run in the database *before* paging, so they apply to
  the full matching catalog rather than only the first 20 products.
- Active filter, search and sort options are preserved across filter tabs,
  pagination and individual/batch scan redirects.
- The **Clear search** link removes the search term while keeping your
  current filter and sort order.
- Scan success or failure redirects to an on-page feedback anchor so the
  notice is visible rather than only silently reloading.

To test search and sort without changing any records:

```
wp eval-file wp-content/plugins/detit/tools/stage62-search-sort-smoke.php
```

This focused smoke test is read-only.

Scores measure catalog **field completeness**, not search rankings, content
truthfulness or product conversion potential. A score below 70 means that
one or more important content fields are missing.

Staleness is detected for the displayed page by comparing the stored
`source_hash` to the current `ProductReader` + `ProductSourceHasher` hash,
and checking the saved scoring rules version. The dashboard does **not**
automatically recompute scores for stale rows; users intentionally rescan.

Counts and below-70 filters are based on **stored audit values**, so a
product edited since its last scan may still appear under its old score until
rescanned. A full-store audit may need multiple clicks (10 products each).

## Stage 63: Audit schema and consistency

The existing `wp_detit_product_index` table is retained. Database version
is increased to **1.6.0** and `rules_version varchar(20) NOT NULL DEFAULT ''`
is appended to the existing schema through WordPress `dbDelta`.

The single-product auditor now persists
`ProductHealthScorer::RULES_VERSION` alongside its score, issue list,
source hash and UTC last-scan timestamp. Legacy index rows with an empty
rules version will be marked for refresh, not silently treated as current.

The existing `last_optimized_at` field is explicitly preserved by scans.
The unique product index remains unchanged, avoiding duplicate audit records.
The migration does not delete prior `issue_flags`, scores, timestamps or hashes.

**Upgrade verification matters:** Some WordPress/database combinations can
fail to apply `dbDelta` schema updates. The smoke script inspects the real
table column; if it fails, do not mark Stage 63 accepted.

## LocalWP acceptance

1. In the DetIt plugin folder, ensure `git status` is clean; switch to
   `feature/stages-62-63-content-health-dashboard-audit-db`.
2. In the WordPress root (`app/public`), run:

   ```
   wp eval-file wp-content/plugins/detit/tools/stages62-63-smoke.php
   ```

3. Expect `RESULT: PASS`. This script **updates DetIt's test audit row for
   product 46** and temporarily simulates legacy/stale records, restoring
   the current correct audit afterwards. It does not mutate WooCommerce
   products or call Gemini.
4. Visit **WooCommerce → DetIt Content Health** and verify that:
   - scores, issues and scan times are visible;
   - filter tabs work;
   - **Rescan** updates product 46 and shows a success notice;
   - **Scan first 10 products** shows a batch result;
   - **Continue: next 10** appears when additional products remain.
5. On a disposable product, edit a description in WooCommerce. On refreshing
   the dashboard, its previously scanned row should say **Needs rescan**;
   clicking **Rescan** should return it to **Current**. Undo/revert that
   disposable product edit and rescan if needed.
6. Retest **Generate → Preview → Apply → Refresh → Undo** on a disposable
   WooCommerce product.

Keep this work in its draft PR until both the script and manual dashboard
acceptance checks pass. The unresolved image-safety PR #1 remains separate;
Milestone B cannot be declared fully closed until that integration is
deliberately resolved.

## Explicit limitations

- No background jobs or automatic recurring full-catalog scan.
- No public REST endpoints and no AI charges for audits.
- No catalog ranking or industry-standard SEO quality claims.
- When page filters include product rows that a user cannot edit, those
  records are hidden in the table; store-level aggregate counts require
  `manage_woocommerce`.
- Pagination is cursor-based for scans and page-based for the read-only
  dashboard; batches are best-effort while products are added or removed.
