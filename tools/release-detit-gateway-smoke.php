<?php
/**
 * Release check: send a minimal prompt THROUGH DetIt's real AI gateway.
 *
 * Run from WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/release-detit-gateway-smoke.php
 *
 * IMPORTANT: This sends ONE real, short request to the configured provider.
 * It may use AI quota or incur provider charges. It does not write products,
 * generation history, or settings. No API key, raw exception or prompt text
 * is printed.
 */

use DetIt\AI\GenerationRequest;
use DetIt\AI\WordPressAIClientGateway;

echo "RELEASE: DETIT PRODUCT GENERATION GATEWAY\n";
echo "----------------------------------------\n";

if (! function_exists('wp_ai_client_prompt')) {
    echo "AI_CLIENT_AVAILABLE: FAIL\n";
    echo "RESULT: FAIL (AI Client unavailable)\n";
    return;
}

echo "AI_CLIENT_AVAILABLE: PASS\n";

try {
    $result = (new WordPressAIClientGateway())->generate(
        new GenerationRequest('Reply with only the word OK.')
    );

    if ($result->isFailure()) {
        echo 'GATEWAY_ERROR_CODE: ' . sanitize_key((string) $result->errorCode()) . "\n";
        echo "RESULT: FAIL (DetIt gateway request failed)\n";
        return;
    }

    $text = trim((string) $result->text());
    echo 'SELECTED_PROVIDER: ' . sanitize_text_field((string) ($result->providerId() ?? 'unknown')) . "\n";
    echo 'SELECTED_MODEL: ' . sanitize_text_field((string) ($result->modelId() ?? 'unknown')) . "\n";
    echo 'TEXT_RETURNED: ' . ($text !== '' ? 'YES' : 'NO') . "\n";
    echo 'RESULT: ' . ($text !== '' ? 'PASS' : 'FAIL (empty text)') . "\n";
} catch (\Throwable $error) {
    // The class name is useful for triage without revealing provider secrets.
    echo 'EXCEPTION_TYPE: ' . get_class($error) . "\n";
    echo "RESULT: FAIL (gateway exception)\n";
}
