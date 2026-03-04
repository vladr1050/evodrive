<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->string('phone_number', 30)->nullable()->after('registration_number');
        });

        Schema::create('car_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->string('action', 30); // start_shift, open_car, close_car, end_shift
            $table->string('sms_to', 30);
            $table->json('sms_payloads'); // array of SMS text strings
            $table->string('status', 20)->default('queued'); // queued, sent, delivered, failed
            $table->json('provider_message_ids')->nullable(); // NESS message IDs
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_commands');
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
};
