<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('type', 50);
            $table->string('transmission', 50);
            $table->string('consumption', 50);
            $table->unsignedTinyInteger('seats')->default(5);
            $table->unsignedInteger('price');
            $table->unsignedInteger('deposit');
            $table->string('image_path')->nullable();
            $table->string('image_url')->nullable();
            $table->json('categories')->nullable();
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_vehicles');
    }
};
