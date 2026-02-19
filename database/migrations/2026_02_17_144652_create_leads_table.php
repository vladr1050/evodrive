<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->default('unknown'); // google, meta, unknown
            $table->string('intent', 50)->nullable(); // work, rent
            $table->string('phone', 30);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('atd_license')->nullable();
            $table->string('driving_experience', 20)->nullable(); // 3-5, 5-10, 10+
            $table->string('shift_preference', 50)->nullable();
            $table->string('area')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('status', 30)->default('new'); // new, contacted, approved, rejected, archived
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
