# Stages 58–59: catalog completeness and source fingerprinting

This batch introduces two **pure, read-only** catalog services built on Stage 57's
`ProductIssueDetector`. Neither service sends an AI request, writes a WooCommerce
product, or stores database records. Stage 60 will coordinate and persist audit data.

## Stage 58 — Content Health score

`DetIt\Catalog\ProductHealthScorer::score($issues)` consumes the Stage 57
issue objects and produces an integer in the range **0–100**, a rating,
a scoring-version string and itemized point deductions.

The score measures **catalog field completeness**, **not** content quality,
product truthfulness, SEO performance, search rankings, or conversion rates.

Start at 100 and deduct:

| Issue code | Deduction |
| --- | ---: |
| `missing_title` | 30 |
| `missing_featured_image` | 25 |
| `missing_description` | 20 |
| `missing_short_description` | 10 |
| `missing_categories` | 10 |
| `missing_attributes` **when severity is medium** (variable product) | 15 |

- Any `info` issue: **0 points**, including missing SKU/tags and missing
  attributes on a simple product.
- Missing image alt text is informational (0 points); the appropriateness of
  decorative vs descriptive alt text requires context.
- Each issue code is counted at most once, unknown issue codes deduct nothing,
  and final scores cannot drop below 0.
- Ratings: **excellent** 90–100, **good** 70–89,
  **needs_attention** 40–69, **critical** 0–39.
- `RULES_VERSION` changes only when meaningfully adjusting the scoring
  model; rescanning under new model versions will be handled in later stages.

Example: a complete simple product with no SKU or attributes still scores
**100**. A product missing a featured image loses **25** points.

These are product-design starting weights; validate with actual merchants
before presenting the score as an established industry standard.

## Stage 59 — Product source hash

`DetIt\Catalog\ProductSourceHasher::hash($context)` returns a **64-character
SHA-256 hex hash** of explicitly selected merchant-side catalog input fields.

Included: product ID, title, SKU, type, descriptions, category/tag identities,
attributes, weight/dimensions, featured image ID/URL/alt and gallery images.

The hash:
- is deterministic for unchanged content;
- is unaffected by reordering category/tag record lists or associative keys;
- changes on edited product text, taxonomy membership, dimensions or images;
- intentionally reflects gallery order (which affects presentation);
- excludes volatile scan times, issue lists, AI results and database records.

The hash is a **change detector**, not a cryptographic proof of correctness.
The `SOURCE_VERSION` constant captures the source projection version.
Later audits should compare both the source fingerprint and scoring model
version before deciding cached scores are up to date.

## LocalWP verification

1. Switch to `feature/stages-58-59-health-scoring-source-hash` after checking
   `git status` is clean.
2. From WordPress `app/public` run:

   `wp eval-file wp-content/plugins/detit/tools/stages58-59-smoke.php`

3. Expect `RESULT: PASS` and review the computed score, issue codes and
   source hash for test product 46 (Green Drink).
4. Regression-check Generate → Preview → Apply → Refresh → Undo.
5. No AI quota usage and no product mutations in the smoke script.
