<?php

namespace App\Events;

use App\Models\Driver;
use App\Models\Shift;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShiftCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Shift $shift,
        public Driver $driver
    ) {
    }
}
