<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 64)->index();
            $table->text('notes')->nullable();
            $table->double('expected_power_min')->default(0);
            $table->double('expected_power_max')->default(0);
            $table->double('avg_power_w')->default(0);
            $table->double('peak_power_w')->default(0);
            $table->double('avg_current_a')->default(0);
            $table->double('variability_pct')->default(0);
            $table->double('startup_ratio')->default(0);
            $table->json('signature_snapshot')->nullable();
            $table->unsignedTinyInteger('trained_from_socket')->nullable();
            $table->timestamp('last_trained_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_profiles');
    }
};
