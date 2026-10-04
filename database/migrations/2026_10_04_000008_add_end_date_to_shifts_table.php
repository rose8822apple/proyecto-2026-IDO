<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shifts', 'end_date')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->date('end_date')->nullable()->after('date');
            });
        }

        DB::table('shifts')->whereNull('end_date')->update([
            'end_date' => DB::raw('`date`'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts', 'end_date')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropColumn('end_date');
            });
        }
    }
};
