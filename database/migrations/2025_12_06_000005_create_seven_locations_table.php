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
        Schema::create('seven_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('api_location_id')->nullable();
            $table->bigInteger('location_id')->unique();
            $table->string('name', 191)->index('name');
            $table->string('alias', 50)->nullable();
            $table->string('address', 191)->nullable();
            $table->string('city', 191)->nullable();
            $table->string('state', 191)->nullable();
            $table->string('country', 191)->nullable();
            $table->boolean('hasResy')->default(false);
            $table->boolean('groupTips')->default(false);
            $table->boolean('active')->default(true);
            $table->string('resy_url', 191)->nullable();
            $table->string('resy_api_key', 191)->nullable();
            $table->string('toast_location', 100)->nullable();
            $table->string('toast_sftp_id', 100)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seven_locations');
    }
};

