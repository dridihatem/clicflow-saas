<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    protected $model = ContractTemplate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Service Agreement',
            'type_key' => 'service',
            'body_html' => '<p>This agreement is between {{company_name}} and {{client_name}}.</p>',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
