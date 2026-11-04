<?php

declare(strict_types=1);

namespace DetIt\AI;

use DetIt\Domain\ProductFactSheet;

final class GenerationResponseHandler
{
    private AIClientGatewayInterface $gateway;
    private GenerationResponseParser $parser;

    public function __construct(
        AIClientGatewayInterface $gateway,
        GenerationResponseParser $parser
    ) {
        $this->gateway = $gateway;
        $this->parser  = $parser;
    }

    public function handle(
        GenerationRequest $request,
        array $expectedFields,
        ?ProductFactSheet $productFacts = null
    ): GenerationOutcome {
        if ($expectedFields === []) {
            throw new \InvalidArgumentException(
                'At least one expected generation field is required.'
            );
        }

        /*
         * Attempt #1:
         * Normal AI generation.
         */
        $initialResponse = $this->gateway->generate($request);

        if ($initialResponse->isFailure()) {
            return GenerationOutcome::failure(
                $initialResponse->errorCode()
                    ?? AIErrorCode::UNKNOWN_ERROR->value,
                $initialResponse->errorMessage()
                    ?? 'The AI request failed.'
            );
        }

        $qualityIssues = [];

        try {
            $result = $this->parser->parse(
                (string) $initialResponse->text(),
                $expectedFields
            );

            if ($productFacts !== null) {
                $qualityIssues = (new ContentQualityPolicy())->issues(
                    $result,
                    $productFacts,
                    $expectedFields
                );
            }

            if ($qualityIssues === []) {
                return GenerationOutcome::success(
                    $result,
                    $initialResponse->providerId(),
                    $initialResponse->modelId()
                );
            }
            // A well-formed JSON response can still be unhelpfully short.
            // Give the model one opportunity to improve it using facts.
        } catch (\InvalidArgumentException $exception) {
            // Invalid structured output; preserve the existing one-repair
            // limit rather than repeatedly spending provider credits.
        }

        /*
         * Attempt #2:
         * One repair request only.
         */
        $repairRequest = $this->buildRepairRequest(
            $request,
            (string) $initialResponse->text(),
            $expectedFields,
            $qualityIssues
        );

        $repairResponse = $this->gateway->generate(
            $repairRequest
        );

        /*
         * If the provider itself fails during repair, preserve the
         * provider-level failure rather than pretending it was a
         * structured-output problem.
         */
        if ($repairResponse->isFailure()) {
            return GenerationOutcome::failure(
                $repairResponse->errorCode()
                    ?? AIErrorCode::UNKNOWN_ERROR->value,
                $repairResponse->errorMessage()
                    ?? 'The AI repair request failed.'
            );
        }

        try {
            $result = $this->parser->parse(
                (string) $repairResponse->text(),
                $expectedFields
            );

            if ($productFacts !== null) {
                $remainingIssues = (new ContentQualityPolicy())->issues(
                    $result,
                    $productFacts,
                    $expectedFields
                );

                if ($remainingIssues !== []) {
                    return GenerationOutcome::failure(
                        AIErrorCode::INVALID_AI_RESPONSE->value,
                        'The AI could not produce sufficiently useful product copy using the available facts. Add verified product details or try again.'
                    );
                }
            }

            return GenerationOutcome::success(
                $result,
                $repairResponse->providerId(),
                $repairResponse->modelId()
            );
        } catch (\InvalidArgumentException $exception) {
            return GenerationOutcome::failure(
                AIErrorCode::INVALID_AI_RESPONSE->value,
                'The AI returned an invalid structured response after one repair attempt.'
            );
        }
    }

    private function buildRepairRequest(
        GenerationRequest $originalRequest,
        string $invalidResponse,
        array $expectedFields,
        array $qualityIssues = []
    ): GenerationRequest {
        $fieldList = implode(', ', $expectedFields);

        // These messages come only from DetIt's own quality checks, never
        // directly from external product data or returned model text.
        $qualityGuidance = $qualityIssues !== []
            ? "The previous output was valid JSON but did not meet these copy-quality requirements:\n"
                . implode("\n", $qualityIssues) . "\n"
            : '';

        $repairPrompt =
            $originalRequest->prompt()
            . "\n\n"
            . "----- DETIT RESPONSE REPAIR -----\n"
            . "Your previous response could not be accepted because it "
            . "was not valid structured output.\n\n"
            . "Return the requested content again as ONE valid JSON object.\n"
            . "Use exactly these top-level fields: {$fieldList}.\n"
            . "Do not add Markdown code fences.\n"
            . "Do not add explanations before or after the JSON.\n"
            . "Do not invent new product facts.\n"
            . "All original fact-safety and content rules still apply.\n"
            . $qualityGuidance . "\n"
            . "Previous invalid response:\n"
            . "<invalid_output>\n"
            . $invalidResponse
            . "\n</invalid_output>";

        return new GenerationRequest(
            $repairPrompt,
            0.0,
            $originalRequest->maxTokens(),
            $originalRequest->systemInstruction()
        );
    }
}