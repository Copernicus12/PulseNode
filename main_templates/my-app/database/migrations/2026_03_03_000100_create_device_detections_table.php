<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_detections', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('socket_index')->index();
            $table->foreignId('device_profile_id')->nullable()->constrained('device_profiles')->nullOnDelete();
            $table->string('predicted_label');
            $table->string('predicted_category', 64)->nullable()->index();
            $table->unsignedTinyInteger('confidence')->default(0)->index();
            $table->json('signature_snapshot')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->string('status', 24)->default('matched')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_detections');
    }
};
