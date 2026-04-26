<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->after('cancelled_by_driver_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn('cancelled_by_user_id');
        });
    }
};
