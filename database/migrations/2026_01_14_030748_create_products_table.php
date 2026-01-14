<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sub_category_id')
                ->constrained('sub_categories')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable(); // path/url
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // prevent duplicate product names inside same subcategory
            $table->unique(['sub_category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
