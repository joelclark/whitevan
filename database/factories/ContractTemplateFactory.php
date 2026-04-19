<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ContractTemplate::DEFAULT_KIND,
            'label' => 'Default contract',
            'body' => "# Service agreement\n\nLorem ipsum dolor sit amet.",
        ];
    }
}
