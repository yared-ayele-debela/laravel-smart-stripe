<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('stripe-smart.billable_table', 'users');

        if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'stripe_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('stripe_id')->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        $tableName = config('stripe-smart.billable_table', 'users');

        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'stripe_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('stripe_id');
            });
        }
    }
};
