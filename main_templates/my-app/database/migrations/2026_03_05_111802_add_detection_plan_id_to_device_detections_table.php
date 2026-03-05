<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_detections', function (Blueprint $table): void {
            $table->foreignId('detection_plan_id')
                ->nullable()
                ->after('device_profile_id')
                ->constrained('detection_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_detections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('detection_plan_id');
        });
    }
};
