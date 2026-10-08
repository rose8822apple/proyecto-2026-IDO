<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('nombre', 50);
            });
        }

    }

    public function down(): void
    {
        throw new RuntimeException('The shared roles table is preserved to avoid deleting existing role data.');
    }
};
