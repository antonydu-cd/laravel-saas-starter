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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('lago_plan_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('amount_currency', 3)->default('USD');
            $table->enum('interval', ['monthly', 'yearly', 'weekly'])->default('monthly');
            $table->unsignedInteger('trial_period')->default(0);
            $table->json('features')->nullable();
            $table->json('highlights')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('lago_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
