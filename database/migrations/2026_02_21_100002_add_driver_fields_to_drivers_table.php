<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('license_number')->nullable()->after('atd_number');
            $table->string('locale', 5)->default('en')->after('status');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
        foreach (DB::table('drivers')->get() as $d) {
            $name = (string) ($d->name ?? 'Unknown');
            $parts = explode(' ', $name, 2);
            DB::table('drivers')->where('id', $d->id)->update([
                'first_name' => $parts[0] ?? 'Unknown',
                'last_name' => $parts[1] ?? '',
            ]);
        }
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'license_number', 'locale', 'last_login_at']);
        });
    }
};
