<?php
/**
 * DetIt 2.0 release candidate: minimally probe the configured WordPress
 * AI Client independently of DetIt's product generation workflow.
 *
 * Run from app/public:
 * wp eval-file wp-content/plugins/detit/tools/release-ai-provider-smoke.php
 *
 * IMPORTANT: Makes ONE real, very small text-generation request to the
 * merchant-configured provider, and may consume quota/incur provider fees.
 * Never prints API keys, credentials, or raw provider error messages.
 */

echo "RELEASE: WORDPRESS AI PROVIDER SMOKE\n";
echo "-----------------------------------\n";

if (! function_exists('wp_ai_client_prompt')) {
    echo "AI_CLIENT_AVAILABLE: FAIL\n";
    echo "RESULT: FAIL (WordPress AI Client API unavailable)\n";
    return;
}

echo "AI_CLIENT_AVAILABLE: PASS\n";

try {
    $builder = wp_ai_client_prompt('Reply with only the word OK.')
        ->using_model_preference('gemini-2.5-flash-lite')
        ->using_max_tokens(32);

    echo "MODEL_PREFERENCE: gemini-2.5-flash-lite (preference only)\n";

    if (! $builder->is_supported_for_text_generation()) {
        echo "TEXT_GENERATION_SUPPORTED: FAIL\n";
        echo "RESULT: FAIL (No compatible configured text provider)\n";
        return;
    }

    echo "TEXT_GENERATION_SUPPORTED: PASS\n";

    $result = $builder->generate_text_result();

    if (is_wp_error($result)) {
        $data = $result->get_error_data();
        $status = is_array($data) && isset($data['status']) && is_numeric($data['status'])
            ? (int) $data['status']
            : 'not supplied';

        // Deliberately omit the provider's raw message: it can contain
        // sensitive details and is not needed to diagnose HTTP 429.
        echo 'WP_ERROR_CODE: ' . sanitize_key((string) $result->get_error_code()) . "\n";
        echo 'HTTP_STATUS: ' . $status . "\n";
        echo "RESULT: FAIL (AI provider request failed)\n";
        return;
    }

    $provider = $result->getProviderMetadata();
    $model = $result->getModelMetadata();
    $text = trim((string) $result->toText());

    echo 'SELECTED_PROVIDER: ' . (
        $provider !== null ? sanitize_text_field((string) $provider->getId()) : 'unknown'
    ) . "\n";
    echo 'SELECTED_MODEL: ' . (
        $model !== null ? sanitize_text_field((string) $model->getId()) : 'unknown'
    ) . "\n";
    echo 'TEXT_RETURNED: ' . ($text !== '' ? 'YES' : 'NO') . "\n";
    echo 'RESULT: ' . ($text !== '' ? 'PASS' : 'FAIL (empty text)') . "\n";
} catch (\Throwable $e) {
    // Do not log exception messages in a shareable diagnostic: providers
    // may put credentials or request details in exception strings.
    echo 'EXCEPTION_TYPE: ' . get_class($e) . "\n";
    echo "RESULT: FAIL (AI client exception)\n";
}
