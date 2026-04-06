<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoice_folders', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_key')->index();
            $table->string('owner_email')->nullable();
            $table->string('folder_type', 20)->index();
            $table->string('folder_key', 7)->index();
            $table->unsignedSmallInteger('folder_year')->index();
            $table->unsignedTinyInteger('folder_month')->nullable()->index();
            $table->timestamps();

            $table->unique(['owner_key', 'folder_type', 'folder_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_folders');
    }
};
