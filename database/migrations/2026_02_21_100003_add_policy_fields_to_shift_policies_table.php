<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_policies', function (Blueprint $table) {
            $table->boolean('require_return_to_home_station')->default(false)->after('time_slot_minutes');
            $table->unsignedTinyInteger('planning_opens_weekday')->nullable()->after('require_return_to_home_station');
            $table->string('timezone', 50)->default('Europe/Riga')->after('planning_opens_weekday');
        });
    }

    public function down(): void
    {
        Schema::table('shift_policies', function (Blueprint $table) {
            $table->dropColumn(['require_return_to_home_station', 'planning_opens_weekday', 'timezone']);
        });
    }
};
