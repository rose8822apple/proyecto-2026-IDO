<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('people') || Schema::hasColumn('people', 'status')) {
            return;
        }

        Schema::table('people', function (Blueprint $table): void {
            $table->string('status')->default('Disponible');
        });
    }

    public function down(): void
    {
        // Keep the column because it may have existed before this compatibility migration.
    }
};
