<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('id');
            $table->string('model')->nullable()->after('brand');
            $table->unsignedSmallInteger('year')->nullable()->after('model');
            $table->string('color')->nullable()->after('year');
            $table->string('atd_license_number')->nullable()->after('color');
            $table->string('registration_number')->nullable()->after('atd_license_number');
        });
        foreach (DB::table('fleet_vehicles')->get() as $row) {
            $reg = 'REG-' . $row->id;
            $label = (string) ($row->label ?? 'Vehicle');
            $parts = explode(' ', $label, 2);
            DB::table('fleet_vehicles')->where('id', $row->id)->update([
                'registration_number' => $reg,
                'brand' => $parts[0] ?? 'Unknown',
                'model' => $parts[1] ?? 'Vehicle',
            ]);
        }
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->string('registration_number')->nullable(false)->change();
            $table->unique('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropUnique(['registration_number']);
        });
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model', 'year', 'color', 'atd_license_number', 'registration_number']);
        });
    }
};
