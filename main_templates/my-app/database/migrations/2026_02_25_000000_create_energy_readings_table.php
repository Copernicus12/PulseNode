<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_readings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->float('energy_socket_1')->default(0);
            $table->float('energy_socket_2')->default(0);
            $table->float('energy_socket_3')->default(0);
            $table->float('energy_total')->default(0);
            $table->timestamps();

            $table->unique('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_readings');
    }
};
