<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('stripe-smart.logging.table', 'stripe_payment_logs');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->nullable()->index();
            $table->string('type', 50)->default('charge');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('status', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('device', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tableName = config('stripe-smart.logging.table', 'stripe_payment_logs');
        Schema::dropIfExists($tableName);
    }
};
