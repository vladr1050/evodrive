<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('original_vehicle_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('fleet_vehicles')
                ->nullOnDelete();
        });

        DB::table('shifts')->whereNull('original_vehicle_id')->update([
            'original_vehicle_id' => DB::raw('vehicle_id'),
        ]);

        Schema::create('shift_vehicle_replacements', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('from_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('to_vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'created_at']);
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_vehicle_replacements');

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_vehicle_id');
        });
    }
};
