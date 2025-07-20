<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->integer('price'); // in paisa
            $table->integer('cost_price')->nullable(); // in paisa
            $table->decimal('quantity_in_meter', 10, 2)->default(0);
            $table->decimal('quantity_in_gaz', 10, 2)->default(0);
            $table->decimal('min_stock_level', 10, 2)->default(0);
            $table->string('unit_type')->default('gaz');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
