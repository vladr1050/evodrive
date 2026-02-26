<?php

namespace App\Console\Commands;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use Illuminate\Console\Command;

class CompleteShiftsCommand extends Command
{
    protected $signature = 'shifts:complete';

    protected $description = 'Mark past ended shifts (status booked) as completed.';

    public function handle(): int
    {
        $now = now();
        $query = Shift::query()
            ->where('status', ShiftStatus::Booked)
            ->where('ends_at', '<=', $now);

        $count = $query->count();
        if ($count === 0) {
            $this->info('No shifts to complete.');

            return self::SUCCESS;
        }

        $updated = $query->update(['status' => ShiftStatus::Completed]);

        $this->info("Marked {$updated} shift(s) as completed.");

        return self::SUCCESS;
    }
}
