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
            $table->string('role')->default('moderator')->after('password');
            $table->timestamp('guest_expires_at')->nullable()->after('role');
            $table->boolean('is_blocked')->default(false)->after('guest_expires_at');
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
        });

        DB::table('users')->update([
            'role' => 'admin',
            'is_blocked' => false,
            'blocked_at' => null,
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
            ]);
        });
    }
};
