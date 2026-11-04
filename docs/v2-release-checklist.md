# DetIt Free 2.0.0 — Release candidate checklist

**Status: NOT READY TO PUBLISH.** This is a release candidate preparation branch. A passing GitHub PHP syntax check and ZIP build check do not replace fresh-install tests, Plugin Check, privacy review, or WordPress.org approval.

## Permanent free features

1. Individual WooCommerce product content generation, preview, selective apply, safe one-step undo.
2. Saved Brand Profile, with optional one-off language/tone changes.
3. Missing product information detection.
4. Product completeness scoring, not SEO ranking.
5. Read-only/manual product catalog health audits (single, selected up to 25, sequential batches of 10), search/sort/filter, and source-change detection.

Not included: image-aware generation (checkbox disabled), automatic AI bulk rewriting, premium services, scheduling. Keep existing users' V2 Free capabilities free when 2.1–2.3 add new features.

## What the maintainer's assistant prepared in GitHub

- [x] Merged accepted Stage 62–63 work to `develop`; Stage 54–55 image PR stays separate and unmerged.
- [x] Release-preparation branch from `develop`, not `main`.
- [x] Autoload fallback so no `vendor/` directory is needed by end users.
- [x] WordPress.org format `readme.txt`, service and privacy disclosure, correct GitHub Plugin URI.
- [x] A clean, allowlist-based Windows PowerShell release ZIP builder.
- [x] CI PHP syntax and automated release ZIP checks.
- [x] Provider/model selection uses the provider configured by the site owner.
- [x] Dedicated uninstall cleanup for DetIt-only options and history tables.

## What the merchant/developer must verify locally

**Step 1: get the release branch.** In PowerShell from your existing DetIt plugin folder:

```powershell
git status
git fetch origin
git switch -c feature/v2-free-release-preparation --track origin/feature/v2-free-release-preparation
git branch --show-current
```

If Git reports the branch already exists, use `git switch feature/v2-free-release-preparation` then `git pull --ff-only origin feature/v2-free-release-preparation`.

**Step 2: check versions and compatibility.** Confirm WordPress version, PHP >=8.1, WooCommerce active, a compatible WP AI Client available (`function_exists('wp_ai_client_prompt')`) and a configured AI provider. For modern WordPress 7.0+ the AI Client API is in core; older setups may need a compatible separate client.

**Step 3: rerun all non-destructive WP-CLI smoke tests.** From `app/public`:

```text
wp eval-file wp-content/plugins/detit/tools/brand-profile-generator-defaults-smoke.php
wp eval-file wp-content/plugins/detit/tools/stage56-ability-smoke.php
wp eval-file wp-content/plugins/detit/tools/stage57-issues-smoke.php
wp eval-file wp-content/plugins/detit/tools/stages58-59-smoke.php
wp eval-file wp-content/plugins/detit/tools/stages60-61-smoke.php
wp eval-file wp-content/plugins/detit/tools/stages62-63-smoke.php
wp eval-file wp-content/plugins/detit/tools/stage62-search-sort-smoke.php
wp eval-file wp-content/plugins/detit/tools/stage62-selected-scan-smoke.php
```

Each should report `RESULT: PASS` where applicable. Stage-specific scripts may assume a particular disposable product exists; investigate before interpreting an incomplete result as a code failure.

**If generation returns HTTP 429 (rate limiting):** Before replacing API keys, check the Google AI Studio project selected in the key's project, and its model-level RPM/TPM/RPD limits. AI Client automatic selection can choose a model with no free quota even when other models in the project have capacity. As of November 2026, Google also restricts Gemini 2.5 generation to previously active projects: model metadata and capability checks may succeed, while actual API generation returns HTTP 404. Prefer a current model such as Gemini 3.5 Flash-Lite or 3.8 Flash in diagnostic tests.

A minimal diagnostic is available on the release branch:

```text
wp eval-file wp-content/plugins/detit/tools/release-ai-provider-smoke.php
```

**Warning:** This diagnostic makes exactly one real, small text-generation request. It may consume provider quota or incur provider fees. It uses a non-binding preference for `gemini-3.5-flash-lite` and prints only success/selected provider+model, or sanitized error code and HTTP status. It intentionally does not print raw error details, API keys or credentials. If the provider smoke fails, troubleshoot the AI Client/provider separately before attributing the failure to the DetIt generation controller.

**Step 4: manual regression on a disposable product (never a live paid order/product).**

- [ ] Saved Language and Tone appear in modal; can override without changing Brand Profile.
- [ ] Product generation succeeds using the configured AI provider after provider-agnostic model selection change.
- [ ] Preview displays only selected generated fields; no WooCommerce writes before Apply.
- [ ] Selective Apply modifies only approved fields; refresh shows persisted data.
- [ ] Undo restores the previous content once; subsequent undo cannot repeat.
- [ ] Catalog audit changes only DetIt's audit records, not product content.
- [ ] Selected scan and first/next automatic batch buttons show successful notices.
- [ ] After editing a test product, dashboard says Needs rescan; rescan restores Current.
- [ ] Brand Profile, product content, and audit history survive plugin reactivation and update.

**Step 5: build the candidate ZIP** from the DetIt plugin directory, using Windows PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\build-release.ps1
```

The command must end with `RESULT: PASS`. It creates `dist/detit-2.0.0.zip` (one plugin directory inside). It excludes `tools/`, Git data, docs, `vendor/`, and Composer files by design. Do **not** ZIP the entire GitHub source folder.

**Step 6: clean-install test using the generated ZIP, on a separate disposable WordPress installation.**

- [ ] Back up the test database and files first.
- [ ] Install only WooCommerce and the generated ZIP; verify activation succeeds without Composer.
- [ ] Verify Settings > DetIt Brand Profile and WooCommerce > DetIt Content Health load.
- [ ] Create a test product; verify auditing works with no AI provider.
- [ ] Configure a compatible AI provider and test actual Generate → Preview → Apply → Undo.
- [ ] Test deactivation/reactivation without lost data.
- [ ] Test an upgrade from an earlier DetIt version on another backed-up test site.
- [ ] Test **uninstall on a disposable site only**: DetIt tables/options are removed; previously applied WooCommerce product content remains. The uninstall path intentionally deletes DetIt audit and generation history.

**Step 7: run official directory checks.**

- [ ] Install [Plugin Check](https://wordpress.org/plugins/plugin-check/), then run Tools > Plugin Check against DetIt on the disposable installation.
- [ ] Optionally use `wp plugin check detit-product-content-generator-for-woocommerce --format=table` after Plugin Check is active; use the actual installed folder slug.
- [ ] Review every error and warning. Bring results back for code fixes; do not ignore severe findings.
- [ ] Validate `readme.txt` with the [WordPress.org readme validator](https://wordpress.org/plugins/developers/readme-validator/).
- [ ] Verify WordPress.org contributor username and add it accurately if appropriate.
- [ ] Verify tested-up-to WordPress version using a genuine test before adding that line.
- [ ] Check plugin display name / requested slug availability, screenshots and trademarks.
- [ ] Review external AI provider disclosure and links against the provider actually used.
- [ ] Confirm the resulting ZIP is below WordPress.org's 10 MB upload guidance.

**Step 8: submission and approval.**

- [ ] Only after passing the above, approve merging the release-prep PR into `develop`, then promote the tested release commit to `main` and tag `v2.0.0`.
- [ ] Submit the final ZIP at https://wordpress.org/plugins/developers/add/ using your WordPress.org account.
- [ ] Monitor the developer email for review findings; revise and resubmit as requested.
- [ ] After approval, publish through the SVN repository assigned by WordPress.org; uploading to the submission portal alone does **not** publish the plugin directory listing.
- [ ] Do not claim the plugin is live before WordPress.org has approved it and its SVN release has been published.

## Known open issue before final release approval

The experimental image-safety PR is not part of Free 2.0.0. Image context remains disabled. Avoid merging that PR inadvertently into the free release.

**Important:** WordPress.org assigns the final plugin slug during submission. If the approved slug differs from `detit-product-content-generator-for-woocommerce`, align package folder, text domain, readme and update path *before* the first SVN publication. Existing development installations under a different folder name (`detit`) may not automatically convert to the WordPress.org plugin path; do not attempt a production upgrade without checking backup and duplicate-plugin behavior.
