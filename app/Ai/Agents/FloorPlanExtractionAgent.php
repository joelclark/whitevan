<?php

namespace App\Ai\Agents;

use App\Models\AiAgentSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Reads a floor plan PDF and extracts room-level measurements.
 *
 * The sysop-editable prompt body lives in {@see AiAgentSetting::system_prompt};
 * the JSON response shape is frozen here so the declared schema cannot drift
 * from the example that the model sees. Any change to the response contract
 * has to land in code.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-5.4-mini')]
class FloorPlanExtractionAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public const RESPONSE_FORMAT_JSON = <<<'JSON'
{
    "title": "<string here>",
    "total_sqft": <number here>,
    "rooms": [
        {
            "name": "<room name here>",
            "page": <number here>,
            "sqft": <number here>,
            "perimeter": <number here, in linear feet>
        }
    ],
    "errors": [
        "<text here but ONLY if you encounter real errors, omit otherwise>"
    ]
}
JSON;

    public function __construct(private readonly AiAgentSetting $setting) {}

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::OpenAI => ['reasoning' => ['effort' => 'high']],
            default => [],
        };
    }

    public function instructions(): string
    {
        return trim($this->setting->system_prompt)
            ."\n\nReturn ONLY valid JSON matching this exact shape:\n"
            .self::RESPONSE_FORMAT_JSON;
    }

    /**
     * @return array<string, Type>
     *
     * Note: OpenAI strict structured output requires every property key to
     * appear in `required`. Optional fields must be expressed as
     * nullable + required so the serializer emits `type: [..., "null"]`.
     * Do not make fields "optional" in the Laravel sense — that omits the
     * key from `required` and OpenAI rejects the whole schema with a 400.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('A short human-readable title for the floor plan.')
                ->required(),
            'total_sqft' => $schema->number()
                ->description('The sum of every room\'s square footage.')
                ->required(),
            'rooms' => $schema->array()
                ->description('The rooms extracted from the PDF.')
                ->items($schema->object([
                    'name' => $schema->string()->required(),
                    'page' => $schema->integer()->min(1)->required(),
                    'sqft' => $schema->number()->required(),
                    'perimeter' => $schema->number()
                        ->description('Perimeter in linear feet. Return null when not stated on the drawing — the server will compute a fallback.')
                        ->nullable()
                        ->required(),
                ]))
                ->required(),
            'errors' => $schema->array()
                ->description('Only populated when a real error prevents a confident answer. Return null or an empty array when there are no errors.')
                ->items($schema->string())
                ->nullable()
                ->required(),
        ];
    }
}
