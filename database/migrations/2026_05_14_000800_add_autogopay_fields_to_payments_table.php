<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedInteger('requested_amount')->default(0)->after('amount_paid');
            $table->string('provider', 40)->nullable()->after('method');
            $table->string('provider_transaction_id')->nullable()->after('reference_number')->index();
            $table->string('provider_order_id')->nullable()->after('provider_transaction_id');
            $table->text('qr_string')->nullable()->after('provider_order_id');
            $table->text('qr_url')->nullable()->after('qr_string');
            $table->dateTime('expires_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'requested_amount',
                'provider',
                'provider_transaction_id',
                'provider_order_id',
                'qr_string',
                'qr_url',
                'expires_at',
            ]);
        });
    }
};
