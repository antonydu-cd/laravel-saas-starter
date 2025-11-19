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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('lago_subscription_id')->nullable();
            $table->string('lago_external_id')->nullable();
            $table->string('plan_code');
            $table->string('plan_name');
            $table->enum('status', ['active', 'pending', 'terminated', 'canceled'])->default('pending');
            $table->timestamp('subscription_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ending_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index('plan_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
