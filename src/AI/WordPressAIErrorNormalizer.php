<?php

namespace DetIt\AI;

if (! defined('ABSPATH')) {
    exit;
}

final class WordPressAIErrorNormalizer
{
    /**
     * @return array{
     *     code: string,
     *     message: string
     * }
     */
    public function normalize(\WP_Error $error): array
    {
        $wordpressCode = (string) $error->get_error_code();
        $originalMessage = (string) $error->get_error_message();

        $data = $error->get_error_data();

        $status = 0;

        if (
            is_array($data)
            && isset($data['status'])
            && is_numeric($data['status'])
        ) {
            $status = (int) $data['status'];
        }

        $errorCode = $this->determineErrorCode(
            $wordpressCode,
            $status,
            $originalMessage
        );

        return [
            'code' => $errorCode->value,
            'message' => $this->messageFor($errorCode),
        ];
    }

    private function determineErrorCode(
        string $wordpressCode,
        int $status,
        string $message
    ): AIErrorCode {

        if (in_array($status, [401, 403], true)) {
            return AIErrorCode::AUTHENTICATION_ERROR;
        }

        if ($status === 429) {
            return AIErrorCode::RATE_LIMIT;
        }

        if (in_array($status, [408, 504], true)) {
            return AIErrorCode::PROVIDER_TIMEOUT;
        }

        if (in_array($status, [502, 503], true)) {
            return AIErrorCode::PROVIDER_UNAVAILABLE;
        }

        if ($this->looksLikeNoProviderError($message)) {
            return AIErrorCode::NO_AI_PROVIDER;
        }

        return match ($wordpressCode) {
            'prompt_network_error',
            'prompt_upstream_server_error',
            'prompt_prevented'
                => AIErrorCode::PROVIDER_UNAVAILABLE,

            default
                => AIErrorCode::UNKNOWN_ERROR,
        };
    }

    private function looksLikeNoProviderError(
        string $message
    ): bool {
        $message = strtolower($message);

        $phrases = [
            'no suitable model',
            'no suitable provider',
            'no provider available',
            'no ai provider',
            'no model available',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function messageFor(
        AIErrorCode $errorCode
    ): string {
        return match ($errorCode) {
            AIErrorCode::NO_AI_PROVIDER =>
                'No compatible AI provider is currently available.',

            AIErrorCode::AUTHENTICATION_ERROR =>
                'The AI provider could not authenticate the connection.',

            AIErrorCode::RATE_LIMIT =>
                'The AI provider is temporarily rate limiting requests.',

            AIErrorCode::PROVIDER_TIMEOUT =>
                'The AI provider took too long to respond.',

            AIErrorCode::PROVIDER_UNAVAILABLE =>
                'The AI service is temporarily unavailable.',

            AIErrorCode::INVALID_AI_RESPONSE =>
                'The AI provider returned an invalid response.',

            AIErrorCode::UNKNOWN_ERROR =>
                'An unexpected AI error occurred.',
        };
    }
}