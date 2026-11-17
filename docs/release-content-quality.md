# DetIt Free 2.0 — Product Copy Quality Contract

## What changed, and why

Previously, DetIt sent product facts and a template to the AI provider without strong field-by-field writing requirements. The result could be valid JSON but contain short, generic product text.

DetIt now includes customer-focused, natural search-language guidance and an explicit output budget, with these **targets**:

| Field | Guidance |
| --- | --- |
| Product title | Specific, accurate product name with natural searchable terms |
| Short description | About 25–50 words, prioritizing true product identity and benefit |
| Long description, detailed facts | Aim around 200–350 **useful** words, with meaningful H3 headings, paragraphs, relevant features and product context |
| Long description, sparse facts | Concise, accurate copy, usually 80–160 words when possible; do not fabricate details to fill space |
| SEO meta description | Aim around 135–155 characters; **preview only** in V2.0 |
| Tags | Specific, nonduplicated tags grounded in recorded facts |

These are editorial guidelines, **not search engine ranking guarantees** or an arbitrary universal SEO minimum. Google says there is no preferred word count for rankings and recommends accurate, people-first content.

## Fact-safety policy

- Existing product name, descriptions, categories, tags, attributes and supplied product details are primary evidence.
- Do not invent dimensions, materials, ingredients, compatibility, warranties, certifications, prices, delivery, stock or performance.
- Product details are often insufficient for long copy. DetIt must not manufacture details to meet a length target.
- Quality is improved by completing the product's actual attributes and existing descriptions, rather than by writing longer prompts from a bare title.
- Brand Profile continues to guide language, tone, writing style and preferences.
- The existing manual Preview → selected Apply → Undo safety gate remains mandatory.

## Bounded quality gate

For fact-rich products (35+ words of existing product description material, 3+ populated attributes, or 16+ words plus a populated attribute):

- Short description under 20 words is rejected as too thin.
- Long description under 150 words or without a meaningful `<h3>` subheading is rejected.
- A failing result is given **one** additional AI generation attempt using the established repair mechanism; never unlimited retries.
- If it still fails, DetIt returns an error rather than silently offering obviously underdeveloped copy.
- Sparse fact sheets **do not have a hard word minimum**, because enforcing one encourages invented claims.

The checks catch obvious short/flat output. They cannot verify every factual claim or guarantee readability or ranking; merchants must still review generated content before applying it.

One additional request may consume provider credits or API quota. The default maximum output token setting is 2,800 for long descriptions and 1,000 for other selected fields. These are maximum budgets, not minimum paid usage.

## WooCommerce markup and safe preview

For a full description, the prompt requests standard WooCommerce HTML with paragraphs, H3 subheadings, feature lists, and emphasis. DetIt's browser preview reconstructs approved formatting with DOM node creation and **never inserts raw model HTML** via `innerHTML`. The existing ProductWriter still sanitizes approved HTML through `wp_kses_post` before saving.

## SEO metadata limitation

V2.0 generates an SEO meta description for **review only**. It currently does **not** write that string to Yoast, Rank Math, or any other SEO plugin. Automatically applying SEO plugin metadata would require separate integration, snapshots, undo safety and compatibility tests. Do not describe it as already working.

The generated title, short description and long description can still be written in SEO-aware, natural language without the external SEO meta field being saved.

## How to test

From the WordPress `app/public` terminal, after pulling the release branch:

```text
wp eval-file wp-content/plugins/detit/tools/release-content-quality-smoke.php
```

The deterministic smoke test sends **no AI requests** and writes **no products or settings**.

Then on an expendable WooCommerce product with several *verified* attributes and a meaningful existing description:

1. Select **Description**, **Short description** and optionally **Meta description**.
2. Generate **once** (may use a second AI request automatically for quality repair).
3. Confirm the long description has genuine sections, natural terminology, and does not invent claims.
4. Confirm the short description is informative, and the SEO meta is shown as *Preview only*.
5. Apply only product content, refresh, and verify Undo restores it.
6. On a product with almost no facts, verify the generator does not invent details to reach a word count.

The live test determines whether the upgraded instructions produce acceptable real-world content; automated smoke alone is not proof of writing quality.
