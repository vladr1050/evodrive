<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('status');
            $table->dateTime('cancelled_at')->nullable()->after('confirmed_at');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'cancelled_at', 'cancel_reason']);
        });
    }
};
