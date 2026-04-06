<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoice_files', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_key')->index();
            $table->string('owner_email')->nullable();
            $table->string('billing_period', 7)->index();
            $table->unsignedSmallInteger('billing_year')->index();
            $table->unsignedTinyInteger('billing_month')->index();
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 120);
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_files');
    }
};
