<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('electricity_price_per_wh', 12, 6)->default(0);
            $table->string('billing_currency', 3)->default('RON');
            $table->decimal('billing_monthly_base_fee', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'electricity_price_per_wh',
                'billing_currency',
                'billing_monthly_base_fee',
            ]);
        });
    }
};
