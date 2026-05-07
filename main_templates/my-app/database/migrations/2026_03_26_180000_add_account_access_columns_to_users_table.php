<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('moderator');
            $table->timestamp('guest_expires_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_at')->nullable();
            $table->string('account_status')->default('active');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
        });

        DB::table('users')->update([
            'role' => 'admin',
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => 'active',
            'requested_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'role',
                'guest_expires_at',
                'is_blocked',
                'blocked_at',
                'account_status',
                'requested_at',
                'approved_at',
                'rejected_at',
            ]);
        });
    }
};
