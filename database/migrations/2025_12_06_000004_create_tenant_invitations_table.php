<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('email', 255)->index();
            $table->string('invitation_token', 255)->unique();
            $table->string('first_name', 128)->nullable();
            $table->string('last_name', 128)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->json('response_data')->nullable(); // Stores registration form data
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};

