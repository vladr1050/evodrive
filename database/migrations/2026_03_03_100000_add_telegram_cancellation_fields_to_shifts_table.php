<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add fields required for Telegram cancellation notifications and audit.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('cancelled_by_driver_id')->nullable()->after('cancel_reason')->constrained('drivers')->nullOnDelete();
            $table->dateTime('cancellation_notified_at')->nullable()->after('cancelled_by_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_driver_id']);
            $table->dropColumn(['cancelled_by_driver_id', 'cancellation_notified_at']);
        });
    }
};
