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
            /*FabricSeeder::class,*/
        ]);

        // Create categories in both English and Urdu - Based on new lace product list
        $categories = [
            [
                'en' => ['name' => 'Lace Products', 'description' => 'Various types of lace and lace materials'],
                'ur' => ['name' => 'لیس کی اشیاء', 'description' => 'مختلف قسم کے لیس اور لیس کا سامان']
            ],
            [
                'en' => ['name' => 'Stitching & Binding', 'description' => 'Stitching styles and binding materials'],
                'ur' => ['name' => 'سلائی اور بائنڈنگ', 'description' => 'سلائی کے انداز اور بائنڈنگ کا سامان']
            ],
            [
                'en' => ['name' => 'Hooks & Buttons', 'description' => 'Fancy hooks, buttons and buckles'],
                'ur' => ['name' => 'ہکس اور بٹن', 'description' => 'فینسی ہکس، بٹن اور بکل']
            ],
            [
                'en' => ['name' => 'Needles & Tools', 'description' => 'Sewing needles and related tools'],
                'ur' => ['name' => 'سوئیاں اور آلات', 'description' => 'سلائی کی سوئیاں اور متعلقہ آلات']
            ],
            [
                'en' => ['name' => 'Accessories', 'description' => 'Chains, zips and other accessories'],
                'ur' => ['name' => 'لوازمات', 'description' => 'چین، زپ اور دیگر لوازمات']
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

        // Create products based on the new lace product list
        $products = [
            // Lace Products Category (Category 1)
            [
                'translations' => [
                    'en' => [
                        'name' => 'Shuttle Lace 14 yards',
                        'description' => 'High quality shuttle lace material, 14 yards'
                    ],
                    'ur' => [
                        'name' => 'شٹل لیس 14 گز',
                        'description' => 'اعلی معیار کا شٹل لیس مٹیریل، 14 گز'
                    ],
                ],
                'attributes' => [
                    'sku' => 'SHUT-LACE-14',
                    'category_id' => 1,
                    'price' => 1400, // 14 gaz
                    'cost_price' => 1200,
                    'quantity_in_gaz' => 14,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 5,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Shuttle Lace Embroidery',
                        'description' => 'Shuttle lace with embroidery details'
                    ],
                    'ur' => [
                        'name' => 'شٹل لیس صدرالم',
                        'description' => 'شٹل لیس بمع صدرالم کاری'
                    ],
                ],
                'attributes' => [
                    'sku' => 'SHUT-LACE-EMB',
                    'category_id' => 1,
                    'price' => 1200,
                    'cost_price' => 950,
                    'quantity_in_gaz' => 10,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 3,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Madanshar Lace Design',
                        'description' => 'Designer lace with Madanshar pattern'
                    ],
                    'ur' => [
                        'name' => 'مدنشر لیس ڈیزائن',
                        'description' => 'مدنشر ڈیزائن کے ساتھ لیس'
                    ],
                ],
                'attributes' => [
                    'sku' => 'MAD-LACE-DES',
                    'category_id' => 1,
                    'price' => 1500,
                    'cost_price' => 1200,
                    'quantity_in_gaz' => 8,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 2,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Indman Dori',
                        'description' => 'Traditional Indman style lace string'
                    ],
                    'ur' => [
                        'name' => 'انڈمن ڈوری',
                        'description' => 'روایتی انڈمن سٹائل لیس ڈوری'
                    ],
                ],
                'attributes' => [
                    'sku' => 'IND-DORI-001',
                    'category_id' => 1,
                    'price' => 800,
                    'cost_price' => 650,
                    'quantity_in_gaz' => 15,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 3,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Shuttle Lace 32 yards',
                        'description' => 'High quality shuttle lace material, 32 yards'
                    ],
                    'ur' => [
                        'name' => 'شٹل لیس 32 گز',
                        'description' => 'اعلی معیار کا شٹل لیس مٹیریل، 32 گز'
                    ],
                ],
                'attributes' => [
                    'sku' => 'SHUT-LACE-32',
                    'category_id' => 1,
                    'price' => 3200, // 32 gaz
                    'cost_price' => 2800,
                    'quantity_in_gaz' => 32,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 5,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            // Stitching & Binding Category (Category 2)
            [
                'translations' => [
                    'en' => [
                        'name' => 'Chinese Stitching',
                        'description' => 'Traditional Chinese stitching style'
                    ],
                    'ur' => [
                        'name' => 'چائنیز سلائی',
                        'description' => 'روایتی چائنیز سلائی کا انداز'
                    ],
                ],
                'attributes' => [
                    'sku' => 'CHIN-STITCH-001',
                    'category_id' => 2,
                    'price' => 1200,
                    'cost_price' => 950,
                    'quantity_in_gaz' => 20,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 5,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Madanshar Stitching Freestyle',
                        'description' => 'Madanshar freestyle stitching technique'
                    ],
                    'ur' => [
                        'name' => 'مدنشر سلائی فری اسٹائل',
                        'description' => 'مدنشر فری سٹائل سلائی کا طریقہ'
                    ],
                ],
                'attributes' => [
                    'sku' => 'MAD-STITCH-FREE',
                    'category_id' => 2,
                    'price' => 1800,
                    'cost_price' => 1500,
                    'quantity_in_gaz' => 15,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 3,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Binding Tape',
                        'description' => 'High quality fabric binding tape'
                    ],
                    'ur' => [
                        'name' => 'بائنڈنگ ٹیپ',
                        'description' => 'اعلی معیار کی کپڑے کی بائنڈنگ ٹیپ'
                    ],
                ],
                'attributes' => [
                    'sku' => 'BIND-TAPE-001',
                    'category_id' => 2,
                    'price' => 800,
                    'cost_price' => 650,
                    'quantity_in_meter' => 50,
                    'quantity_in_gaz' => 0,
                    'min_stock_level' => 10,
                    'unit_type' => 'meter',
                    'is_active' => true,
                ]
            ],
            // Needles & Tools Category (Category 4)
            [
                'translations' => [
                    'en' => [
                        'name' => 'Knife',
                        'description' => 'Cutting knife tool'
                    ],
                    'ur' => [
                        'name' => 'چاقو',
                        'description' => 'کاٹنے والا چاقو'
                    ],
                ],
                'attributes' => [
                    'sku' => 'KNIFE-001',
                    'category_id' => 4,
                    'price' => 800,
                    'cost_price' => 650,
                    'quantity_in_gaz' => 5,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 2,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Old Horn',
                        'description' => 'Traditional horn tool'
                    ],
                    'ur' => [
                        'name' => 'اولڈ ہون',
                        'description' => 'روایتی ہارن کا آلہ'
                    ],
                ],
                'attributes' => [
                    'sku' => 'OLD-HORN-001',
                    'category_id' => 4,
                    'price' => 1200,
                    'cost_price' => 1000,
                    'quantity_in_gaz' => 3,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 1,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            // Hooks & Buttons Category (Category 3)
            [
                'translations' => [
                    'en' => [
                        'name' => 'Fancy Hooks',
                        'description' => 'Decorative hooks for clothing'
                    ],
                    'ur' => [
                        'name' => 'فینسی ہکس',
                        'description' => 'کپڑوں کے لیے آرائشی ہکس'
                    ],
                ],
                'attributes' => [
                    'sku' => 'FANCY-HOOK-001',
                    'category_id' => 3,
                    'price' => 600,
                    'cost_price' => 450,
                    'quantity_in_gaz' => 30,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 10,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            // Accessories Category (Category 5)
            [
                'translations' => [
                    'en' => [
                        'name' => 'Rustal Ars',
                        'description' => 'Rustal ars hardware item'
                    ],
                    'ur' => [
                        'name' => 'روسٹل ارس',
                        'description' => 'روسٹل ارس سخت سامان'
                    ],
                ],
                'attributes' => [
                    'sku' => 'RUST-ARS-004',
                    'category_id' => 5,
                    'price' => 400, // 4 number
                    'cost_price' => 350,
                    'quantity_in_gaz' => 4,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 1,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Rustal Ars',
                        'description' => 'Decorative accessory item'
                    ],
                    'ur' => [
                        'name' => 'روسٹل ارس',
                        'description' => 'آرائشی لوازمات'
                    ],
                ],
                'attributes' => [
                    'sku' => 'RUST-ARS-001',
                    'category_id' => 5,
                    'price' => 400,
                    'cost_price' => 350,
                    'quantity_in_gaz' => 4,
                    'quantity_in_meter' => 0,
                    'min_stock_level' => 1,
                    'unit_type' => 'gaz',
                    'is_active' => true,
                ]
            ],
            [
                'translations' => [
                    'en' => [
                        'name' => 'Quality Zipper',
                        'description' => 'High quality metal zipper'
                    ],
                    'ur' => [
                        'name' => 'کوالٹی زپ',
                        'description' => 'اعلی معیار کی دھاتی زپ'
                    ],
                ],
                'attributes' => [
                    'sku' => 'QUAL-ZIP-001',
                    'category_id' => 5,
                    'price' => 1000,
                    'cost_price' => 900,
                    'quantity_in_meter' => 10,
                    'quantity_in_gaz' => 0,
                    'min_stock_level' => 2,
                    'unit_type' => 'meter',
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
