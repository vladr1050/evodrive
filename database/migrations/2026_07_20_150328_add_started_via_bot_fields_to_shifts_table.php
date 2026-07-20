<?php

use App\Models\CarCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dateTime('started_via_bot_at')->nullable()->after('confirmed_at');
            $table->dateTime('no_start_notified_at')->nullable()->after('started_via_bot_at');
        });

        // Backfill from successful Start shift commands (SQLite + Postgres).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('
                UPDATE shifts AS s
                SET started_via_bot_at = sub.first_started
                FROM (
                    SELECT shift_id, MIN(created_at) AS first_started
                    FROM car_commands
                    WHERE action = ?
                      AND status = ?
                    GROUP BY shift_id
                ) AS sub
                WHERE s.id = sub.shift_id
                  AND s.started_via_bot_at IS NULL
            ', [CarCommand::ACTION_START_SHIFT, CarCommand::STATUS_SENT]);
        } else {
            DB::statement('
                UPDATE shifts
                SET started_via_bot_at = (
                    SELECT MIN(car_commands.created_at)
                    FROM car_commands
                    WHERE car_commands.shift_id = shifts.id
                      AND car_commands.action = ?
                      AND car_commands.status = ?
                )
                WHERE started_via_bot_at IS NULL
                  AND EXISTS (
                    SELECT 1 FROM car_commands
                    WHERE car_commands.shift_id = shifts.id
                      AND car_commands.action = ?
                      AND car_commands.status = ?
                  )
            ', [
                CarCommand::ACTION_START_SHIFT,
                CarCommand::STATUS_SENT,
                CarCommand::ACTION_START_SHIFT,
                CarCommand::STATUS_SENT,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['started_via_bot_at', 'no_start_notified_at']);
        });
    }
};
