<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('pickup_delivery_type', 30)->default('none')->after('payment_method');
            $table->unsignedInteger('pickup_delivery_fee')->default(0)->after('pickup_delivery_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['pickup_delivery_type', 'pickup_delivery_fee']);
        });
    }
};
