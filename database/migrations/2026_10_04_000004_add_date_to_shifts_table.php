<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('shifts', 'date')) {
                $table->date('date')->nullable()->after('site_id');
            }
        });

        DB::table('shifts')->whereNull('date')->update([
            'date' => now()->toDateString(),
        ]);
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'date')) {
                $table->dropColumn('date');
            }
        });
    }
};
