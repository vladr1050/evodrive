<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('min_duration_hours')->default(4);
            $table->json('allowed_durations_json'); // [4, 6, 8, 10, 12]
            $table->decimal('vehicle_downtime_hours', 4, 2)->default(0); // buffer between shifts for same vehicle
            $table->unsignedTinyInteger('max_shifts_per_driver_per_day')->nullable();
            $table->unsignedSmallInteger('planning_window_days')->default(14); // how far ahead driver can book
            $table->unsignedTinyInteger('time_slot_minutes')->default(15); // start time must align to 15-min
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_policies');
    }
};
