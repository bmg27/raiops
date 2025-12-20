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
        Schema::create('rds_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(3306);
            $table->string('username');
            $table->text('password'); // Laravel Crypt encrypted
            $table->string('rai_database');
            $table->string('providers_database')->nullable();
            $table->string('app_url'); // URL to RAI instance for this RDS
            $table->boolean('is_active')->default(true);
            $table->boolean('is_master')->default(false);
            $table->enum('health_status', ['healthy', 'degraded', 'down', 'unknown'])->default('unknown');
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_master');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rds_instances');
    }
};
