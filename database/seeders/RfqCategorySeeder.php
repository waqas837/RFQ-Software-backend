<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class RfqCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics & IT',
                'slug' => 'electronics-it',
                'description' => 'Electronic devices, computers, software, and IT equipment',
                'is_active' => true,
            ],
            [
                'name' => 'Office Supplies',
                'slug' => 'office-supplies',
                'description' => 'Stationery, furniture, office equipment, and supplies',
                'is_active' => true,
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'description' => 'Consulting, maintenance, professional services, and support',
                'is_active' => true,
            ],
            [
                'name' => 'Raw Materials',
                'slug' => 'raw-materials',
                'description' => 'Manufacturing materials, components, and industrial supplies',
                'is_active' => true,
            ],
            [
                'name' => 'Equipment & Machinery',
                'slug' => 'equipment-machinery',
                'description' => 'Industrial equipment, tools, machinery, and heavy equipment',
                'is_active' => true,
            ],
            [
                'name' => 'Construction & Building',
                'slug' => 'construction-building',
                'description' => 'Construction materials, building supplies, and infrastructure',
                'is_active' => true,
            ],
            [
                'name' => 'Healthcare & Medical',
                'slug' => 'healthcare-medical',
                'description' => 'Medical equipment, pharmaceuticals, and healthcare supplies',
                'is_active' => true,
            ],
            [
                'name' => 'Transportation & Logistics',
                'slug' => 'transportation-logistics',
                'description' => 'Vehicles, transportation services, and logistics equipment',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }
    }
}
