<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('strategy', 24)->default('balanced')->index();
            $table->unsignedTinyInteger('socket_scope')->nullable()->index();
            $table->unsignedSmallInteger('window_samples')->default(90);
            $table->unsignedTinyInteger('min_samples')->default(3);
            $table->unsignedTinyInteger('match_threshold')->default(68);
            $table->boolean('is_active')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_plans');
    }
};
