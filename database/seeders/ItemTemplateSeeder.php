<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ItemTemplate;

class ItemTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Electronics Template',
                'description' => 'Template for electronic items with technical specifications',
                'category' => 'Electronics',
                'field_definitions' => [
                    [
                        'name' => 'Brand',
                        'type' => 'text',
                        'required' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Model Number',
                        'type' => 'text',
                        'required' => true,
                        'sort_order' => 2,
                    ],
                    [
                        'name' => 'Warranty Period',
                        'type' => 'text',
                        'required' => false,
                        'sort_order' => 3,
                    ],
                    [
                        'name' => 'Power Consumption',
                        'type' => 'text',
                        'required' => false,
                        'sort_order' => 4,
                    ],
                    [
                        'name' => 'Certification',
                        'type' => 'dropdown',
                        'required' => false,
                        'sort_order' => 5,
                        'options' => [
                            'options' => ['CE', 'FCC', 'UL', 'ISO', 'Other']
                        ]
                    ],
                ],
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Office Supplies Template',
                'description' => 'Template for office supplies and stationery',
                'category' => 'Office Supplies',
                'field_definitions' => [
                    [
                        'name' => 'Color',
                        'type' => 'text',
                        'required' => false,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'Material',
                        'type' => 'text',
                        'required' => false,
                        'sort_order' => 2,
                    ],
                    [
                        'name' => 'Pack Size',
                        'type' => 'number',
                        'required' => false,
                        'sort_order' => 3,
                    ],
                    [
                        'name' => 'Eco-Friendly',
                        'type' => 'boolean',
                        'required' => false,
                        'sort_order' => 4,
                    ],
                ],
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Furniture Template',
                'description' => 'Template for furniture items',
                'category' => 'Furniture',
                'field_definitions' => [
                    [
                        'name' => 'Material',
                        'type' => 'dropdown',
                        'required' => true,
                        'sort_order' => 1,
                        'options' => [
                            'options' => ['Wood', 'Metal', 'Plastic', 'Glass', 'Fabric', 'Leather']
                        ]
                    ],
                    [
                        'name' => 'Dimensions (L x W x H)',
                        'type' => 'text',
                        'required' => true,
                        'sort_order' => 2,
                    ],
                    [
                        'name' => 'Weight',
                        'type' => 'number',
                        'required' => false,
                        'sort_order' => 3,
                    ],
                    [
                        'name' => 'Assembly Required',
                        'type' => 'boolean',
                        'required' => false,
                        'sort_order' => 4,
                    ],
                    [
                        'name' => 'Delivery Date',
                        'type' => 'date',
                        'required' => false,
                        'sort_order' => 5,
                    ],
                ],
                'is_active' => true,
                'is_default' => true,
            ],
        ];

        foreach ($templates as $template) {
            ItemTemplate::create($template);
        }
    }
}
