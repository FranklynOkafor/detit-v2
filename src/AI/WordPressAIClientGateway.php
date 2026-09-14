<?php

namespace DetIt\AI;

if (! defined('ABSPATH')) {
    exit;
}

final class WordPressAIClientGateway implements AIClientGatewayInterface
{
    private WordPressAIErrorNormalizer $errorNormalizer;

    public function __construct(
        ?WordPressAIErrorNormalizer $errorNormalizer = null
    ) {
        $this->errorNormalizer = $errorNormalizer
            ?? new WordPressAIErrorNormalizer();
    }

    public function generate(
        GenerationRequest $request
    ): GenerationResult {

        /*
         * Keep every direct dependency on WordPress AI
         * inside this gateway.
         */
        if (! function_exists('wp_ai_client_prompt')) {
            return GenerationResult::failure(
                AIErrorCode::NO_AI_PROVIDER->value,
                'The WordPress AI Client is unavailable.'
            );
        }

        try {
            $builder = wp_ai_client_prompt(
                $request->prompt()
            );

            /*
             * Apply optional request configuration.
             */
            if ($request->systemInstruction() !== null) {
                $builder = $builder->using_system_instruction(
                    $request->systemInstruction()
                );
            }

            if ($request->temperature() !== null) {
                $builder = $builder->using_temperature(
                    $request->temperature()
                );
            }

            if ($request->maxTokens() !== null) {
                $builder = $builder->using_max_tokens(
                    $request->maxTokens()
                );
            }

            /*
             * Feature detection happens after configuring
             * the request because model compatibility may
             * depend on those requirements.
             */
            if (! $builder->is_supported_for_text_generation()) {
                return GenerationResult::failure(
                    AIErrorCode::NO_AI_PROVIDER->value,
                    'No configured AI provider supports this text generation request.'
                );
            }

            /*
             * Use the full result variant rather than
             * generate_text() so DetIt can retain provider
             * and model metadata.
             */
            $result = $builder->generate_text_result();

            if (is_wp_error($result)) {
                $normalized = $this->errorNormalizer->normalize(
                    $result
                );

                return GenerationResult::failure(
                    $normalized['code'],
                    $normalized['message']
                );
            }

            $providerId = null;
            $modelId = null;

            $providerMetadata = $result->getProviderMetadata();

            if ($providerMetadata !== null) {
                $providerId = $providerMetadata->getId();
            }

            $modelMetadata = $result->getModelMetadata();

            if ($modelMetadata !== null) {
                $modelId = $modelMetadata->getId();
            }

            return GenerationResult::success(
                $result->toText(),
                $providerId,
                $modelId
            );

        } catch (\Throwable $throwable) {

            return GenerationResult::failure(
                AIErrorCode::UNKNOWN_ERROR->value,
                'An unexpected AI error occurred.'
            );
        }
    }
}