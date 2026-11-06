<?php
/**
 * Read-only static release checks for changes prompted by Plugin Check.
 *
 * From WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/release-plugin-check-fixes-smoke.php
 *
 * No AI calls, no SQL, no product writes, no option changes.
 * This does NOT substitute for the official WordPress Plugin Check.
 */

echo "RELEASE: PLUGIN CHECK REMEDIATION SMOKE\n";
echo "-------------------------------------\n";

$root = dirname(__DIR__);
$failed = 0;
$check = static function (string $name, bool $ok) use (&$failed): void {
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $ok) {
        ++$failed;
    }
};

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        throw new RuntimeException('Unable to inspect expected local file: ' . $relative);
    }

    return $contents;
};

try {
    $readme = $read('readme.txt');
    $plugin = $read('detit.php');
    $bootstrap = $read('src/Plugin.php');
    $page = $read('src/Admin/ContentHealthPage.php');
    $summary = $read('src/Abilities/ProductSummaryAbility.php');
    $issues = $read('src/Abilities/ProductIssuesAbility.php');
    $prompts = $read('src/AI/PromptBuilder.php');
    $dashboard = $read('src/Storage/ContentHealthDashboardRepository.php');
    $generation = $read('src/Storage/GenerationRepository.php');
    $index = $read('src/Storage/ProductIndexRepository.php');
    $runs = $read('src/Storage/RunRepository.php');
    $runItems = $read('src/Storage/RunItemRepository.php');
    $uninstall = $read('uninstall.php');

    $check('README_TESTED_UP_TO_PRESENT', preg_match('/^Tested up to:\s*[0-9.]+$/mi', $readme) === 1);
    $check('NO_NONEXISTENT_DOMAIN_PATH', stripos($plugin, 'Domain Path: languages') === false);
    $check('MANUAL_TRANSLATION_LOADER_REMOVED', ! str_contains($bootstrap, 'load_plugin_textdomain('));
    $check('DASHBOARD_LITERAL_TRANSLATION_DOMAINS', ! str_contains($page, 'self::TD'));
    $check('ABILITY_LITERAL_TRANSLATION_DOMAINS', ! str_contains($summary . $issues, 'self::TEXT_DOMAIN'));
    $check('NO_CONCATENATED_ABILITY_TRANSLATION', ! str_contains($summary, ". 'Requires permission to manage"));
    $check('PROMPTS_NO_HEREDOC', ! str_contains($prompts, '<<<PROMPT'));
    $check('DASHBOARD_NONCES_STILL_PRESENT',
        str_contains($page, "check_admin_referer('detit_health_scan_batch')")
        && str_contains($page, "check_admin_referer('detit_health_scan_selected')")
        && str_contains($page, "check_admin_referer('detit_health_scan_product_'"));
    $check('SELECTED_INPUT_SANITIZATION_PRESENT', str_contains($page, "map_deep(wp_unslash("));
    $check('AUDIT_TABLE_IDENTIFIERS_PREPARED', str_contains($dashboard, 'FROM %i p')
        && str_contains($dashboard, 'LEFT JOIN %i i'));
    $check('REPOSITORY_IDENTIFIERS_PREPARED', ! str_contains(
        $generation . $index . $runs . $runItems,
        '{$this->table}'
    ) && str_contains($generation, '%i'));
    $check('UNINSTALL_TABLES_PREPARED', str_contains($uninstall, "prepare('DROP TABLE IF EXISTS %i'"));
    $check('UNINSTALL_ONLY_DETIT_TABLES', ! str_contains($uninstall, 'DROP TABLE IF EXISTS wp_posts')
        && ! str_contains($uninstall, 'wp_delete_post'));
} catch (Throwable $error) {
    // Avoid leaking local paths or secrets in shared terminal logs.
    echo 'STATIC_CHECK_EXCEPTION_TYPE: ' . get_class($error) . PHP_EOL;
    ++$failed;
}

echo 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL (' . $failed . ')') . PHP_EOL;
