<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_samples', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->unsignedTinyInteger('hour')->index();
            $table->timestamp('sampled_at')->index();

            $table->double('energy_abs')->default(0);
            $table->double('delta_energy')->default(0);
            $table->double('energy_socket_1')->default(0);
            $table->double('energy_socket_2')->default(0);
            $table->double('energy_socket_3')->default(0);

            $table->double('voltage')->default(0);
            $table->double('power')->default(0);
            $table->double('power_socket_1')->default(0);
            $table->double('power_socket_2')->default(0);
            $table->double('power_socket_3')->default(0);

            $table->double('current')->default(0);
            $table->double('current_1')->default(0);
            $table->double('current_2')->default(0);
            $table->double('current_3')->default(0);

            $table->string('warning_level', 16)->default('normal')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_samples');
    }
};
