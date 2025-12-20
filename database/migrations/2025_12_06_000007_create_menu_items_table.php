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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('menu_id')->index('menu_items_menu_id_foreign');
            $table->string('title', 191);
            $table->string('url', 191);
            $table->integer('parent_id')->nullable();
            $table->string('icon', 20)->nullable();
            $table->string('containerType', 20)->default('Standard');
            $table->string('route', 60)->nullable();
            $table->integer('order')->default(0);
            $table->tinyInteger('active')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('permission_id')->nullable()->index('menu_items_permission_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

