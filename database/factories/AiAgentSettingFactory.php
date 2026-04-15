<?php

namespace Database\Factories;

use App\Enums\AiAgentKind;
use App\Models\AiAgentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAgentSetting>
 */
class AiAgentSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = AiAgentKind::FloorPlanExtraction;

        return [
            'kind' => $kind,
            'label' => $kind->label(),
            'description' => $kind->description(),
            'system_prompt' => $kind->defaultSystemPrompt(),
        ];
    }
}
