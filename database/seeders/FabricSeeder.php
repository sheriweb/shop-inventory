<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FabricSeeder extends Seeder
{
    public function run()
    {
        // Set locale to Urdu
        app()->setLocale('ur');

        // Disable mass assignment protection for this operation
        Category::unguard();
        Product::unguard();

        // Create categories
        $categories = [
            [
                'name' => 'سوٹنگ', // Suiting
                'name_en' => 'Suiting',
                'slug' => 'suiting',
                'is_active' => true,
                'products' => [
                    ['name' => 'سپر نیٹ', 'name_en' => 'Super Net', 'price' => 2500, 'unit_type' => 'meter'],
                    ['name' => 'پریمیم نیٹ', 'name_en' => 'Premium Net', 'price' => 2800, 'unit_type' => 'meter'],
                    ['name' => 'ڈیلکس نیٹ', 'name_en' => 'Deluxe Net', 'price' => 3000, 'unit_type' => 'meter'],
                    ['name' => 'سپریم نیٹ', 'name_en' => 'Supreme Net', 'price' => 3200, 'unit_type' => 'meter'],
                    ['name' => 'لگژری نیٹ', 'name_en' => 'Luxury Net', 'price' => 3500, 'unit_type' => 'meter'],
                ]
            ],
            [
                'name' => 'شلوار قمیض', // Shalwar Kameez
                'name_en' => 'Shalwar Kameez',
                'slug' => 'shalwar-kameez',
                'is_active' => true,
                'products' => [
                    ['name' => 'سادہ کاٹن', 'name_en' => 'Plain Cotton', 'price' => 1500, 'unit_type' => 'meter'],
                    ['name' => 'پرنٹڈ کاٹن', 'name_en' => 'Printed Cotton', 'price' => 1800, 'unit_type' => 'meter'],
                    ['name' => 'کمبل سوٹ', 'name_en' => 'Kambal Suit', 'price' => 2200, 'unit_type' => 'meter'],
                    ['name' => 'لیس والا سوٹ', 'name_en' => 'Lace Suit', 'price' => 2500, 'unit_type' => 'meter'],
                ]
            ],
            [
                'name' => 'کریان', // Kareen
                'name_en' => 'Kareen',
                'slug' => 'kareen',
                'is_active' => true,
                'products' => [
                    ['name' => 'سادہ کریان', 'name_en' => 'Plain Kareen', 'price' => 1200, 'unit_type' => 'meter'],
                    ['name' => 'پرنٹڈ کریان', 'name_en' => 'Printed Kareen', 'price' => 1500, 'unit_type' => 'meter'],
                ]
            ],
            [
                'name' => 'کھیس', // Khais
                'name_en' => 'Khais',
                'slug' => 'khais',
                'is_active' => true,
                'products' => [
                    ['name' => 'سادہ کھیس', 'name_en' => 'Plain Khais', 'price' => 1000, 'unit_type' => 'meter'],
                    ['name' => 'پرنٹڈ کھیس', 'name_en' => 'Printed Khais', 'price' => 1300, 'unit_type' => 'meter'],
                ]
            ],
            [
                'name' => 'سوٹ', // Suit
                'name_en' => 'Suit',
                'slug' => 'suit',
                'is_active' => true,
                'products' => [
                    ['name' => 'سوٹ 2 پیس', 'name_en' => '2-Piece Suit', 'price' => 3500, 'unit_type' => 'set'],
                    ['name' => 'سوٹ 3 پیس', 'name_en' => '3-Piece Suit', 'price' => 4500, 'unit_type' => 'set'],
                ]
            ],
            [
                'name' => 'جینز', // Jeans
                'name_en' => 'Jeans',
                'slug' => 'jeans',
                'is_active' => true,
                'products' => [
                    ['name' => 'جینز پینٹ', 'name_en' => 'Jeans Pants', 'price' => 2000, 'unit_type' => 'piece'],
                    ['name' => 'جینز شرٹ', 'name_en' => 'Jeans Shirt', 'price' => 1800, 'unit_type' => 'piece'],
                ]
            ],
            [
                'name' => 'دیگر', // Others
                'name_en' => 'Others',
                'slug' => 'others',
                'is_active' => true,
                'products' => [
                    ['name' => 'مختلف اقسام', 'name_en' => 'Various Types', 'price' => 0, 'unit_type' => 'piece'],
                ]
            ],
        ];

        // Create categories and products
        foreach ($categories as $categoryData) {
            // Create or update category
            $category = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                ['is_active' => $categoryData['is_active']]
            );

            // Add translations
            $category->translateOrNew('ur')->name = $categoryData['name'];
            $category->translateOrNew('en')->name = $categoryData['name_en'];
            $category->save();

            // Create products for this category
            foreach ($categoryData['products'] as $productData) {
                // Generate a simple SKU based on category and product name
                $sku = strtoupper(substr($categoryData['slug'], 0, 3)) . '-' . 
                       strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $productData['name_en']));
                $sku = substr($sku, 0, 20); // Limit SKU length
                
                $product = Product::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'price' => $productData['price'],
                        'unit_type' => $productData['unit_type']
                    ],
                    [
                        'sku' => $sku,
                        'quantity_in_meter' => $productData['unit_type'] === 'meter' ? 100 : 0,
                        'quantity_in_gaz' => $productData['unit_type'] === 'gaz' ? 100 : 0,
                        'is_active' => true,
                    ]
                );

                // Add translations for the product
                if ($product->wasRecentlyCreated) {
                    $product->translateOrNew('ur')->name = $productData['name'];
                    $product->translateOrNew('en')->name = $productData['name_en'];
                    $product->translateOrNew('ur')->description = $productData['name'] . ' کی تفصیل';
                    $product->translateOrNew('en')->description = $productData['name_en'] . ' details';
                    $product->save();
                }
            }
        }

        // Re-enable mass assignment protection
        Category::reguard();
        Product::reguard();
    }
}
