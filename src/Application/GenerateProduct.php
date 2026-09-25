<?php

declare(strict_types=1);

namespace DetIt\Application;

use DetIt\AI\GenerationOutcome;
use DetIt\AI\GenerationResponseHandler;
use DetIt\AI\GenerationResponseParser;
use DetIt\AI\OutputSchema;
use DetIt\AI\PromptBuilder;
use DetIt\AI\Templates\TemplateRegistry;
use DetIt\AI\WordPressAIClientGateway;
use DetIt\Domain\BrandProfile;
use DetIt\WooCommerce\ProductFactSheetFactory;
use DetIt\WooCommerce\ProductReader;

if (! defined('ABSPATH')) {
    exit;
}

final class GenerateProduct
{
    private ProductReader $productReader;

    private ProductFactSheetFactory $factSheetFactory;

    private TemplateRegistry $templateRegistry;

    private PromptBuilder $promptBuilder;

    private GenerationResponseHandler $responseHandler;

    public function __construct(
        ?ProductReader $productReader = null,
        ?ProductFactSheetFactory $factSheetFactory = null,
        ?TemplateRegistry $templateRegistry = null,
        ?PromptBuilder $promptBuilder = null,
        ?GenerationResponseHandler $responseHandler = null
    ) {
        $this->productReader = $productReader
            ?? new ProductReader();

        $this->factSheetFactory = $factSheetFactory
            ?? new ProductFactSheetFactory();

        $this->templateRegistry = $templateRegistry
            ?? new TemplateRegistry();

        $this->promptBuilder = $promptBuilder
            ?? new PromptBuilder();

        $this->responseHandler = $responseHandler
            ?? new GenerationResponseHandler(
                new WordPressAIClientGateway(),
                new GenerationResponseParser()
            );
    }

    public function execute(
        int $productId,
        array $selectedFields,
        string $templateSlug,
        string $language,
        string $tone = '',
        string $additionalInstructions = ''
    ): GenerationOutcome {

        /*
         * Keep the application service safe even when it is
         * called from somewhere other than the REST endpoint.
         */
        $selectedFields = array_values(
            array_unique($selectedFields)
        );

        if ($productId < 1) {
            throw new \InvalidArgumentException(
                'Product ID must be greater than zero.'
            );
        }

        if ($selectedFields === []) {
            throw new \InvalidArgumentException(
                'At least one generation field must be selected.'
            );
        }

        foreach ($selectedFields as $field) {
            if (
                ! is_string($field)
                || ! in_array(
                    $field,
                    OutputSchema::fields(),
                    true
                )
            ) {
                throw new \InvalidArgumentException(
                    'One or more generation fields are invalid.'
                );
            }
        }

        $templateSlug = sanitize_key($templateSlug);
        $language = trim($language);
        $tone = trim($tone);
        $additionalInstructions = trim(
            $additionalInstructions
        );

        if ($language === '') {
            throw new \InvalidArgumentException(
                'Generation language cannot be empty.'
            );
        }

        /*
         * 1. Read the WooCommerce product.
         */
        $productContext = $this->productReader->read(
            $productId
        );

        if ($productContext === null) {
            throw new \InvalidArgumentException(
                'The requested product could not be found.'
            );
        }

        /*
         * 2. Convert stored product data into trusted AI facts.
         */
        $factSheet = $this->factSheetFactory->create(
            $productContext
        );

        /*
         * 3. Load the saved DetIt Brand Profile.
         */
        $brandData = get_option(
            'detit_brand_profile',
            []
        );

        if (! is_array($brandData)) {
            $brandData = [];
        }

        $brandProfile = new BrandProfile(
            $brandData
        );

        /*
         * 4. Resolve the requested generation template.
         */
        $template = $this->templateRegistry->get(
            $templateSlug
        );

        if ($template === null) {
            throw new \InvalidArgumentException(
                'The requested DetIt template does not exist.'
            );
        }

        /*
         * 5. Build the complete AI generation request.
         */
        $generationRequest = $this->promptBuilder->build(
            $brandProfile,
            $factSheet,
            $template,
            $selectedFields,
            $language,
            $tone,
            $additionalInstructions
        );

        /*
         * 6. Generate, parse, validate and repair if required.
         *
         * GenerationResponseHandler contains the Stage 37
         * structured-output protection.
         */
        return $this->responseHandler->handle(
            $generationRequest,
            $selectedFields
        );
    }
}