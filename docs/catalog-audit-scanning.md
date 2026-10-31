# Stages 60–61: Single-product audits and bounded catalog scanning

## What these stages do

Stage 60 combines the read-only product reader, Stage 57 issue detector,
Stage 58 completeness score and Stage 59 source hash into one product audit.
Results are **persisted in the existing `wp_detit_product_index` table** using
`ProductIndexRepository`.

Stage 61 adds controlled batches so audits are not performed on an entire
store in one request. Both services are internal PHP application services for
now; Stage 62 will design the merchant-facing dashboard.

## Critical distinction

- **Read:** WooCommerce product name, descriptions, categories, images,
  tags, attributes and other existing catalog data.
- **Write:** DetIt's own `detit_product_index` row: `content_health`,
  `issue_flags`, `source_hash`, `last_scanned_at`.
- **Never writes:** WooCommerce product fields, meta, categories, tags or
  images. `last_optimized_at` remains unchanged.
- **Never calls:** Gemini/AI, asynchronous jobs, generation/apply/undo APIs.
- **Authorization:** Signed-in operator must have `manage_woocommerce`
  and permission to edit each specific product.

## Single-product audit

`(new DetIt\Catalog\ProductCatalogAuditor())->audit(46)`

Returns an array on success, `WP_Error` on failure.

The `status` describes the persisted audit:
- `created`: the product had no DetIt index record;
- `updated`: product content, issue flags or computed score differed;
- `unchanged`: same source hash, issues and score already stored.

Each successful scan refreshes the `last_scanned_at` timestamp, including
unchanged scans. A `100` score measures **catalog completeness only**, not
SEO rankings, persuasive writing or regulatory accuracy.

For now, issue flags are stored as the complete structured issue list, and
the numeric health score is stored in its existing column. The scoring rules
version is returned in `health.rules_version` but not yet in a separate DB
column. Stage 63 should address versioned persistence and migration
requirements if scoring policy becomes configurable.

## Batch scanner

`(new DetIt\Catalog\BatchCatalogAuditor())->scan(10, 0)`

- Scans at most **25** parent products per call (default 10).
- Uses ascending product IDs and a `next_after_id` cursor; pass that cursor
  to the next call when `has_more` is true.
- Includes publish, private, draft, pending and future products; excludes
  product variations and trash.
- A product-level failure is returned as a per-item error code and does not
  abort other items. No automatic retries or scheduled queue yet.
- Works with explicit IDs too:
  `(new DetIt\Catalog\BatchCatalogAuditor())->scanIds([46, 47])`.
  Duplicate IDs are scanned once, input size is capped at 25.

The cursor-based pagination is best-effort when products are added or removed
during a multi-request scan. Full cross-request snapshots and queued scan
lifecycle tracking are beyond Stage 61.

## LocalWP test

**Before testing**, have your existing local plugin changes backed up and
switch to `feature/stages-60-61-catalog-audit-batches`.

From WordPress `app/public`:

```text
wp eval-file wp-content/plugins/detit/tools/stages60-61-smoke.php
```

The script requires a local administrator who can edit product #46. It
temporarily impersonates that administrator within WP-CLI only, restores
the original current user afterward, and prints `RESULT: PASS` on success.

**Side effects:** It writes/refreshes index rows for product #46 and up to
four catalog products from the two test pages. It deliberately does not
delete existing index rows, and does not touch WooCommerce product content.
The script also checks that existing `last_optimized_at` remains unchanged
for product #46.

After the audit smoke succeeds, regression-check the existing Generate →
Preview → Apply → Refresh → Undo flow on a disposable product.

## Milestone boundary

Keep this feature in a draft pull request targeting `develop`. Never merge
to `main` without a separate release decision. Image observation Stage
54–55 remains isolated in its own draft PR.
