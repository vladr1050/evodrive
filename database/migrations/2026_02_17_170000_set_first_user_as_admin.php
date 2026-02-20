<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::where('id', 1)->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // Don't revert: we don't know previous role
    }
};
