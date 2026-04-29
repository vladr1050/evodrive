<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_command_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_command_id')->constrained('car_commands')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('requested_mode', 10);
            $table->string('effective_transport', 10);
            $table->string('sim_number', 191)->nullable();
            $table->text('command_text');
            $table->boolean('ok')->default(false);
            $table->string('failure_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_refs')->nullable();
            $table->text('response_detail')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'created_at']);
            $table->index('car_command_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_command_deliveries');
    }
};
