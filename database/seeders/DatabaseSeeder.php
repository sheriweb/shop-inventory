<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Set app locale to Urdu
        app()->setLocale('ur');
        
        // Seed roles, permissions, and default users
        $this->call([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            FabricSeeder::class,
        ]);

        // Create categories in both English and Urdu
        $categories = [
            [
                'en' => ['name' => 'Fabric', 'description' => 'Various types of fabrics'],
                'ur' => ['name' => 'کپڑا', 'description' => 'مختلف قسم کے کپڑے']
            ],
            [
                'en' => ['name' => 'Accessories', 'description' => 'Sewing and clothing accessories'],
                'ur' => ['name' => 'لوازمات', 'description' => 'سلائی اور کپڑوں کے لوازمات']
            ],
            [
                'en' => ['name' => 'Thread', 'description' => 'Sewing threads'],
                'ur' => ['name' => 'دھاگہ', 'description' => 'سلائی کے دھاگے']
            ],
        ];

        foreach ($categories as $categoryData) {
            // Create the category
            $category = Category::create([
                'is_active' => true
            ]);
            
            // Then add translations
            foreach (['en', 'ur'] as $locale) {
                $category->translateOrNew($locale)->fill([
                    'name' => $categoryData[$locale]['name'],
                    'description' => $categoryData[$locale]['description']
                ]);
            }
            
            // Save the translations
            $category->save();
        }

        // Create sample products in both English and Urdu
        $products = [
            [
                'translations' => [
                    'en' => [
                        'name' => 'Cotton Fabric',
                        'description' => '100% Cotton fabric, soft and breathable'
                    ],
                    'ur' => [
                        'name' => 'سوتی کپڑا',
                        'description' => '100% سوتی کپڑا، نرم اور آرام دہ'
                    ],
                ],
                'attributes' => [
                    'sku' => 'COT-FAB-001',
                    'category_id' => 1,
                    'price' => 2500, // in paisa (25.00 PKR)
                    'cost_price' => 2000, // in paisa (20.00 PKR)
                    'quantity_in_meter' => 1000,
                    'quantity_in_gaz' => 0,
                    'min_stock_level' => 100,
                    'unit_type' => 'meter',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Plastic Buttons - White',
                        'description' => 'Standard white plastic buttons'
                    ],
                    'ur' => [
                        'name' => 'پلاسٹک کے بٹن - سفید',
                        'description' => 'معیاری سفید پلاسٹک کے بٹن'
                    ],
                ],
                'attributes' => [
                    'sku' => 'BTN-PLA-WHT-001',
                    'category_id' => 2,
                    'price' => 200, // in paisa (2.00 PKR)
                    'cost_price' => 100, // in paisa (1.00 PKR)
                    'quantity_in_gaz' => 500,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 50,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
        ];

        foreach ($products as $productData) {
            // Create the product
            $product = Product::create($productData['attributes']);
            
            // Then add translations
            foreach (['en', 'ur'] as $locale) {
                $product->translateOrNew($locale)->fill([
                    'name' => $productData['translations'][$locale]['name'],
                    'description' => $productData['translations'][$locale]['description']
                ]);
            }
            
            // Save the translations
            $product->save();
        }
    }
}
