<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role');
            $table->string('phone')->nullable();
            $table->string('status')->default('Disponible');
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('municipality');
            $table->integer('coverage')->default(0);
            $table->string('status')->default('Operativa');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('site_id')->constrained('sites');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('coverage')->default(0);
            $table->string('status')->default('En curso');
            $table->timestamps();
        });

        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('Disponible');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('people');
    }
};
