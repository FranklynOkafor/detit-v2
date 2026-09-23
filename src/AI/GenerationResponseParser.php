<?php

declare(strict_types=1);

namespace DetIt\AI;

final class GenerationResponseParser
{
    public function parse(
        string $rawResponse,
        ?array $expectedFields = null
    ): GenerationResult {
        $json = $this->normalize($rawResponse);

        if ($json === '') {
            throw new \InvalidArgumentException(
                'The AI response is empty.'
            );
        }

        if ($json[0] !== '{') {
            throw new \InvalidArgumentException(
                'The AI response is not a JSON object.'
            );
        }

        try {
            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(
                'The AI response contains invalid JSON.',
                0,
                $exception
            );
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'The AI response must decode to an object.'
            );
        }

        try {
            return GenerationResult::fromArray(
                $data,
                $expectedFields
            );
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(
                'The AI response does not match the expected output schema.',
                0,
                $exception
            );
        }
    }

    private function normalize(string $rawResponse): string
    {
        $response = trim($rawResponse);

        // Remove UTF-8 BOM if a provider ever includes one.
        $response = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $response
        ) ?? $response;

        /*
         * Some models wrap otherwise-valid JSON in Markdown fences.
         *
         * ```json
         * {...}
         * ```
         *
         * This formatting mistake can be corrected locally without
         * spending another AI request.
         */
        if (
            preg_match(
                '/^```(?:json)?\s*(.*?)\s*```$/is',
                $response,
                $matches
            )
        ) {
            $response = trim($matches[1]);
        }

        return $response;
    }
}