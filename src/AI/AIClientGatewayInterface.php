<?php

namespace DetIt\AI;

if (! defined('ABSPATH')) {
    exit;
}

interface AIClientGatewayInterface
{
    public function generate(
        GenerationRequest $request
    ): GenerationResult;
}