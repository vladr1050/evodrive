<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renters', function (Blueprint $table) {
            $table->id();
            $table->string('name_or_company');
            $table->string('personal_code_or_reg_number')->nullable();
            $table->string('client_identifier')->nullable();
            $table->string('licence')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('contract_signed_at')->nullable();
            $table->date('contract_ends_at')->nullable();
            $table->decimal('total_debt', 12, 2)->nullable();
            $table->date('next_payment_at')->nullable();
            $table->unsignedSmallInteger('overdue_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rented_fleet_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->nullable()->constrained('renters')->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('atd_license_number')->nullable();
            $table->string('registration_number');
            $table->string('status', 20)->default('active');
            $table->string('imei', 20)->nullable();
            $table->string('sim', 50)->nullable();
            $table->timestamps();

            $table->unique('registration_number');
        });

        Schema::create('renter_payment_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->constrained('renters')->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_overdue')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renter_payment_schedule_items');
        Schema::dropIfExists('rented_fleet_vehicles');
        Schema::dropIfExists('renters');
    }
};
