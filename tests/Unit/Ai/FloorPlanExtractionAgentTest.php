<?php

use App\Ai\Agents\FloorPlanExtractionAgent;
use App\Enums\AiAgentKind;
use App\Models\AiAgentSetting;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('instructions() includes the sysop prompt and the frozen response format', function () {
    $setting = new AiAgentSetting([
        'kind' => AiAgentKind::FloorPlanExtraction->value,
        'label' => 'Floor plans',
        'description' => null,
        'system_prompt' => "You are the best floor plan reader.\n  ",
    ]);

    $agent = new FloorPlanExtractionAgent($setting);

    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain('You are the best floor plan reader.')
        ->toContain('Return ONLY valid JSON')
        ->toContain('"total_sqft"')
        ->toContain('"rooms"');

    // Trimmed — no trailing whitespace between the prompt body and the format tail.
    expect($instructions)->not->toContain("best floor plan reader.\n  \n\n");
});

test('schema() declares the response shape used at call time', function () {
    $setting = AiAgentSetting::factory()->make();
    $agent = new FloorPlanExtractionAgent($setting);

    $schema = $agent->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKeys(['title', 'total_sqft', 'rooms', 'errors']);
});
