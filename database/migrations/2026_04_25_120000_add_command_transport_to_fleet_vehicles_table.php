<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->string('command_transport', 10)->nullable()->after('sim');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropColumn('command_transport');
        });
    }
};
