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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_id")->unique()->constrained("orders");
            $table->bigInteger("cashbox_id");
//            $table->string("external_order_id")->nullable();
            $table->bigInteger("amount");
            $table->string("status");
            $table->string("payment_url")->nullable();
            $table->timestamps();

            $table->unique([
                "cashbox_id",
                "order_id",
            ]);
            // external_order_id - $externalOrderId = "order-{$order->id}"; - именно то значение, которое передается в PS terminal's (string) order_id, уникальный только в рамках конкретного terminal
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
