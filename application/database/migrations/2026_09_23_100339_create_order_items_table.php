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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_id")->constrained("orders");
            $table->foreignId("ticket_type_id")->constrained("ticket_types");
            $table->integer("quantity");
            $table->bigInteger("unit_price");
            $table->timestamps();

            $table->unique([
                "order_id",
                "ticket_type_id",
            ]); // юзер-покупатель в одном заказе указывает в 1 заказе 1 тип билета (не получится взять 2 раза один и тот же тип билета)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
