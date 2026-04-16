<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renter_contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renter_id')->constrained('renters')->cascadeOnDelete();
            $table->string('description');
            $table->string('stored_path', 512);
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renter_contract_documents');
    }
};
