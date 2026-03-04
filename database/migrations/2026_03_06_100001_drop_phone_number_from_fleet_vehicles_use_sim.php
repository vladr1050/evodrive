<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Car control uses vehicle SIM (phone number), not a separate phone_number column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('fleet_vehicles', 'phone_number')) {
            Schema::table('fleet_vehicles', function (Blueprint $table) {
                $table->dropColumn('phone_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fleet_vehicles', 'phone_number')) {
            Schema::table('fleet_vehicles', function (Blueprint $table) {
                $table->string('phone_number', 30)->nullable()->after('registration_number');
            });
        }
    }
};
